# Spec: Fix conciliación — pagos que ocupan dos renglones en el extracto bancario (SCRUM-125)

**Date**: 2026-07-14
**Requested by**: Luis (reportado por Juan Andrés Ramírez Gómez)
**Status**: Approved
**Project**: Proseguir Factoring

## Problem
En la conciliación de pagos (`ConciliationController` + `ConciliationService`), cuando un
pago en el extracto bancario ocupa **dos renglones** del PDF (el monto queda en una fila
separada de la fecha/descripción), el sistema no lo reconoce como un pago del banco.
El resultado aparece incorrectamente como `SOLO EN SUSUERTE`, generando una novedad de
conciliación falsa. Caso reportado: pagos con "REFERENCIA 1" = "Wilson Murillo"
(ZIP*37391276617*000000900354306*20260710*14162678.pdf, PSL ABONOS JUNIO 2026.xlsx).

## Causa raíz (confirmada en código)
- `extract_pdf.cjs` agrupa el texto del PDF en filas por posición Y (`Y_TOLERANCE`), una
  fila por grupo — asume 1 fila = 1 transacción.
- `ConciliationService::parseBankText()` (backend/app/Services/ConciliationService.php,
  líneas 83-118) exige que **cada línea individual** tenga fecha `YYYY/MM/DD` en la primera
  columna (línea 91) y un monto válido en la última (línea 101, regex `-?[\d.,]*\d[.,]\d{2}`).
- Cuando el pago se parte en dos renglones: la fila 1 trae fecha + descripción terminando en
  el nombre (ej. "WILSON MURILLO") → se descarta porque el último campo no es un monto.
  La fila 2 trae solo el monto → se descarta porque no tiene fecha válida en la primera
  columna. El pago nunca entra a `$bankData` y `performMatching()` (líneas 120-172) no
  encuentra con qué emparejar el registro de Susuerte.
- Test existente `ConciliationServiceValorParsingTest::test_rejects_rows_where_last_column_is_a_person_name`
  (líneas 65-72) usa este caso como "correcto a rechazar" — hay que revisar ese test junto
  con el fix.

## Solution summary
Antes de aplicar el filtro de fecha/VALOR por línea, re-ensamblar filas "huérfanas"
(sin fecha válida en la primera columna) con la fila inmediatamente anterior, de forma
genérica — no solo para el caso "Wilson Murillo" puntual. Si una línea no matchea el
patrón de fecha inicial, se concatena/fusiona con la línea previa antes de re-evaluar
fecha + monto. Esto cubre cualquier pago futuro que se parta en 2+ renglones, no solo el
caso reportado.

## Users and roles
Usuarios que corren conciliación de pagos (rol con acceso a `ConciliationController`,
hoy sin restricción de rol específica documentada — confirmar si aplica alguna).

## Acceptance criteria
- [ ] Un pago cuyo renglón en el PDF del banco se divide en 2 líneas (fecha+descripción
      en una, monto en la siguiente) es reconocido como una sola transacción con fecha y
      monto correctos.
- [ ] El caso reportado (Wilson Murillo, ZIP*37391276617*000000900354306*20260710*14162678.pdf
      vs PSL ABONOS JUNIO 2026.xlsx) concilia correctamente como match banco↔Susuerte, no
      como "SOLO EN SUSUERTE".
- [ ] Pagos normales de un solo renglón siguen conciliando exactamente igual que antes (no
      regresión).
- [ ] Filas verdaderamente inválidas (basura, encabezados, totales) se siguen descartando
      igual que antes.
- [ ] El test `test_rejects_rows_where_last_column_is_a_person_name` se actualiza para
      reflejar el nuevo comportamiento esperado (el caso ya no debe rechazarse si viene
      seguido de una fila-continuación con el monto).
- [ ] Nuevo test cubre explícitamente el caso de pago partido en 2 renglones.

## Edge cases and error scenarios
- Pago partido en **3 o más** renglones (fecha, descripción larga, monto en 3ra fila) —
  definir si se cubre en este fix o queda fuera de alcance.
- Dos pagos consecutivos sin fecha completa uno detrás del otro (fila huérfana que no
  corresponde a continuación sino a un segundo pago malformado) — no debe fusionarse
  incorrectamente con la fila anterior generando un monto erróneo.
- Fila huérfana al inicio del archivo (sin fila previa a la cual fusionarse) — debe
  descartarse con log/advertencia, no debe romper el parseo del resto del archivo.
- El monto de la fila de continuación debe seguir pasando la validación de formato de
  VALOR existente; si no la pasa, se descarta como hoy (evitar falsos positivos).

## Out of scope
- Cambios al pipeline OCR Gemini/Mistral (`ProcessUploadJob`) — este módulo no lo usa.
- Cambios al `ReconciliationController` (conciliación factura/banco "Contable*"), que es
  un módulo distinto no afectado por este bug.
- Reprocesar conciliaciones históricas ya generadas con el bug (no se re-concilia
  retroactivamente salvo pedido explícito).

## Open questions
- [Luis] Confirmar si el fix debe reprocesar manualmente el caso puntual de junio 2026
  (los archivos ya adjuntos en el ticket) una vez desplegado, o si Luis lo hace él mismo.
- [Architect] Definir el límite de renglones a fusionar (2 vs N) — la spec asume fusión
  genérica de una fila huérfana con la anterior; evaluar si generalizar a cadenas más
  largas trae riesgo de fusionar filas que no deberían fusionarse.

## References
- `backend/app/Http/Controllers/ConciliationController.php` (endpoint `conciliate()`, ~línea 53)
- `backend/app/Services/ConciliationService.php` (`readBankPdf` ~línea 76, `parseBankText` líneas 83-118, `performMatching` líneas 120-172)
- `backend/extract_pdf.cjs` (extracción de texto del PDF, `Y_TOLERANCE` línea 15)
- `backend/tests/Feature/ConciliationServiceValorParsingTest.php`
- `backend/tests/Feature/ConciliacionSusuerteTest.php`
- Jira: SCRUM-125
