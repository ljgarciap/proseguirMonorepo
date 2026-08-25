# Backups y plan de recuperación de datos — Proseguir Factoring

SCRUM-247. Antes de esto no existía ningún backup en producción (verificado por SSH el
2026-08-24: sin crontab, sin systemd timer propio, `/backup` vacío).

## Qué se respalda

| Dato | Dónde vive | Cómo se respalda |
|---|---|---|
| Base de datos (MySQL 8.0) | volumen Docker `mysql_data` | `mysqldump` completo, comprimido |
| Documentos subidos (créditos, actas, firmas) | `backend/storage/app/public`, filesystem del host (bind mount, NO volumen Docker) | `tar.gz` del directorio |

## Política

- **Frecuencia**: diaria, 3:00 AM hora del servidor.
- **Retención local**: 30 días (rotación automática en el script).
- **Capas de redundancia** (`scripts/backup-diario.sh`):
  1. Copia local en `~/backups/proseguir/` — recuperación rápida sin depender de red.
  2. `rsync` a la VM de test — mismo proveedor, protege contra falla de disco de UNA máquina.
  3. `rclone` a SharePoint corporativo (`coordinadorcomercial@proseguirliquidez.com`) —
     offsite real, fuera del proveedor del VPS. **Pendiente activar**: requiere el login OAuth
     único de rclone, ver sección siguiente.

## RPO / RTO declarados

- **RPO (pérdida máxima aceptable de datos)**: 24 horas — el backup corre una vez al día.
- **RTO (tiempo objetivo de recuperación)**: no cronometrado formalmente todavía más allá del
  drill de este documento (~10 min BD + ~2 min documentos en el ensayo de abajo, sobre datos de
  tamaño actual — 223M BD, 46M documentos). Con volumen mucho mayor este número cambia; re-medir
  el RTO cuando el volumen de datos crezca de forma significativa.

## Activar la pierna de SharePoint (pendiente, password del login era incorrecta el 2026-08-24)

1. En tu laptop: `brew install rclone`
2. En el servidor (`ssh proseguir-prod`): `~/bin/rclone config` → nuevo remote `proseguir-sharepoint`,
   tipo `onedrive`, sin browser automático (`n`) → te da un comando `rclone authorize "onedrive"`.
3. En tu laptop, correr ese comando exacto → login con `coordinadorcomercial@proseguirliquidez.com`
   en el navegador → copiar el bloque de token que imprime.
4. Pegarlo de vuelta en la terminal del servidor (paso 2, quedó esperando) → completar tipo de
   cuenta (Business/SharePoint) y sitio.
5. Verificar: `~/bin/rclone listremotes` debe mostrar `proseguir-sharepoint:`. Desde ahí,
   `scripts/backup-diario.sh` empieza a subir a SharePoint solo, sin tocar nada más.

## Restauración — pasos concretos

### Restaurar la base de datos

```bash
ssh proseguir-prod   # o proseguir-test para un ensayo
cd /home/admpsl/apps/proseguir

# 1. Descomprimir el dump elegido
gunzip -k ~/backups/proseguir/db_YYYY-MM-DD_HHMMSS.sql.gz

# 2. Restaurar (SOBREESCRIBE la BD actual del contenedor — confirmar antes con Luis si es prod)
docker exec -i factoring_db sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" factoring_db' \
    < ~/backups/proseguir/db_YYYY-MM-DD_HHMMSS.sql
```

### Restaurar documentos

```bash
cd /home/admpsl/apps/proseguir/backend/storage/app
tar -xzf ~/backups/proseguir/storage_YYYY-MM-DD_HHMMSS.tar.gz
# recrea el directorio 'public' con los documentos del momento del backup
```

### Recuperar desde SharePoint (cuando la pierna offsite esté activa)

```bash
~/bin/rclone copy proseguir-sharepoint:Backups/Proseguir/db_YYYY-MM-DD_HHMMSS.sql.gz ~/backups/proseguir/
# luego los mismos pasos de restauración de arriba
```

## Prueba de restauración real (drill) — no asumir que un backup sirve porque corrió sin error

Hecho el 2026-08-24 sobre la VM de **test** (no prod, para no arriesgar datos reales):

1. Backup manual corrido (`./scripts/backup-diario.sh`) — dump + tar generados OK.
2. Verificado el dump con `gunzip -t` (integridad del gzip) — OK.
3. Restaurado sobre una base de datos de prueba aparte (no la de test en uso) — conteos de tablas
   coincidieron con el origen (`clientes`, `users`, etc.).
4. Documentos: extraído el tar a un directorio temporal, verificado que los archivos abren
   correctamente (no corruptos).

**Repetir este drill al menos una vez por trimestre** — un backup nunca probado no es un backup
confiable, es una suposición.

## Qué NO cubre esto todavía

- No hay alerta automática si el cron falla (queda en `~/backups/proseguir/backup.log`, hay que
  revisarlo). Si se vuelve crítico, agregar un chequeo que avise (ej. push notification) si el
  backup del día no corrió.
- El volumen de test comparte el mismo proveedor que prod — un desastre a nivel de datacenter/
  proveedor completo solo lo cubre la pierna de SharePoint, hoy pendiente de activar.
