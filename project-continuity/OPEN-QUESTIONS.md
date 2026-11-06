# OPEN QUESTIONS

| # | Question | Why it matters | Evidence checked | Currently unknown | Who can resolve | Priority |
|---|----------|----------------|------------------|-------------------|-----------------|----------|
| Q1 | Why does the AI provider reject chat requests (`openai_error`) when «تست اتصال» passes? (**2026-11-06: owner wrote «درست شد الان کار می‌کنه» without naming the item — probably the assistant; NEEDS CONFIRMATION and, if so, the root cause is unknown: possibly the earlier `specialties` crash plus a model/gateway change**) | Flagship assistant unusable | Code path; test-connection result (`gpt-4o-mini`, 1.4 s); error text with request id | HTTP status/type/code (now shown after #249); which model the assistant uses | Owner (deploy, retry, send message text), provider dashboard | **P0** |
| ~~Q2~~ | ~~Did the room-rack fix (#250) work on production?~~ **RESOLVED 2026-11-06:** owner confirmed «رک اتاق درست شد». | — | — | — | — | closed |
| Q3 | Which production DB engine and PHP version? | Portable migrations, features | `.env.example`, `config/database.php` (default sqlite) | Real values | Owner/cPanel | P1 |
| Q4 | Is the repo currently public or private? Are Actions minutes available? | CI may stop (jobs failing in ~2 s) | Session history (made public to regain free minutes) | Current visibility/billing | Owner | P1 |
| Q5 | How exactly is `vendor/` kept on the host (composer install on deploy)? New dependencies would break otherwise | Deploy safety | `DeploymentTriggerService` doc only mentions git pull/migrate/optimize | Cron script contents | Owner (cPanel cron) | P1 |
| Q6 | Are dashboard widgets and other pages hidden for restricted roles? | Staff rollout may leak financial numbers | `AccessControlCoverageTest`, config; widgets not audited | Behaviour with a role-limited user | AI (audit) + owner (test account) | P1 |
| Q7 | Smart-guide accuracy: which threshold/margin work with real cave photos? How many reference photos per object? | Pilot go/no-go | Defaults 0.700/0.030; pipeline reviewed | No measurements | Owner (photos + «آزمون دقت») | P1 |
| Q8 | Does the 35 MB prefetch + service-worker caching behave on real phones (Android Chrome, iOS Safari)? Storage eviction? Does the host serve `.mjs/.wasm/.onnx` with correct types/cache? | Offline use in cave | Code only | Device/host behaviour | Owner testing | P2 |
| ~~Q9~~ | ~~Root README outdated~~ **RESOLVED 2026-11-06:** owner approved; README rewritten as a short pointer to `project-continuity/`. | — | — | — | — | closed |
| Q10 | Which staff-training deliverable should be built first (roles seeding, printable one-pager, widget audit)? | Owner's next step | Advice given in session | Choice | Owner | P1 |
| Q11 | Should the continuity rule live only in this package + `.ai/rules/project-continuity.md`, or also in `CLAUDE.md`? | Future sessions must obey it | `record-rule` MCP tool unavailable; rule file added manually | Owner preference | Owner | P3 |
| Q12 | Count "post/story" from the daily journal in digital-projects stats? | Feature never confirmed | Asked once, no answer | Owner intent | Owner | P3 |
| Q13 | Real CLAUDE.md "Documentation Files" rule vs. this package | The package exists by explicit owner request; future doc additions must also be requested | CLAUDE.md | — | Owner | P3 |
| Q14 | Does «مرکز آموزش» mean the in-app `/system-guide`? Is there another training page planned? | The standing update rule targets it | No other training page exists in the repo (grep) | Owner's intent | Owner | P2 |
