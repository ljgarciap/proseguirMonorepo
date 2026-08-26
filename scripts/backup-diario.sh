#!/usr/bin/env bash
#
# SCRUM-247 — Backup de Proseguir Factoring (BD + documentos).
#
# Qué respalda:
#   1. Dump completo de MySQL (mysqldump dentro de factoring_db)
#   2. tar de backend/storage/app/public (documentos subidos, PDFs de
#      actas/firmas — viven en el filesystem del host, NO en un volumen
#      Docker, ver docker-compose.yml)
#
# Frecuencia (ajustada 2026-08-26, a pedido de Luis): 2 veces al día, vía
# cron — 1:00 PM y 11:00 PM hora Colombia (UTC-5 todo el año, sin horario de
# verano). El servidor corre en UTC, así que el cron usa 18:00 y 04:00 UTC:
#   0 18 * * * .../backup-diario.sh >> ...   # 1:00 PM Colombia
#   0 4  * * * .../backup-diario.sh >> ...   # 11:00 PM Colombia (día anterior)
#
# Rotación (ajustada 2026-08-26): ring buffer de $RETENTION_BACKUPS archivos
# por tipo (db_*.sql.gz y storage_*.tar.gz por separado) — con 2 corridas
# diarias, 6 = 3 días de historia (día 1: 2, día 2: 4, día 3: 6: al llegar
# el día 4 se borran los 2 del día 1 para hacer lugar a los 2 nuevos).
# Se rota por CONTEO (los N más recientes de `ls -t`), no por edad en días
# (`find -mtime`): evita el redondeo ambiguo de mtime en los bordes exactos
# de 24h/72h y es exactamente el criterio que pidió Luis ("se borran los 2
# más viejos cuando entran 2 nuevos"), autocorrige solo si alguna corrida
# se atrasa o se salta.
#
# Dónde queda:
#   - Copia local en $BACKUP_DIR (ver ruta abajo) — ESTA es la ruta que
#     necesita el administrador externo para copiar los backups a una
#     máquina fuera de este proveedor (SCRUM-247, ajuste 2026-08-26: ya no
#     se usa SharePoint/rclone, la copia offsite ahora la hace el
#     administrador a mano tomando los archivos de acá).
#   - Copia "espejo" por rsync a la VM de test (misma cuenta/proveedor,
#     protege contra falla de disco de UNA máquina, no un desastre del
#     proveedor completo). Rsync corre con --delete-after, así que la
#     rotación de acá se refleja también ahí — nunca hay que rotar dos
#     veces.
#
# Uso: se instala vía cron (ver instrucciones al final del script y
# docs/backup-recovery.md). Corrida manual: ./backup-diario.sh
#
set -euo pipefail

APP_DIR="/home/admpsl/apps/proseguir"
BACKUP_DIR="$HOME/backups/proseguir"
RETENTION_BACKUPS=6  # 2 corridas/día × 3 días de historia
FECHA="$(date +%Y-%m-%d_%H%M%S)"
RSYNC_DESTINO_TEST="admpsl@10.10.1.150"  # IP interna de la VM de test, alcanzable desde prod
RSYNC_KEY="$HOME/.ssh/id_ed25519_backup"  # key dedicada prod→test (ver docs/backup-recovery.md)
LOG_TAG="[backup-diario $FECHA]"

mkdir -p "$BACKUP_DIR"

echo "$LOG_TAG Iniciando backup..."

# 1. Dump de MySQL
DUMP_FILE="$BACKUP_DIR/db_${FECHA}.sql.gz"
docker exec factoring_db sh -c 'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" factoring_db' \
    | gzip > "$DUMP_FILE"
echo "$LOG_TAG Dump de BD: $DUMP_FILE ($(du -h "$DUMP_FILE" | cut -f1))"

# 2. tar de documentos subidos
STORAGE_FILE="$BACKUP_DIR/storage_${FECHA}.tar.gz"
tar -czf "$STORAGE_FILE" -C "$APP_DIR/backend/storage/app" public
echo "$LOG_TAG Documentos: $STORAGE_FILE ($(du -h "$STORAGE_FILE" | cut -f1))"

# 3. Rotación local — ring buffer de $RETENTION_BACKUPS archivos por tipo
for patron in 'db_*.sql.gz' 'storage_*.tar.gz'; do
    # shellcheck disable=SC2012
    ls -1t "$BACKUP_DIR"/$patron 2>/dev/null \
        | tail -n "+$((RETENTION_BACKUPS + 1))" \
        | while IFS= read -r viejo; do rm -f -- "$viejo"; done
done
echo "$LOG_TAG Rotación local aplicada (retención: ${RETENTION_BACKUPS} archivos = 3 días de historia)"

# 4. rsync a la VM de test (siempre, es gratis y ya funciona hoy)
if command -v rsync >/dev/null 2>&1; then
    rsync -az --delete-after -e "ssh -i ${RSYNC_KEY} -o StrictHostKeyChecking=accept-new" \
        "$BACKUP_DIR/" "${RSYNC_DESTINO_TEST}:backups/proseguir-prod/" 2>&1 \
        && echo "$LOG_TAG rsync a VM de test OK" \
        || echo "$LOG_TAG AVISO: rsync a VM de test falló (ver salida arriba) — no se aborta el backup por esto"
else
    echo "$LOG_TAG AVISO: rsync no está instalado, se salta esa copia"
fi

echo "$LOG_TAG Backup completo. Ruta para copia externa manual: $BACKUP_DIR"

# --- Instalación del cron (correr una sola vez, no lo hace este script solo) ---
# crontab -e
# 0 18 * * * /home/admpsl/apps/proseguir/scripts/backup-diario.sh >> /home/admpsl/backups/proseguir/backup.log 2>&1
# 0 4  * * * /home/admpsl/apps/proseguir/scripts/backup-diario.sh >> /home/admpsl/backups/proseguir/backup.log 2>&1
