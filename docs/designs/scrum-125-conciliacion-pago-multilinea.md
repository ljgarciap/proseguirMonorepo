# Diseño técnico: SCRUM-125 — Conciliación pago multi-renglón

**Date**: 2026-07-14
**Spec**: `docs/specs/scrum-125-conciliacion-pago-multilinea.md`
**Status**: Approved para PM

## Componentes afectados
- `backend/app/Services/ConciliationService.php` — método `parseBankText()` (líneas 83-118). Único archivo con lógica a cambiar.
- `backend/tests/Feature/ConciliationServiceValorParsingTest.php` — actualizar caso existente + agregar caso nuevo.

No se toca `extract_pdf.cjs` (la extracción de texto crudo está bien, el problema es el parseo posterior) ni `performMatching()` (no tiene bug, solo no recibe el dato).

## Diseño de la solución
`parseBankText()` hoy recorre el texto línea por línea y descarta cualquiera que no
tenga fecha válida en la primera columna Y monto válido en la última, en un solo paso.

Cambio: separar en dos pasadas.

**Pasada 1 — reensamblado de líneas huérfanas**
Recorrer las líneas crudas. Para cada línea:
- Si empieza con fecha válida (`YYYY/MM/DD`) → es el inicio de un registro nuevo, se guarda como línea "activa".
- Si NO empieza con fecha válida → es huérfana. Se concatena (append de columnas) a la línea activa más reciente, en vez de descartarse inmediatamente.

Esto reemplaza el descarte silencioso actual por una fusión explícita, y es agnóstico del
contenido (no depende de que la huérfana contenga o no un nombre de persona — cubre
cualquier wrap de columna, no solo "Wilson Murillo").

**Pasada 2 — validación de fecha + monto (igual que hoy)**
Sobre las líneas ya reensambladas, aplicar el filtro existente: fecha válida en la primera
columna + monto válido en la última columna. Lo que no pase, se descarta (mismo
comportamiento actual para basura real).

**Caso borde — huérfana sin línea activa previa** (primera línea del archivo huérfana):
se descarta con `Log::warning` (nuevo), no rompe el resto del parseo.

**Caso borde — huérfana que en realidad es basura** (headers, totales, pie de página):
como se fusiona con la línea activa anterior y luego se revalida fecha+monto, si el
resultado fusionado no pasa el filtro de monto se descarta igual que hoy — no cambia el
comportamiento para basura genuina, solo para continuaciones legítimas de pago.

## Riesgo y mitigación
| Riesgo | Mitigación |
|---|---|
| Fusionar dos pagos reales consecutivos donde el segundo por error de OCR/PDF no trae fecha | Bajo probabilidad — cada pago del banco siempre trae fecha en el extracto real; si ocurriera, el monto resultante sería la suma/concat de dos montos y fallaría el regex de VALOR (`-?[\d.,]*\d[.,]\d{2}`), quedando descartado igual que hoy (fail-safe, no fail-silent-wrong). |
| Regresión en conciliaciones ya funcionando (pagos de un solo renglón) | Test de regresión explícito con los casos existentes de `ConciliationServiceValorParsingTest` antes de mergear. |

## Tareas para PM
1. **Backend** — Implementar reensamblado de líneas huérfanas en `parseBankText()`.
   Depende de: nada. Archivos: `ConciliationService.php`.
   Aceptación: casos de test de la spec pasan, incluido el caso Wilson Murillo con los
   archivos adjuntos en SCRUM-125.
   Esfuerzo: ~2h.
2. **Backend** — Actualizar `ConciliationServiceValorParsingTest` (ajustar caso existente
   que hoy espera rechazo del caso multi-línea) + agregar test nuevo de reensamblado.
   Depende de: Tarea 1. Esfuerzo: ~1h.
3. **QA** — Validar manualmente con los archivos reales del ticket
   (`ZIP*37391276617*000000900354306*20260710*14162678.pdf` + `PSL ABONOS JUNIO 2026.xlsx`)
   corriendo el endpoint `conciliate()`. Esfuerzo: ~30min.

Sin dependencia de Frontend ni DevOps.
