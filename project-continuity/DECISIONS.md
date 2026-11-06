# DECISIONS — chronological register

Dates before 2026-10-28 are not recoverable from this snapshot (**UNKNOWN**); order is by PR number where available. Status: CONFIRMED / INFERRED / NEEDS CONFIRMATION / SUPERSEDED.

| # | Date | Decision | Context / reason | Status | Affected | Revisit? |
|---|------|----------|------------------|--------|----------|----------|
| 1 | early (UNKNOWN) | Laravel + Filament admin, RTL Persian, Jalali dates | Product for Iranian group | CONFIRMED | whole app | no |
| 2 | early | **No third-party CDNs; self-host fonts/libs** | Users in Iran; CDNs slow/filtered | CONFIRMED (`.ai/rules/general.md`, #222 guard test) | views, vite, `public/vendor` | no |
| 3 | early | **No PDF library**; print-optimised Blade + browser print; Excel via openspout | Host constraints/simplicity | CONFIRMED (README + code) | `*PrintController`, `TableExports` | maybe |
| 4 | early | Dynamic RBAC from `config/access-control.php`; `Gate::before`: no-role users and `admin` get everything | Backward compatibility | CONFIRMED | `AppServiceProvider`, policies | **yes** — "no role = full access" is risky for staff rollout |
| 5 | early | Queue = database, drained by the every-minute schedule (`queue:work --stop-when-empty`) | No worker daemon on host | CONFIRMED | `routes/console.php` | no |
| 6 | early | Schedules use `Schedule::call(Artisan::call…)`, never `Schedule::command()` | `proc_open` disabled | CONFIRMED + test | `routes/console.php` | no |
| 7 | early | Deploy via panel button writing a flag file; external cron performs `git pull`/`migrate --force`/cache | No terminal on host | CONFIRMED (`DeploymentTriggerService`) | deployment | no |
| 8 | early | `public/build` committed | No Node on host | CONFIRMED | `public/build` | maybe |
| 9 | workflow | One PR per feature; **merge only on the owner's «مرج کن»** and only with green CI on PHP 8.4 + 8.5 | Owner control | CONFIRMED (owner instructions) | process | no |
| 10 | workflow | Squash merge with `expectedHeadSha` | Safe, linear history | CONFIRMED (practice) | GitHub | no |
| 11 | #237 (≈2026-10) | Migrations must be idempotent (`hasTable/hasColumn`) | Prod deploy aborted on existing table | CONFIRMED | new migrations | no |
| 12 | #240 | Auto-create missing permission rows when the roles page opens + daily `access-control-sync` | Deploy button doesn't run sync; role screen had no checkboxes | CONFIRMED | `ManageRoles`, `routes/console.php` | no |
| 13 | 2026-10-28 | **Smart guide: recognition runs in the browser** (onnxruntime-web WASM + DINOv2-small int8), not on the server | Zero token/API cost, privacy (frames never leave phone), cPanel can't run ML | CONFIRMED (owner approved pilot; attached the model) | `public/guide`, `public/vendor/guide` | after accuracy test |
| 14 | 2026-10-28 | Embedding = concat(L2(CLS), L2(mean patches)); cosine; match needs `best ≥ 0.700` and gap ≥ 0.030, two consecutive frames | Reduce false accepts between similar objects | CONFIRMED (code) — values are defaults to tune | `guide-engine.js`, `GuideSetting` | **yes** after real photos |
| 15 | 2026-10-28 | Guide is **off by default**; staff can preview while off | Experimental, unmeasured accuracy | CONFIRMED | `GuideSetting`, `GuideController` | when accurate |
| 16 | 2026-10-28 | Vendor `onnxruntime-web` 1.22.0 + model files into `public/vendor/guide` | No CDN rule; no build on host | CONFIRMED (owner implicitly approved when attaching the model) | repo size +35 MB | maybe |
| 17 | 2026-10-28 | Only anonymous events stored (`guide_events`); no images server-side | Privacy | CONFIRMED | `GuideController::event` | no |
| 18 | #244 | Videos default to published | Unpublished default hid videos from the owner | CONFIRMED | `VideosRelationManager` | no |
| 19 | #246 | Prefetch the model on `/app` open (skip Save-Data/2G; re-check every 3 days) | Avoid first-scan wait; cave has poor signal | CONFIRMED (owner asked) | `customer-app.blade.php`, `guide-engine.js` | after device tests |
| 20 | 2026-11-02 | Add admin-only **«گزارش خطاهای سرور»** page (tail of laravel.log, masked) | No terminal; third diagnosis blocked by missing logs | CONFIRMED (owner: «لاگ خوان را بساز») | `ServerErrorLog`, `ServerLogReader` | no |
| 21 | 2026-11-02 | Assistant: catch any `Throwable` in chat send, log + show cause to admins | Generic Filament "page load error" hid root cause | CONFIRMED | `AssistantFailureReporter` | no |
| 22 | 2026-11-02 | Sample data via an **admin button**, marker `[تست] `, additive only, no factories/Faker, not via migration/deploy | Real prod data must not be touched; migration data-fill is irreversible; Faker not installed in prod | CONFIRMED (owner chose hospitality + CRM) | `DemoDataGenerator`, `TestDataTools` | no |
| 23 | 2026-11-06 | Keep this `project-continuity/` package current in every PR; rule recorded in `.ai/rules/project-continuity.md` | Owner instruction | CONFIRMED | all PRs | no |
| 24 | 2026-11-06 | Phase-1 staff rollout: limited roles («پذیرش», «سرپرست پذیرش»), hotel basics only, train on sample data | Owner wants to start safely | NEEDS CONFIRMATION (advice given; owner hasn't picked deliverables) | `config/access-control.php`, roles | yes |
| 25 | 2026-11-02 | Git: use `merge -s ours` to publish a rebuilt branch after squash-merge because auto-mode denies force-push | Tooling restriction | CONFIRMED (practice) — **has a footgun** (dropped open-PR commits once; fixed by cherry-pick) | git workflow | prefer a proper fix if force-push allowed |
