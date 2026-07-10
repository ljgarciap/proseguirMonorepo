# Task Breakdown: Habilitar medición de cobertura de tests (backend + frontend)

**Date**: 2026-07-03
**Author**: PM
**Status**: Planning artifact — awaiting Luis's go-ahead to implement
**Design**: `docs/designs/coverage-medicion-tests.md`
**Spec**: `docs/specs/coverage-medicion-tests.md`

> Nothing in this document has been executed. No project code was modified to produce this
> breakdown. This is the task list PM would hand to the team once Luis approves starting.

---

## Summary

| Bucket | Tasks | Hours | Role(s) |
|---|---|---|---|
| Backend | 2 | 3.5h | DevOps (1.75h) · Backend Dev (1.75h) |
| Frontend | 2 | 2h | Frontend Dev (2h) |
| CI integration | 3 | 3.5h | DevOps (3.5h) |
| Documentation | 1 | 1.5h | Tech Writer (1.5h) |
| **Total** | **8** | **~10.5h** | |

**Hours by role**: DevOps 5.25h · Backend Dev 1.75h · Frontend Dev 2h · Tech Writer 1.5h

---

## Backend

### Task B1: Multi-stage Dockerfile split + pin production target in compose
**Agent**: DevOps
**Depends on**: none — can start immediately
**Acceptance**:
- `backend/Dockerfile` has three stages: `base` (all current steps, unchanged), `production` (default
  target, byte-for-byte same runtime behavior as today), `local-coverage` (adds `pecl install pcov &&
  docker-php-ext-enable pcov` on top of `base`).
- `docker-compose.yml` explicitly sets `target: production` on both the `backend` and `queue` service
  `build:` blocks.
- `docker compose up -d --build` produces an image with no PCOV/Xdebug present (verify via
  `php -m | grep -i pcov` returns nothing inside the running `backend` container).
- `docker build --target=local-coverage backend/` succeeds and the resulting image has PCOV enabled.
**Files**: `backend/Dockerfile`, `docker-compose.yml`

### Task B2: composer script, phpunit review, .gitignore, local coverage verification
**Agent**: Backend Dev
**Depends on**: B1 (needs the `local-coverage` image to run the verification step)
**Acceptance**:
- `backend/composer.json` has a `test:coverage` script that runs
  `php artisan test --coverage-clover=coverage/clover.xml --coverage-html=coverage/html`.
- Confirmed and noted (no diff needed) that `backend/phpunit.xml`'s existing `<source><include>` block
  already supports `--coverage` under PHPUnit 10+ — no changes required.
- `backend/.gitignore` adds `/coverage`.
- Local verification: build/run the `local-coverage` target, execute `composer test:coverage` (or
  `php artisan test --coverage`) inside the container, confirm no "no coverage driver available" error,
  confirm the same 60 tests pass as before this change (no pass/fail regressions).
**Files**: `backend/composer.json`, `backend/.gitignore` (no change expected to `backend/phpunit.xml`,
but review/confirmation is part of this task's acceptance)

---

## Frontend

### Task F1: package.json script + angular.json codeCoverageExclude + .gitignore
**Agent**: Frontend Dev
**Depends on**: none — independent of backend, can start immediately in parallel with B1/B2
**Acceptance**:
- `frontend/package.json` has a `test:coverage` script:
  `ng test --watch=false --no-progress --browsers=ChromeHeadless --code-coverage`.
- `frontend/angular.json`, inside `architect.test.options`, adds
  `"codeCoverageExclude": ["src/environments/**", "src/main.ts"]`.
- `frontend/.gitignore` adds `/coverage`.
**Files**: `frontend/package.json`, `frontend/angular.json`, `frontend/.gitignore`

### Task F2: Local frontend coverage verification
**Agent**: Frontend Dev
**Depends on**: F1
**Acceptance**:
- Running `npm run test:coverage` locally generates HTML + lcov reports under
  `frontend/coverage/factoring-frontend/`.
- All existing specs report the same pass/fail result as `npm run test:ci` today (no regressions from
  enabling `--code-coverage`).
- Coverage % is visibly reported in the console output (confirms `karma-coverage` is actually wired in,
  not silently skipped).
**Files**: none expected (verification-only task; may produce a fixup commit to F1's files if something
doesn't wire up as designed)

---

## CI Integration

### Task C1: test-backend CI job — coverage step + artifact upload
**Agent**: DevOps
**Depends on**: none (CI installs PHP directly on the runner via `setup-php`, independent of the Docker
image work in B1/B2) — can run in parallel with the Backend bucket
**Acceptance**:
- `.github/workflows/deploy.yml`, `test-backend` job: `setup-php@v2` step adds `coverage: pcov`.
- Existing "Run Tests (SQLite In-Memory)" step is untouched, byte-for-byte — this remains the deploy
  gate.
- New `Generate Coverage Report` step added **after** the gate step, with `continue-on-error: true`,
  running `php artisan test --coverage-clover=coverage/clover.xml --coverage-html=coverage/html`.
- New `Upload Backend Coverage Report` step (`if: always()`, `actions/upload-artifact@v4`,
  `retention-days: 14`, `if-no-files-found: warn`) uploads `backend/coverage/html`.
- Confirm on a throwaway branch/PR that the job still passes/fails on the existing gate exactly as
  before, and the coverage artifact appears in the run's Artifacts section.
**Files**: `.github/workflows/deploy.yml`

### Task C2: test-frontend CI job — coverage step + artifact upload
**Agent**: DevOps
**Depends on**: F1 (needs `frontend/package.json`'s `test:coverage` script to exist)
**Acceptance**:
- `.github/workflows/deploy.yml`, `test-frontend` job: existing "Run Angular Headless Tests" step
  untouched.
- New `Generate Coverage Report` step (`continue-on-error: true`) runs `npm run test:coverage`.
- New `Upload Frontend Coverage Report` step (`if: always()`, `actions/upload-artifact@v4`,
  `retention-days: 14`, `if-no-files-found: warn`) uploads `frontend/coverage/factoring-frontend`.
**Files**: `.github/workflows/deploy.yml`

### Task C3: End-to-end CI validation
**Agent**: DevOps
**Depends on**: C1 and C2 both merged
**Acceptance**:
- On a throwaway branch, confirm both `test-backend` and `test-frontend` gate steps behave identically
  to pre-change (same pass/fail outcome, same duration order).
- Confirm both coverage artifacts are generated and downloadable from the workflow run.
- Simulate a coverage-step failure (e.g. temporarily break the coverage command) and confirm the job
  still reports success on the gate step and does not block `deploy-prod`/`deploy-test` — i.e.
  `continue-on-error: true` behaves as designed (spec edge case 5).
**Files**: none expected (validation-only; throwaway branch is discarded after)

---

## Documentation

### Task D1: docs/testing/coverage.md
**Agent**: Tech Writer
**Depends on**: soft dependency on C3 (needs real report output/paths to describe and screenshot
accurately) — can start drafting structure/outline in parallel with everything else, finalize last
**Acceptance**:
- New `docs/testing/coverage.md` explains how to read the coverage report locally (backend: build
  `local-coverage` target or run `composer test:coverage`; frontend: `npm run test:coverage`) and in CI
  (where to find the artifact on a workflow run, retention window).
- Explicitly documents the "coverage ≠ quality" caveat from spec edge case 4 (Feature tests can inflate
  controller coverage without meaningful assertions).
- Notes that this phase is informational-only — no threshold gate exists yet.
**Files**: `docs/testing/coverage.md` (new)

---

## Execution order

```
Wave 1 (parallel, start immediately):
  B1 (DevOps)         Dockerfile multi-stage + compose pin
  F1 (Frontend Dev)   package.json/angular.json/.gitignore
  C1 (DevOps)         test-backend CI step (no dependency on B1)
  D1 (Tech Writer)    start drafting doc outline

Wave 2:
  B2 (Backend Dev)    depends on B1
  F2 (Frontend Dev)   depends on F1
  C2 (DevOps)         depends on F1

Wave 3:
  C3 (DevOps)         depends on C1 + C2

Wave 4:
  D1 (Tech Writer)    finalize, depends on C3 for real output
```

Note: B1, C1, and C2/C3 are all assigned to DevOps. If only one DevOps engineer is available, these
serialize in practice (~5.25h sequential for that role) even though they have no *technical* dependency
on each other in Wave 1 — real wall-clock parallelism across roles caps at whichever role has the
longest queue (DevOps, 5.25h), not the 10.5h total.

---

## Recommendation

At ~10.5h total (mostly informational tooling, no gate, no new external cost), this is a low-risk,
low-effort investment that unblocks the far more valuable follow-up (deciding a real coverage threshold)
once a baseline exists — worth doing in the next sprint, but not urgent enough to interrupt
higher-priority work to start today.
