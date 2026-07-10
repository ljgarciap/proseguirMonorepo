# Design: Habilitar medición de cobertura de tests (backend + frontend)

**Date**: 2026-07-03
**Author**: Arquitecto
**Status**: Ready for PM breakdown (pending Luis's approval on escalation items below)
**Spec**: `docs/specs/coverage-medicion-tests.md`
**Project**: Proseguir Factoring

## 0. Grounding — what I verified before designing

- `backend/phpunit.xml` already uses PHPUnit 10+ `<source><include><directory>app</directory></include></source>`
  syntax — this is exactly the config the `--coverage` flag needs. **No changes required here** for phase 1.
- `backend/Dockerfile` (`php:8.4-fpm`) has no PCOV/Xdebug. Confirmed via `docker-compose.yml`: this
  **same Dockerfile is used for both `backend` and `queue` services**, and `docker-compose.yml` is the
  exact file `deploy.yml` runs via `docker compose up -d --build` for **both** `master` (prod) and `test`
  deploys (only the `.env` differs). **This Dockerfile is the production image.** Any coverage driver
  added here must not become part of what ships to prod/test servers by default.
- `.github/workflows/deploy.yml` `test-backend` job does **not** use Docker at all — it installs PHP
  directly on the `ubuntu-latest` runner via `shivammathur/setup-php@v2`. This means CI coverage for the
  backend is decoupled from the Docker image question entirely: the action can install PCOV on the
  runner with a one-line parameter change, with zero effect on the deployed image.
- `frontend/package.json` has `karma-coverage: ~2.2.0` as a devDependency but it's never invoked.
  `frontend/angular.json`'s `test` architect target uses the standard
  `@angular-devkit/build-angular:karma` builder, which has **built-in, native support for
  `--code-coverage`** using whatever coverage package is present in `node_modules` — no custom
  `karma.conf.js` is required. This simplifies the spec's proposed solution.
- No `frontend/karma.conf.js` exists today (confirmed).
- **Frontend baseline, recounted**: `frontend/src/app/components/**/*.component.ts` (excluding specs) =
  **28 components** (spec said 29 — off by one, not material). Of those, **6 have a matching
  `*.component.spec.ts`**: `notificaciones`, `conciliacion-susuerte-history`, `asignaciones`, `visitas`,
  `clientes`, `destinatarios`. **22/28 (~79%) have zero test coverage**, including `upload.component.ts`,
  `dashboard.component.ts`, `configuraciones.component.ts`, and `auth/login`. This confirms the spec's
  concern: **any gate today would break the pipeline on the first push.**
- Neither `backend/.gitignore` nor `frontend/.gitignore` currently ignores a `coverage/` directory —
  needs to be added so reports don't get committed by accident.

## 1. Decisions on the Analyst's open questions

### 1.1 PCOV vs Xdebug for backend → **PCOV**
Nothing in the codebase or CLAUDE.md indicates an active need for remote step-debugging today (no
`launch.json`/`xdebug.ini` references found anywhere in the repo). PCOV exists specifically for coverage
collection, has near-zero overhead when not actively collecting (unlike Xdebug, which measurably slows
*every* request even when idle), and keeps this initiative scoped to "measurement only" as the spec
intends. **If Luis wants interactive breakpoint debugging in the IDE later, that's a separate, unrelated
decision** — it would mean adding Xdebug to a dev-only image, not reopening this one.

### 1.2 Informational-only vs threshold gate → **Informational-only in this phase**
Confirmed by the corrected baseline above: frontend sits at ~21% component-spec coverage. A gate today
fails immediately and blocks all deploys. Decision: ship measurement only, publish the first real
baseline, then **revisit a gate in a follow-up initiative** once the team has seen at least one sprint of
numbers. I'm not picking a future threshold now — that's a call for Luis once real data exists.

### 1.3 Where the report lives → **GitHub Actions artifacts (free, zero new infra)**
No external service. Each `test-backend`/`test-frontend` CI run uploads its HTML+Clover / HTML+lcov
report as a workflow artifact (14-day retention), downloadable by anyone with repo access. This fully
satisfies the acceptance criteria at zero cost.

**Escalation — not deciding this myself**: Codecov/Coveralls/SonarQube (trend history, PR diff-coverage
comments) is explicitly Out of Scope in the spec, and I'm keeping it that way. If Luis wants it sooner,
it needs his explicit sign-off first — it means sending source/coverage data to a third-party SaaS and
likely a paid plan for a private repo. **Flagging, not deciding.**

## 2. Components affected

| Component | Change |
|---|---|
| `backend/Dockerfile` | Convert to multi-stage: `base` → `production` (default, unchanged behavior) → new `local-coverage` stage (adds PCOV, opt-in only) |
| `docker-compose.yml` | Pin `target: production` explicitly on `backend` and `queue` services, so a future stage reorder can never silently ship PCOV to prod |
| `backend/composer.json` | New `test:coverage` script |
| `backend/phpunit.xml` | No change (already PHPUnit 10+ `<source>` syntax) |
| `backend/.gitignore` | Add `/coverage` |
| `frontend/package.json` | New `test:coverage` script (`--code-coverage` flag, no new deps needed — `karma-coverage` already installed) |
| `frontend/angular.json` | Add `codeCoverageExclude` to the `test` architect options |
| `frontend/.gitignore` | Add `/coverage` |
| `.github/workflows/deploy.yml` | `test-backend`: add `coverage: pcov` to `setup-php`, new isolated coverage step + artifact upload. `test-frontend`: new isolated coverage step + artifact upload. **Existing gating steps in both jobs are untouched.** |
| `docs/testing/coverage.md` (new, Tech Writer) | How to read reports locally + in CI, and the "coverage ≠ quality" caveat from spec edge case 4 |

No data model or API contract changes — this is tooling/CI only.

## 3. Design detail

### 3.1 Backend Docker (local coverage capability)
```dockerfile
FROM php:8.4-fpm AS base
# ... all current steps unchanged (system deps, php extensions, composer, node/npm, permissions) ...

FROM base AS production
WORKDIR /var/www/html
EXPOSE 9000
CMD ["php-fpm"]

FROM base AS local-coverage
RUN pecl install pcov && docker-php-ext-enable pcov
WORKDIR /var/www/html
EXPOSE 9000
CMD ["php-fpm"]
```
`docker-compose.yml` gets `target: production` added to the `backend` and `queue` service `build:` blocks
— this is what guarantees the deployed image never silently picks up the coverage stage. Luis runs
coverage locally on demand via a throwaway build (`docker build --target=local-coverage ...` +
`docker run`, or a small `docker-compose.coverage.yml` override) — not part of the default
`docker compose up -d --build` flow. **Zero size/build-time impact on the production path** since Docker
only builds the stages that are actually targeted.

### 3.2 Backend CI (deploy.yml, test-backend job)
Keep the existing "Run Tests (SQLite In-Memory)" step **byte-for-byte identical** (the deploy gate).
Add PCOV to the `setup-php` step (`coverage: pcov`) and a **new, separate** step after the gate:
```yaml
- name: Generate Coverage Report
  continue-on-error: true
  run: |
    cd backend
    php artisan test --coverage-clover=coverage/clover.xml --coverage-html=coverage/html
- name: Upload Backend Coverage Report
  if: always()
  uses: actions/upload-artifact@v4
  with:
    name: backend-coverage-report
    path: backend/coverage/html
    retention-days: 14
    if-no-files-found: warn
```
`continue-on-error: true` directly implements spec edge case 5 — a coverage-generation failure (e.g. OOM
building the HTML report) cannot fail `test-backend`, so it can never block `deploy-prod`/`deploy-test`.

### 3.3 Frontend (no new karma.conf.js needed)
`package.json`:
```json
"test:coverage": "ng test --watch=false --no-progress --browsers=ChromeHeadless --code-coverage"
```
`angular.json`, inside `architect.test.options`:
```json
"codeCoverageExclude": ["src/environments/**", "src/main.ts"]
```
The Angular karma builder auto-wires `karma-coverage` (already a devDependency) when `--code-coverage`
is passed, writing HTML + lcov to `frontend/coverage/factoring-frontend/` by default — matching the
acceptance criterion path.

### 3.4 Frontend CI (deploy.yml, test-frontend job)
Same isolation pattern as backend — existing "Run Angular Headless Tests" step untouched, new step added:
```yaml
- name: Generate Coverage Report
  continue-on-error: true
  run: |
    cd frontend
    npm run test:coverage
- name: Upload Frontend Coverage Report
  if: always()
  uses: actions/upload-artifact@v4
  with:
    name: frontend-coverage-report
    path: frontend/coverage/factoring-frontend
    retention-days: 14
    if-no-files-found: warn
```

## 4. Dependencies between tasks
1. Backend Dockerfile/compose change is independent of everything else — can start immediately.
2. Backend CI step depends on nothing (doesn't need the Dockerfile change — CI installs PHP directly on
   the runner). Can be done in parallel with #1.
3. Frontend package.json/angular.json change is independent — can start immediately.
4. Frontend CI step depends on #3 being merged (needs `test:coverage` script to exist).
5. Documentation depends on #1–#4 being functional, so Tech Writer can screenshot/describe the real
   output — can start drafting structure in parallel, finalize last.

## 5. Risks and mitigations

| Risk | Mitigation |
|---|---|
| `pecl install pcov` fails on `php:8.4-fpm` (missing build toolchain) | Verify locally before merging; official PHP images ship `pecl`/`phpize` already; budget contingency time in task |
| Running the test suite twice in CI (gate + coverage) roughly doubles backend/frontend CI wall-clock | Suite is small today (60 backend tests, 6 frontend specs) — low absolute cost; revisit if suite grows significantly or once a threshold gate replaces the duplicate run |
| Exposing 22/28 (~79%) untested frontend components may read as "things just got worse" | Frame proactively to team: this is the existing (pre-existing) state made visible, not a regression introduced by this work |
| Docker stage order drift later accidentally ships PCOV to prod | `docker-compose.yml` explicitly pins `target: production` — immune to stage reordering in the Dockerfile |
| HTML coverage artifact size/retention cost | `retention-days: 14`, GitHub artifact storage is free within normal usage on standard plans |
| Pre-existing PHP version mismatch: `composer.json` requires `^8.2`, but `Dockerfile`/CI both run 8.4 | Unrelated to this initiative, not blocking — noting only, not fixing here |

## 6. Effort estimate (for PM to chunk into 2–4h tasks)

| Bucket | Tasks | Hours |
|---|---|---|
| **Backend** | Multi-stage Dockerfile + `local-coverage` stage (1.5h) · `docker-compose.yml` target pin (0.25h) · `composer.json` script (0.25h) · phpunit.xml review, confirm no change needed (0.5h) · local verification: build, run `--coverage` in container, confirm 60 tests unchanged (1h) · `.gitignore` (0.1h) | **~3.5h** |
| **Frontend** | `package.json` script (0.25h) · `angular.json` `codeCoverageExclude` (0.5h) · local verification: confirm report lands in `coverage/factoring-frontend/`, specs still pass (1h) · `.gitignore` (0.1h) | **~2h** |
| **CI integration** | `test-backend` job changes: setup-php `coverage: pcov` + new step + artifact upload (1h) · `test-frontend` job changes: new step + artifact upload (1h) · end-to-end validation on a throwaway branch: confirm gate behavior identical, artifacts downloadable, simulated coverage-step failure doesn't fail the job (1.5h) | **~3.5h** |
| **Documentation** (Tech Writer, parallel) | `docs/testing/coverage.md`: how to read reports locally + in CI, "coverage ≠ quality" caveat (spec edge case 4) | **~1.5h** |
| **Total** | | **~10.5h** |

## 7. Escalations to Luis (not decided by me)
1. **Codecov/Coveralls/SonarQube** — external SaaS for trend/history dashboards. Explicit sign-off
   required if wanted sooner than "Phase 2" (cost + third-party code/data sharing). I'm not recommending
   it now; artifacts-only is the phase-1 design.
2. **Xdebug for local step-debugging** — I decided PCOV-only based on no evidence of an active debugging
   need. If that assumption is wrong, tell me and it becomes a separate, small addition (Xdebug in a
   dev-only image), not a rework of this design.
3. **Timing/value of a future coverage threshold gate** — I'm deferring the actual number/date to Luis
   once a real baseline exists post-merge; not blocking this phase.
