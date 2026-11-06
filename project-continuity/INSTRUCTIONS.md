# INSTRUCTIONS — operating rules for any AI working on this project

Labels: **[VERIFIED]** = stated in project files or enforced by tests/CI, or an explicit instruction from the project owner in the working session; **[RECOMMENDED]** = inferred good practice, not mandatory.

## 0. Keep this package and the training centre current (owner's standing instruction)
**[VERIFIED — owner instruction, 2026-11-06]** At the end of every added capability - whenever a feature, fix, module, permission, route, scheduled job, migration, setting or decision is added or changed, update the files in `project-continuity/` **in the same PR**:
- `CONTEXT.md` (modules, architecture, data, config) — if behaviour/structure changed
- `HANDOFF-<date>.md` — add a new dated handoff when a session ends with meaningful change (keep the previous one; do not rewrite history)
- `DECISIONS.md` — one entry per non-trivial decision
- `TEST-LOG.md` — what was actually run and the result (never mark unrun checks as passed)
- `OPEN-QUESTIONS.md` — add/close questions
- `DEPLOYMENT.md` — if deploy/ops behaviour changes
- **مرکز آموزش** = the in-app training centre `/system-guide` (`resources/views/system-guide.blade.php`): add/update the Persian chapter or section for the feature, matching the existing style (callouts, feature cards, "how to use" steps).
- Root `README.md` is now a short pointer to this package; update it only if the entry points change.
A change is not "done" until both this package and the training centre are updated. This rule is also recorded in `.ai/rules/project-continuity.md`. (Owner instruction 2026-11-06; extended to the training centre the same day. «مرکز آموزش» was interpreted as `/system-guide` because no other training page exists — confirm if the owner meant something else.)

## 1. Read before editing
1. `CLAUDE.md` (Laravel Boost guidelines) and `.ai/rules/index.md` → every matching rule file (currently `general.md` and `project-continuity.md`, both `**/*`).
2. This package: `CONTEXT.md`, latest `HANDOFF-*.md`, `OPEN-QUESTIONS.md`.
3. Verify against the live repo: `git log --oneline -30`, `config/access-control.php`, `ls app/Models app/Filament/Resources`.

## 2. Hard project rules [VERIFIED]
- **No third-party CDNs, ever** (`.ai/rules/general.md`). The app serves users in Iran where CDNs are slow/filtered. Fonts via Vite font bundling (`@fonts`, `App\Filament\Support\LocalViteFontProvider`); JS libs via npm → bundle or copy into `public/vendor/<lib>/`. The smart-guide runtime and model live in `public/vendor/guide/` for this reason. Live third-party *data* (e.g. map tiles) is out of scope of the rule.
- **Do not change dependencies without approval** (CLAUDE.md). Exception already granted by the owner: `onnxruntime-web` 1.22.0 files vendored in `public/vendor/guide/` (owner attached the model file and implicitly approved the runtime).
- **No new documentation files unless asked** (CLAUDE.md). This package exists because the owner explicitly asked for it.
- **Do not create new base folders** without approval (CLAUDE.md).
- **No PDF library**; PDF = print-optimised Blade view + `window.print()` (see `*PrintController` pattern). Excel via `openspout`.
- **Never merge a PR unless the owner says «مرج کن»** — and only when CI is green on the exact head SHA for both PHP 8.4 and PHP 8.5. Use squash merge with `expectedHeadSha`.
- Never publish to production or run deploy actions; the owner presses «دیپلوی سرور» themselves.
- Do not push to any branch other than the designated working branch; do not open a PR unless asked (the owner usually asks per feature).
- Do not work around proxy/network denials (e.g. blocked model hosts): report instead.

## 3. Code conventions [VERIFIED: CLAUDE.md + README + code]
- PHP 8.4 target, Laravel 13, Filament v5.8, Livewire 4. Always curly braces; constructor property promotion; explicit return/param types; Enum cases in TitleCase; PHPDoc over inline comments; array-shape PHPDoc.
- Use `php artisan make:*` with `--no-interaction`. Create factories (+seeders where useful) with new models. Prefer named routes (`route()`).
- Singleton settings models expose `::current()` using `firstOrCreate(['id' => 1], [defaults])` — **defaults must be passed as the 2nd argument** (known recurring bug otherwise: fresh row reads `null`). Guide settings use `refresh()` when `wasRecentlyCreated`.
- Enum-cast columns: never `countBy('column')`; use `countBy(fn ($m) => $m->column->value)`.
- Domain services live in `app/Domain/<Module>/`; domain exceptions in `app/Exceptions/`; Filament resources/pages in `app/Filament/{Resources,Pages}`; jobs in `app/Jobs`; scheduled work registered in `routes/console.php`.
- Optional external APIs (Google, push, SMS, AI provider): best-effort — try/catch, `Log::warning`, let the main operation succeed.
- Production-code must not use factories/Faker (dev-only; not installed on the host). `App\Domain\System\DemoDataGenerator` writes records explicitly for this reason.
- Dates: Jalali display via `morilog/jalali` (`->toJalali(...)`); **model date columns can be nullable — guard before calling methods on them** (bug fixed in PR #250: `RoomBlock::ends_on`).
- Casted array attributes (e.g. `AiAssistantProfile::specialties`) must not be string-concatenated (bug fixed in PR #248).

## 4. Access control (RBAC) [VERIFIED]
- Source of truth `config/access-control.php`: `resources` (model modules → list/view/create/update/delete/…) and `pages` (single `{module}.access`, optional extra actions).
- **Every new Filament resource/page must be registered** there; `tests/Feature/AccessControlCoverageTest.php` fails otherwise.
- `Gate::before` gives **users with no role full access** and `admin` role full access (documented backward-compat). To test "forbidden", give the user a role with no permission (not "no role").
- Permissions rows are created by `access-control:sync`; the deploy button does **not** run it. Mitigations: the roles page calls `createMissingPermissions()` on open and a daily schedule `access-control-sync` (03:30 Tehran) exists.

## 5. Database & migrations [VERIFIED/RECOMMENDED]
- Production DB engine is **UNKNOWN to this snapshot** (`.env.example` defaults to sqlite; production likely MySQL/MariaDB — NEEDS VERIFICATION). Write portable migrations.
- **Make migrations idempotent** (`Schema::hasTable/hasColumn` guards): a half-run deploy once failed with `Table 'digital_project_stats' already exists` (PR #237).
- Never run migrations/seeders against production from here; the deploy cron runs `migrate --force`.
- No data-filling migrations on production data; use explicit, reversible admin tools (pattern: «دادهٔ آزمایشی» page, marker `[تست] `).

## 6. Tests & quality gates [VERIFIED]
- PHPUnit 12, feature tests first. Test env (`phpunit.xml`): sqlite `:memory:`, array cache/session, sync queue.
- After PHP edits run `vendor/bin/pint --dirty --format agent` (do not run `pint --test`).
- **The sandbox PHP is 8.3 and cannot boot the app** (`composer check-platform-reqs` fails: `spatie/laravel-activitylog` needs PHP ≥ 8.4). **CI (PHP 8.4 and 8.5 matrix) is the only real verifier.** Do not claim "tests pass" without a green CI on the head SHA.
- Date-dependent tests must pin the clock (`Carbon::setTestNow`) — a mid-month fix was needed in `AttendanceTest` (PR #247 branch).
- For PRs: fast loop = push, wait for the `PHP 8.4` and `PHP 8.5` checks, read failing job logs, fix, push.

## 7. Git / PR workflow [VERIFIED from session]
- Single designated working branch `claude/install-ui-ux-pro-max-tqw3sv`; one PR per feature; PR body in Persian ending with the Claude Code line + session URL; commit trailers `Co-Authored-By` + `Claude-Session` per the session's attribution reminder.
- After a squash-merge the remote branch still holds the old commits. The sandbox auto-mode **denies `git reset --hard` + force push**. Working pattern: `git fetch origin main && git checkout -B <branch> origin/main`, commit, `git merge -s ours origin/<branch>`, normal `git push`. **Caution:** this drops anything on the remote branch that is *not yet merged* (an open PR's commits were once lost this way and restored by cherry-pick) — use only when the remote branch holds merged work, otherwise cherry-pick/merge properly.
- Subscribe to PR activity after opening a PR; stop when merged.

## 8. Security & privacy [VERIFIED]
- Smart-guide: camera frames never leave the phone; only anonymous events (`guide_events`) are stored. Never add server-side image upload for recognition.
- Never print secrets; `.env` values must not appear in docs/logs. The «گزارش خطاهای سرور» page masks keys/tokens and is admin-only.
- AI provider keys are stored in the DB (`ai_providers`), managed in the panel; never hard-code.
- Customer PWA uses its own guard (`customer`), separate from staff `auth`.

## 9. UX conventions [VERIFIED/RECOMMENDED]
- All user-facing text Persian, RTL, Jalali dates, Persian digits via `Number::toPersian` where the surrounding code does.
- Communication with the owner is in Persian; keep explanations short, state honestly what was not verified (pages not seen in the real panel, tests not run locally).
- Accessibility basics for new public UI: visible focus, ≥44px targets, `prefers-reduced-motion`.

## 10. Things not to change casually
- `public/build/` is committed (assets are built elsewhere; host has no Node). Do not expect `npm run build` to be possible on the host. New public JS that must work without a build step goes in `public/` as plain ES modules (guide engine does this).
- `routes/console.php` schedules use `Schedule::call(Artisan::call…)` — **never `Schedule::command()`** (host has `proc_open` disabled; guarded by `ScheduleHostCompatibilityTest`).
- `customer-app-sw.js` (scope `/app/`) and `sw.js` (staff panel) are separate on purpose; cache names must not collide.
- `config/access-control.php` ordering/labels drive the roles screen.
- `public/vendor/guide/dinov2-small-q8.onnx` + `guide-engine.js` must stay in sync: embeddings are only comparable when produced by the same model/preprocessing. Changing either requires re-processing all reference images («همهٔ تصاویر» in «پردازش تصاویر مرجع»).
