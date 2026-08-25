#!/usr/bin/env bash
#
# SCRUM-247 — Backup diario de Proseguir Factoring (BD + documentos).
#
# Qué respalda:
#   1. Dump completo de MySQL (mysqldump dentro de factoring_db)
#   2. tar de backend/storage/app/public (documentos subidos, PDFs de
#      actas/firmas — viven en el filesystem del host, NO en un volumen
#      Docker, ver docker-compose.yml)
#
# Dónde queda:
#   - Copia local en $BACKUP_DIR, con rotación de $RETENTION_DIAS días.
#   - Copia "espejo" por rsync a la VM de test (misma cuenta/proveedor,
#     protege contra falla de disco de UNA máquina, no un desastre del
#     proveedor completo).
#   - Copia offsite real vía rclone al remote $RCLONE_REMOTE (SharePoint
#     corporativo) — SOLO si ese remote ya está configurado
#     (`rclone listremotes`). Si no existe todavía, este paso se salta
#     con un aviso, no falla el backup — permite que las 2 primeras
#     capas (local + rsync) ya estén funcionando mientras se completa el
#     login OAuth de rclone (ver docs/backup-recovery.md).
#
# Uso: se instala vía cron (ver instrucciones al final del script y
# docs/backup-recovery.md). Corrida manual: ./backup-diario.sh
#
set -euo pipefail

APP_DIR="/home/admpsl/apps/proseguir"
BACKUP_DIR="$HOME/backups/proseguir"
RETENTION_DIAS=30
FECHA="$(date +%Y-%m-%d_%H%M%S)"
RCLONE="$HOME/bin/rclone"
RCLONE_REMOTE="proseguir-sharepoint"
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

# 3. Rotación local — borra backups locales de más de $RETENTION_DIAS días
find "$BACKUP_DIR" -name 'db_*.sql.gz' -mtime "+${RETENTION_DIAS}" -delete
find "$BACKUP_DIR" -name 'storage_*.tar.gz' -mtime "+${RETENTION_DIAS}" -delete
echo "$LOG_TAG Rotación local aplicada (retención: ${RETENTION_DIAS} días)"

# 4. rsync a la VM de test (siempre, es gratis y ya funciona hoy)
if command -v rsync >/dev/null 2>&1; then
    rsync -az --delete-after -e "ssh -i ${RSYNC_KEY} -o StrictHostKeyChecking=accept-new" \
        "$BACKUP_DIR/" "${RSYNC_DESTINO_TEST}:backups/proseguir-prod/" 2>&1 \
        && echo "$LOG_TAG rsync a VM de test OK" \
        || echo "$LOG_TAG AVISO: rsync a VM de test falló (ver salida arriba) — no se aborta el backup por esto"
else
    echo "$LOG_TAG AVISO: rsync no está instalado, se salta esa copia"
fi

# 5. Offsite real (SharePoint vía rclone) — solo si el remote ya existe
if [ -x "$RCLONE" ] && "$RCLONE" listremotes | grep -q "^${RCLONE_REMOTE}:"; then
    "$RCLONE" copy "$BACKUP_DIR" "${RCLONE_REMOTE}:Backups/Proseguir" \
        --include "db_${FECHA}.sql.gz" --include "storage_${FECHA}.tar.gz" \
        && echo "$LOG_TAG Subida a SharePoint OK" \
        || echo "$LOG_TAG AVISO: subida a SharePoint falló (ver salida arriba) — no se aborta el backup por esto"
else
    echo "$LOG_TAG AVISO: remote '${RCLONE_REMOTE}' de rclone no configurado todavía — se salta la copia offsite a SharePoint (ver docs/backup-recovery.md)"
fi

echo "$LOG_TAG Backup completo."

# --- Instalación del cron (correr una sola vez, no lo hace este script solo) ---
# crontab -e
# 0 3 * * * /home/admpsl/apps/proseguir/scripts/backup-diario.sh >> /home/admpsl/backups/proseguir/backup.log 2>&1
