# Cobertura de tests (backend + frontend)

**Fase actual: informativa, no bloqueante.** Ningún build falla hoy por
tener cobertura baja. Este documento explica cómo generar y leer el
reporte de cobertura, tanto local como en CI.

## Backend (Laravel / PHPUnit + PCOV)

La imagen Docker de producción (`backend/Dockerfile`, stage `production`,
la que usa `docker-compose.yml` vía `target: production`) **no incluye**
ningún driver de cobertura (ni PCOV ni Xdebug) — correr tests con
cobertura ahí falla con "no coverage driver available". Para medir
cobertura localmente hay que construir el stage `local-coverage`, que sí
instala PCOV, aparte de la imagen que se despliega.

### Cómo correrlo

```bash
cd backend
docker build --target=local-coverage -t factoring-backend-coverage .
docker run --rm -v "$(pwd)":/var/www/html factoring-backend-coverage \
  composer test:coverage
```

`composer test:coverage` ejecuta:

```
php artisan test --coverage-clover=coverage/clover.xml --coverage-html=coverage/html
```

### Dónde ver el reporte (local)

- HTML navegable: `backend/coverage/html/index.html`
- Clover XML (para integraciones futuras, ej. Codecov): `backend/coverage/clover.xml`

Ambas rutas están en `backend/.gitignore` (`/coverage`) — no se commitean.

## Frontend (Angular / Karma + karma-coverage)

```bash
cd frontend
npm run test:coverage
```

Esto corre `ng test --watch=false --no-progress --browsers=ChromeHeadless
--code-coverage`, mismo comando que `test:ci` pero con el flag de
cobertura agregado. No cambia el resultado pass/fail de los specs
existentes.

### Dónde ver el reporte (local)

- HTML navegable: `frontend/coverage/factoring-frontend/index.html`

Nota: por ahora solo se genera el reporte HTML (el reporter por defecto
del builder de Angular). No se genera `lcov`/`clover` para frontend — si
en el futuro se quiere integrar con una herramienta externa (Codecov,
SonarQube, etc.) hay que agregar explícitamente `codeCoverageReporters`
en `frontend/angular.json` (no está configurado hoy).

`frontend/coverage/` está en `frontend/.gitignore` (`/coverage`) — no se
commitea.

## Dónde ver el reporte en CI

`.github/workflows/deploy.yml` genera y publica el reporte en cada corrida
de los jobs `test-backend` y `test-frontend`:

- **Backend**: artifact `backend-coverage-report` (contenido de
  `backend/coverage/html`).
- **Frontend**: artifact `frontend-coverage-report` (contenido de
  `frontend/coverage/factoring-frontend`).
- **Retención**: 14 días.

Para descargarlo: abrir el run del workflow en la pestaña **Actions** del
repo → scroll hasta la sección **Artifacts** al final de la página del
run → click en el nombre del artifact para bajarlo como `.zip`.

**Nota de seguridad (confirmado, no es un problema):** el reporte HTML de
PHPUnit incluye el código fuente completo (con syntax highlighting) de
cada archivo de `app/`, no solo números de líneas cubiertas. En un repo
privado esto ampliaría la exposición a cualquiera con acceso de lectura a
Actions pero sin acceso al código. Se confirmó vía `gh api
repos/ljgarciap/proseguirMonorepo` que **este repositorio es público**
(`visibility: public`) — el código ya es 100% legible por cualquiera sin
necesidad del reporte de cobertura, así que el artifact no agrega ninguna
exposición nueva. No se encontraron secretos hardcodeados en el código de
todas formas (todo pasa por `env()`/`config()`/`ConfiguracionService`).

El paso que genera cobertura (`Generate Coverage Report`) corre con
`continue-on-error: true`: si falla (por ejemplo, por out-of-memory
generando el HTML), **no** tumba el job ni bloquea `deploy-prod` /
`deploy-test`. Los pasos de gate existentes ("Run Tests (SQLite
In-Memory)" en backend, "Run Angular Headless Tests" en frontend) no se
tocaron y siguen siendo los únicos que deciden pass/fail del job.

## Cobertura ≠ calidad

Un porcentaje de cobertura alto **no** significa que el código esté bien
probado. En particular, los tests de `Feature` en Laravel ejercitan rutas
HTTP completas (controlador → request → response) y eso cuenta como
"líneas cubiertas" en el reporte, incluso si el test solo verifica un
`assertStatus(200)` sin validar el contenido, los efectos secundarios o
los casos de error de esa lógica. Es decir:

- La cobertura te dice **qué código se ejecutó** durante los tests.
- No te dice **si lo que se ejecutó fue verificado correctamente**.

Un archivo puede aparecer con 90% de cobertura y tener asserts triviales
o ausentes en las rutas críticas. Por eso el % de cobertura debe usarse
como **complemento** de la revisión manual de tests (qué assertions
tiene cada test, qué casos borde cubre), nunca como métrica única de
calidad.

## Estado: fase 1, solo informativo

Esta primera iteración **no** agrega ningún umbral mínimo de cobertura
que bloquee un merge o un deploy. El objetivo es únicamente tener
visibilidad del número real antes de decidir si vale la pena fijar un
umbral más adelante.

**Líneas base actuales** (referencia para comparar en el futuro):

- **Backend**: ~33.1% de cobertura general (57 tests, todos en verde).
- **Frontend**: ~37.73% de cobertura de statements (48/48 tests en
  verde). De los 28 archivos `*.component.ts` del proyecto, **22 (~79%)
  no tienen ningún `*.component.spec.ts` asociado** — es la mayor parte
  del "hueco" de cobertura del frontend hoy.

Estos números no son buenos ni malos en sí — son el punto de partida.
Cualquier cambio futuro que reduzca la cobertura de un módulo específico
vale la pena revisarlo con más atención, aunque el pipeline no lo
bloquee todavía.

## Decisiones pendientes (provisionales, a confirmar con Luis)

Sin respuesta explícita todavía, se adoptan estos defaults de bajo
riesgo — fácil de revisar más adelante si Luis prefiere lo contrario:

- **Herramienta externa (Codecov/SonarQube):** no se adopta por ahora.
  Se mantiene solo con artifacts de GitHub Actions (gratis, sin enviar
  código/datos de cobertura a terceros). Revisar si en algún momento se
  necesita ver tendencia histórica entre sprints.
- **Umbral mínimo de cobertura:** no se fija todavía. Se espera a tener
  varios sprints de datos reales (no solo esta primera medición) antes
  de decidir un % objetivo que bloquee el pipeline.
