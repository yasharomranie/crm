# DEPLOYMENT & operations (host-specific, non-obvious)

Labels: CONFIRMED (code/session), UNKNOWN, NEEDS VERIFICATION. No secrets here.

## Environment facts
- **Shared cPanel host, no terminal/SSH.** `proc_open` is disabled for PHP (CONFIRMED: comments in `routes/console.php` and `DeploymentTriggerService`; guarded by `tests/Feature/ScheduleHostCompatibilityTest.php`). Consequence: never `Schedule::command()`, never Symfony Process from PHP.
- **No persistent queue worker.** `QUEUE_CONNECTION=database`; the every-minute schedule entry `queue:work --stop-when-empty --max-time=50` drains it. A cron job running `schedule:run` every minute therefore must exist (CONFIRMED by code comments; the cron entry itself is outside the repo — NEEDS VERIFICATION of exact command).
- **Assets are not built on the host.** `public/build/` is committed; `package.json` build runs elsewhere. New public JS that must work without a build lives as plain files in `public/` (e.g. `public/guide/guide-engine.js`, `public/vendor/guide/*`).
- **HTTPS** is required for camera access (smart guide). Production domain appears to be `farsiwp.ir` (from an owner screenshot; NEEDS VERIFICATION).
- **PHP version on host:** UNKNOWN (code requires ≥ 8.4 via `spatie/laravel-activitylog`).
- **DB engine on host:** UNKNOWN (likely MySQL/MariaDB).

## How a deploy works (CONFIRMED from `app/Domain/Deployment/DeploymentTriggerService.php`)
1. Admin presses «دیپلوی سرور» in the panel (page in «زیرساخت سازمانی»). The app only **writes a flag file** (`deploy.trigger` on the `local` disk).
2. A permanent every-minute **cPanel cron job outside PHP** sees the flag and runs `git pull`, `php artisan migrate --force`, `optimize:clear`, `optimize`, appends raw output to `deploy_log.txt`, then removes the flag.
3. The panel shows the last log and approximate last deploy time (log file mtime).
- It does **not** run `access-control:sync`, `npm`, or `composer install`. New Composer dependencies would not be installed by this flow — NEEDS VERIFICATION how `vendor/` is managed on the host (it is not committed, INFERRED from `.gitignore` conventions; confirm).
- Permission rows for new modules appear after the roles page is opened (it calls `createMissingPermissions()`), or at 03:30 Tehran via the `access-control-sync` schedule.
- A failed migration aborts the deploy; make migrations idempotent (see PR #237).

## Production diagnostics without a terminal
- Panel page **«گزارش خطاهای سرور»** (admin): last 20 entries of the newest file in `storage/logs`; secrets masked. First stop for any 500.
- cPanel → File Manager → `storage/logs/laravel.log`, and cPanel → Metrics → Errors (PHP/Apache errors the app can't log).
- AI assistant failures also go to the activity log (`ai_assistant` / `unexpected_error`).

## Smart-guide hosting notes
- `public/vendor/guide/.htaccess` sets MIME types (`.mjs`, `.wasm`, `.onnx`) and a 30-day cache; requires `mod_mime`/`mod_headers` — NEEDS VERIFICATION on the host.
- Repo grew ~35 MB (WASM 11 MB + model 23 MB). Large video uploads are limited by PHP/host limits (form allows up to 200 MB; real limit UNKNOWN) — alternative is a video URL field.
- Public disk URLs (`/storage/...`) require the storage symlink — NEEDS VERIFICATION. Enrollment now uses path-only image URLs to avoid `APP_URL` mismatches.

## CI (GitHub Actions) — CONFIRMED
`.github/workflows/tests.yml`: matrix PHP **8.4 and 8.5**, sqlite, imagick/tesseract extras for OCR tests; nightly schedule. Other workflows are Laravel boilerplate (`dependabot-auto-merge`, `issues`, `pull-requests`). History: Actions minutes were exhausted on the private repo (jobs failing in ~2 s with no logs); the owner made the repository **public** to get free minutes (current visibility UNKNOWN — see OPEN-QUESTIONS).

## Release/rollback
No documented rollback. Practical path: revert the merge on `main`, then press «دیپلوی سرور». Migrations are not auto-reverted — UNKNOWN policy; never `migrate:rollback` blindly on production.
