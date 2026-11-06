# TEST-LOG — what was actually run

Rule: record only what really ran; never mark unrun checks as passed. Append new entries at the bottom of each section.

## Environment (sandbox)
Linux 6.18, **PHP 8.3.6**, Node v22.22.0, Composer 2.8.12. The Laravel app **cannot boot here**; the PHPUnit suite is **not runnable** locally.

## Commands executed in this inspection (2026-11-06)
| Command | Result | What it proves |
|---|---|---|
| `composer check-platform-reqs` | **FAILED** for `php`: installed 8.3.6; `spatie/laravel-activitylog` requires `^8.4` (all extensions OK) | The sandbox cannot satisfy the lockfile; explains why tests cannot run here |
| `php artisan --version` | **FAILED** (`In Request.php line 117 …`, output truncated) | App cannot boot on PHP 8.3 here (hypothesis: platform mismatch); not investigated further because the cause is already known |
| `git status`, `git log`, `git rev-list --count origin/main` (=256) | OK | Repo state as documented |

## Checks run earlier in the session (from visible history)
| Check | Result | Notes |
|---|---|---|
| `vendor/bin/pint --dirty --format agent` before each commit | passed/fixed (all PRs #241–#251) | style only |
| `node --check` / `node --input-type=module --check` on `public/guide/guide-engine.js`, `public/customer-app-sw.js`, scanner/enroll inline modules | passed | syntax only, no execution of third-party code (auto-mode denied running downloaded npm code) |
| `php -l` on new PHP/test files | passed | syntax only |

## CI evidence (GitHub Actions, matrix PHP 8.4 + 8.5) — the real verifier
| PR / head | 8.4 | 8.5 | Notes |
|---|---|---|---|
| #241 `77bdf9a` | PASS | PASS | after fixing 4 initial failures (null defaults on fresh rows; `->each->delete()` on relation) |
| #242 `7f41b19`… | PASS | PASS | first run: 8.5 FAIL on `GuideModuleTest` (`assertSame` 0.0 vs 0 after JSON) → `assertEquals` |
| #243 `34dde65` | PASS | PASS | |
| #244 `87cf735` | PASS | PASS | |
| #245 `3570faa` | PASS | PASS | |
| #246 `82abb5f` | PASS | PASS | |
| #247 first run | PASS (not seen) | **FAIL** `AttendanceTest › monthly summary…` (480 vs 880) | date-dependent test (run on the 2nd of the month); fixed with pinned clock → `a550750` PASS/PASS. Same run: 2,186 passed, 1 failed (~2,187 tests) |
| #248 `c60c4f4` | PASS | PASS | |
| #249 `32b870b` | PASS | PASS | |
| #250 `60d4a51` | PASS | PASS | |
| #251 `f339a6f` | PASS | PASS | CI re-run needed after this package is added |

(Head SHAs abbreviated from session notifications; verify in GitHub.)

## Not run / gaps
- **No local test execution** (PHP version). No browser/e2e tests. No tests of the real host (cron, deploy script, `.htaccess`, storage symlink).
- **ONNX model execution never ran in CI or the sandbox**; on-device recognition is verified only by the owner's phone test (owner reported it recognised an object). No numeric accuracy measurement.
- JS (scanner, enroll, accuracy, prefetch, service worker) has **no automated tests**; Blade pages are only asserted to contain certain strings.
- Visual checks of pages were never done by the AI.
- Coverage gaps worth closing: guide scanner logic (rank/margin) as a JS unit test; deploy flow; dashboard widget permissions.

## Procedure for recording future results
1. After pushing, read the `PHP 8.4` / `PHP 8.5` check runs for the exact head SHA.
2. Add a row under "CI evidence": PR, short SHA, result per PHP version, failing test names + one-line cause + fix.
3. For anything the owner verified manually, note it as "owner-verified (screenshot/message)", with date.
4. Never record "PASS" for a check you didn't see complete.
