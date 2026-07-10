# Spec: Habilitar medición de cobertura de tests (backend + frontend)

**Date**: 2026-07-03
**Requested by**: Luis
**Status**: Draft
**Project**: Proseguir Factoring

## Problem
Hoy el proyecto solo mide tests en pass/fail. El backend corre 60 tests
(PHPUnit, suites `Unit` y `Feature` en `backend/phpunit.xml`) y el frontend
corre specs de Angular/Karma (`npm run test:ci`), pero en ningún punto del
pipeline se calcula qué **porcentaje del código** queda realmente ejercitado
por esos tests. Esto significa que:

- No hay forma objetiva de saber si un PR "bien testeado" realmente cubre
  las rutas críticas o solo los casos felices.
- El Senior Reviewer y Luis (como Arquitecto) no tienen una métrica para
  priorizar qué módulos necesitan más tests antes de tocarlos.
- Áreas del frontend con testing muy débil (ver "Edge cases") no salen a
  la luz hasta que aparece un bug en producción.

El backend hoy **no puede** generar cobertura aunque se le pida: la imagen
`backend/Dockerfile` (base `php:8.4-fpm`) no instala Xdebug ni PCOV, así que
`php artisan test --coverage` fallaría con "no coverage driver available".
El frontend tiene `karma-coverage` (~2.2.0) como devDependency en
`frontend/package.json`, pero no se invoca: `test:ci` no pasa
`--code-coverage` y no existe un `karma.conf.js` propio con reporters ni
umbrales configurados.

## Solution summary
Habilitar la generación de reportes de cobertura en ambos lados del stack
como paso **informativo** del pipeline (no bloqueante en esta primera
iteración), dejando la puerta abierta a un gate de umbral mínimo más
adelante una vez que exista una línea base real:

1. **Backend**: agregar un driver de cobertura (PCOV recomendado sobre
   Xdebug por su menor overhead en CI/build) a `backend/Dockerfile` y/o a un
   paso específico de CI, y correr `php artisan test --coverage` (o
   `--coverage-html`/`--coverage-clover` para reporte exportable).
2. **Frontend**: activar `--code-coverage` en `ng test` (vía script nuevo o
   `karma.conf.js` con `karma-coverage` configurado), generando reporte
   `lcov`/HTML en `coverage/`.
3. **CI**: extender `.github/workflows/deploy.yml` (jobs `test-backend` y
   `test-frontend`) para generar el reporte y publicarlo como artifact
   descargable (o imprimir el resumen de % en el log del job). Sin gate de
   fallo por umbral en esta fase.

## Users and roles
- **Luis (Arquitecto / reviewer)**: consume el % de cobertura para decidir
  qué priorizar y para juzgar calidad de un PR antes de aprobar diseño.
- **Senior Reviewer**: usa el reporte de cobertura como insumo objetivo
  durante la revisión de código (complementa, no reemplaza, la revisión
  manual).
- **QA**: usa el reporte para identificar módulos sin tests antes de
  planear casos de prueba manuales/regresión.
- **Backend Dev / Frontend Dev**: ven localmente qué líneas/branches no
  están cubiertas al desarrollar una feature nueva.
- No hay impacto para usuarios finales de Proseguir Factoring (clientes,
  operativos) — este cambio es 100% interno de ingeniería.

## Acceptance criteria
- [ ] Correr `php artisan test --coverage` (o equivalente) en el contenedor
      backend local produce un porcentaje de cobertura sin error de
      "driver not available".
- [ ] El job `test-backend` en CI genera un reporte de cobertura (HTML y/o
      Clover XML) y lo deja disponible como artifact del workflow run.
- [ ] Correr `npm run test:ci` (o un nuevo script `test:coverage`) en el
      frontend genera un reporte de cobertura en `frontend/coverage/`
      (HTML + lcov) sin cambiar el resultado pass/fail de los specs
      existentes.
- [ ] El job `test-frontend` en CI genera y publica el reporte de
      cobertura como artifact del workflow run.
- [ ] La documentación del proyecto (README o `docs/`) explica cómo leer
      el reporte de cobertura localmente (backend y frontend).
- [ ] Ningún test existente (60 backend, specs actuales frontend) cambia
      de resultado pass/fail a causa de este cambio.
- [ ] El paso de cobertura es informativo: **no** bloquea el merge ni el
      deploy en esta iteración (ver Open questions sobre gate futuro).

## Edge cases and error scenarios
1. **Runner de CI sin driver preinstalado**: GitHub Actions usa una imagen
   Ubuntu limpia; `shivammathur/setup-php@v2` (ya usado en `deploy.yml`)
   soporta instalar PCOV/Xdebug vía el parámetro `coverage:`, pero si se
   omite, el paso de cobertura fallará igual que en local. Debe agregarse
   explícitamente.
2. **Costo de build/imagen Docker**: agregar Xdebug a `backend/Dockerfile`
   aumenta tamaño de imagen y ralentiza cada request en local si queda
   habilitado por defecto (Xdebug tiene overhead notable incluso sin
   step-debugging activo). PCOV es preferible para CI porque solo agrega
   overhead durante la ejecución de tests con cobertura, pero igual debe
   evaluarse si se instala solo en una imagen de test separada o en la
   imagen de desarrollo/CI, nunca en la imagen de producción.
3. **Baseline de frontend probablemente muy baja**: hay 29 archivos
   `*.component.ts` pero solo 6 tienen `*.component.spec.ts` — es decir, la
   mayoría de componentes (incluyendo `upload.component.ts`) no tiene test
   alguno. Si se agregara un umbral mínimo de cobertura ahora, el build
   fallaría de inmediato. Por eso el umbral queda fuera de alcance en esta
   iteración (ver Open questions).
4. **Reporte de cobertura vacío o engañoso en Feature tests**: los tests de
   `Feature` en Laravel ejercitan rutas HTTP end-to-end y pueden inflar la
   cobertura de controladores sin que la lógica interna esté realmente
   validada por asserts significativos (cobertura ≠ calidad de test). Debe
   documentarse esta limitación para que el equipo no la use como única
   métrica de calidad.
5. **Falla silenciosa del paso de cobertura en CI**: si el paso de
   cobertura se agrega como "best effort" y falla (p. ej. por out-of-memory
   generando el reporte HTML), no debe tumbar el job de tests si se decidió
   que cobertura es informativa — debe aislarse en un step separado con
   manejo explícito de error, para no bloquear despliegues por un problema
   de tooling y no del código en sí.

## Out of scope
- Definir o aplicar un umbral mínimo de cobertura que bloquee el merge o
  el deploy (queda como iniciativa futura, condicionada a tener una línea
  base real medida primero).
- Escribir tests nuevos para subir el % de cobertura — este spec cubre
  solo la **medición**, no el cierre de brechas de testing.
- Introducir un framework de E2E (Cypress/Playwright) — hoy no existe
  ninguno en el repo; es una iniciativa separada y de mayor alcance.
- Cobertura de mutación (mutation testing) o cobertura de tipos
  (TypeScript strict coverage) — fuera de alcance, es una métrica distinta.
- Dashboards externos de cobertura (Codecov, Coveralls, SonarQube) —
  posible fase 2 una vez que el reporte básico funcione en CI.

## Open questions
1. **¿Xdebug o PCOV para el backend?** PCOV es más liviano y es la
   recomendación por defecto para "solo medir cobertura en CI/tests", pero
   si el equipo también quiere step-debugging remoto en desarrollo local
   más adelante, Xdebug serviría para ambos casos con más overhead. Se
   necesita confirmación de Luis/Arquitecto sobre si step-debugging es una
   necesidad real antes de decidir.
2. **¿Se fija de una vez un umbral mínimo de cobertura como gate de CI, o
   se deja 100% informativo en esta fase?** Recomendación del Analista:
   dejarlo informativo ahora y fijar un umbral (ej. no bajar del % medido
   en el primer reporte) en una iteración posterior, dado que el frontend
   probablemente parte de una cobertura muy baja (solo 6 de 29 componentes
   tienen spec) y un gate inmediato rompería el pipeline en el primer push.
3. **¿Dónde se publica/visualiza el reporte?** Como artifact descargable
   del run de GitHub Actions es la opción más simple y sin costo, pero si
   Luis quiere un dashboard con tendencia histórica (subir/bajar % por
   sprint) se necesitaría un servicio externo (Codecov/Coveralls) o un
   job que persista el número en algún lado — esto cambia el esfuerzo de
   "CI-integration" de simple a medio.

## Complexity estimate
**General: Medium** (para la medición básica, sin gate de umbral ni
dashboard externo). Desglose por componente:

- **Backend coverage**: Simple–Medium. Agregar PCOV (o Xdebug) a la imagen
  Docker y al runner de CI vía `shivammathur/setup-php@v2` con
  `coverage: pcov`, más el flag `--coverage` en el comando de test. Riesgo
  principal es validar que no rompe el tiempo de build ni el
  `docker compose up -d --build` de producción/test si se comparte la
  misma imagen (recomendación: aislar en un stage/target de Docker
  distinto para CI, no tocar la imagen que se despliega).
- **Frontend coverage**: Simple. `karma-coverage` ya está instalado como
  dependencia; solo falta un `karma.conf.js` con el reporter configurado y
  un script `test:coverage` (o flag `--code-coverage` en `test:ci`). Bajo
  riesgo técnico, pero expone una brecha de testing ya conocida
  (6/29 componentes con spec).
- **CI integration**: Medium. Requiere modificar `deploy.yml` para generar
  y subir artifacts en ambos jobs sin romper el gate actual de
  pass/fail que condiciona `deploy-prod`/`deploy-test`. Si se agrega
  publicación en dashboard externo (Open question 3), sube a Medium–Complex.

## References
- `backend/phpunit.xml` — suites `Unit` y `Feature`, 60 tests actuales.
- `backend/Dockerfile` — imagen base `php:8.4-fpm`, sin Xdebug/PCOV.
- `frontend/package.json` — `karma-coverage: ~2.2.0` presente pero no
  invocado; script `test:ci` sin `--code-coverage`.
- No existe `frontend/karma.conf.js` propio en el repo.
- `.github/workflows/deploy.yml` — jobs `test-backend` y `test-frontend`
  como gate pass/fail antes de `deploy-prod`/`deploy-test`; ningún paso de
  cobertura hoy.
- No existe framework E2E (Cypress/Playwright) en el repo (solo aparece
  `cypress.config.js` como parte de una dependencia vendorizada de
  `swagger-ui`, no relacionado con testing del proyecto).
