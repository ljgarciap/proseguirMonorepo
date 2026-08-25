# Backups de Proseguir Factoring — Guía rápida

SCRUM-247. Referencia rápida para el día a día; el detalle completo (política, RPO/RTO, pasos de
restauración, resultado del drill) vive en [`docs/backup-recovery.md`](../docs/backup-recovery.md).

## ¿Qué se respalda y cuándo?

- **Base de datos** (MySQL, `mysqldump`) + **documentos subidos** (`storage/app/public`, tar).
- **Diario, 3:00 AM**, vía cron en el servidor de prod. Retención local: 30 días.
- 3 copias: local (`~/backups/proseguir/`) → VM de test (rsync) → SharePoint corporativo (rclone,
  **pendiente de activar**, ver abajo).

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

## Activar la copia offsite en SharePoint (pendiente)

El script (`backup-diario.sh`) ya intenta subir a SharePoint en cada corrida, pero se salta ese
paso con un aviso hasta que el remote `proseguir-sharepoint` de `rclone` quede configurado — login
OAuth único, pasos completos en `docs/backup-recovery.md`. Mientras tanto, las otras 2 copias
(local + VM de test) siguen funcionando sin depender de esto.

## Correr el backup a mano (fuera del horario del cron)

```bash
ssh proseguir-prod
cd /home/admpsl/apps/proseguir
./scripts/backup-diario.sh
```
