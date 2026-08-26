# Backups de Proseguir Factoring — Guía rápida

SCRUM-247. Referencia rápida para el día a día; el detalle completo (política, RPO/RTO, pasos de
restauración, resultado del drill) vive en [`docs/backup-recovery.md`](../docs/backup-recovery.md).

## ¿Qué se respalda y cuándo?

- **Base de datos** (MySQL, `mysqldump`) + **documentos subidos** (`storage/app/public`, tar).
- **2 veces al día — 1:00 PM y 11:00 PM hora Colombia** (18:00 y 04:00 UTC, servidor en UTC),
  vía cron en el servidor de prod. Retención local: 6 archivos por tipo = 3 días de historia
  (ring buffer: el 4º día se borran los 2 más viejos al entrar los 2 nuevos).
- 2 copias: local (`~/backups/proseguir/`) → VM de test (rsync). La copia offsite ya **no** es
  SharePoint (descontinuado 2026-08-26) — la hace el administrador externo a mano, ver
  "Copia externa" abajo.

## Verificar que el backup de hoy corrió bien

```bash
ssh proseguir-prod
tail -20 ~/backups/proseguir/backup.log
ls -la ~/backups/proseguir/ | tail -5
```

Si no ves un `db_*.sql.gz` y un `storage_*.tar.gz` de hoy, algo falló — revisar el log completo
(`cat ~/backups/proseguir/backup.log`) antes de asumir que todo está bien.

## "Se cayó el servidor / se corrompió algo, necesito recuperar YA"

1. No entrar en pánico y NO restaurar sobre la BD real sin avisar a Luis primero, salvo que él ya
   haya dado la orden explícita — restaurar sobreescribe lo que haya en ese momento.
2. Los pasos exactos de restauración (BD y documentos) están en
   [`docs/backup-recovery.md`](../docs/backup-recovery.md#restauración--pasos-concretos).
3. Si prod no responde en absoluto, los mismos backups ya están espejados en la VM de test
   (`~/backups/proseguir-prod/` en `proseguir-test`) — no dependés de que prod vuelva para
   recuperar los datos.

## Copia externa (reemplaza a SharePoint, descontinuado 2026-08-26)

El administrador externo copia los backups él mismo desde `/home/admpsl/backups/proseguir/` en
`proseguir-prod` (173.201.39.180, puerto 2282) — no depende del cron ni de este script. Detalle
completo (comando `scp` de ejemplo) en `docs/backup-recovery.md`.

## Correr el backup a mano (fuera del horario del cron)

```bash
ssh proseguir-prod
cd /home/admpsl/apps/proseguir
./scripts/backup-diario.sh
```
