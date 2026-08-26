# Backups y plan de recuperación de datos — Proseguir Factoring

SCRUM-247. Antes de esto no existía ningún backup en producción (verificado por SSH el
2026-08-24: sin crontab, sin systemd timer propio, `/backup` vacío).

## Qué se respalda

| Dato | Dónde vive | Cómo se respalda |
|---|---|---|
| Base de datos (MySQL 8.0) | volumen Docker `mysql_data` | `mysqldump` completo, comprimido |
| Documentos subidos (créditos, actas, firmas) | `backend/storage/app/public`, filesystem del host (bind mount, NO volumen Docker) | `tar.gz` del directorio |

## Política (ajustada 2026-08-26, a pedido de Luis)

- **Frecuencia**: 2 veces al día — **1:00 PM y 11:00 PM hora Colombia** (UTC-5 todo el año, sin
  horario de verano). El servidor corre en UTC (`Etc/UTC`, verificado 2026-08-26), así que el
  cron real usa **18:00 y 04:00 UTC**.
- **Retención local**: ring buffer de **6 archivos por tipo** (`db_*.sql.gz` y
  `storage_*.tar.gz` cada uno por su lado) = **3 días de historia** con 2 corridas diarias — día
  1: 2 backups, día 2: 4, día 3: 6; al llegar el día 4 se borran los 2 más viejos (día 1) para
  hacer lugar a los 2 nuevos, y así sucesivamente. Se rota por **conteo** (los 6 más recientes),
  no por antigüedad en días — evita el redondeo ambiguo de `find -mtime` justo en los bordes de
  24h/72h.
- **Capas de redundancia** (`scripts/backup-diario.sh`):
  1. Copia local en `~/backups/proseguir/` (`/home/admpsl/backups/proseguir/` en
     `proseguir-prod`) — recuperación rápida sin depender de red. **Esta es la ruta que necesita
     el administrador externo** para copiar los backups a una máquina fuera de este proveedor —
     ver "Copia externa manual" abajo.
  2. `rsync` a la VM de test — mismo proveedor, protege contra falla de disco de UNA máquina.
     Corre con `--delete-after`, así que sigue la misma rotación de la copia local sin que haga
     falta rotar dos veces.
- **SharePoint (rclone) descontinuado** (2026-08-26): se habló con el administrador del tenant y
  no se va a usar esa vía — la copia offsite ahora la hace el administrador a mano, copiando
  desde la ruta local de arriba. El script ya no intenta subir a SharePoint.

## RPO / RTO declarados

- **RPO (pérdida máxima aceptable de datos)**: ~12 horas — el backup corre 2 veces al día
  (1:00 PM y 11:00 PM hora Colombia). Antes del ajuste 2026-08-26 era de 24h (1 corrida diaria).
- **RTO (tiempo objetivo de recuperación)**: no cronometrado formalmente todavía más allá del
  drill de este documento (~10 min BD + ~2 min documentos en el ensayo de abajo, sobre datos de
  tamaño actual — 223M BD, 46M documentos). Con volumen mucho mayor este número cambia; re-medir
  el RTO cuando el volumen de datos crezca de forma significativa.

## Copia externa manual (reemplaza a SharePoint, ajuste 2026-08-26)

El administrador externo copia los backups él mismo desde esta ruta, con la periodicidad que
decida (no depende del cron de arriba):

```
Servidor: proseguir-prod (173.201.39.180, puerto 2282, usuario admpsl)
Ruta:     /home/admpsl/backups/proseguir/
Archivos: db_YYYY-MM-DD_HHMMSS.sql.gz (dump de BD) y storage_YYYY-MM-DD_HHMMSS.tar.gz (documentos)
```

Ejemplo desde la máquina externa (con las credenciales/llave que le dé Luis, no incluidas acá):

```bash
scp -P 2282 admpsl@173.201.39.180:~/backups/proseguir/*.gz /ruta/local/destino/
```

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
  backup del turno no corrió.
- El volumen de test comparte el mismo proveedor que prod — un desastre a nivel de datacenter/
  proveedor completo depende de que el administrador externo mantenga al día su copia manual
  (ver "Copia externa manual" arriba); no hay una pierna automática fuera del proveedor del VPS
  desde que se descontinuó SharePoint (2026-08-26).
