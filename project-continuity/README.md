# Project Continuity Package — «سامانه مدیریت یکپارچه» (Ganjnameh CRM/ERP)

**Snapshot date:** 2026-11-06 (inspection ~06:37 UTC) · **Scope:** the repository at `/home/user/crm`, branch `claude/install-ui-ux-pro-max-tqw3sv`, plus the visible history of the working session (labelled where used).

## What this is
A documentation package that lets a fresh AI session (or a human) understand the project and continue safely without the original conversation. It is a **snapshot**: code is the final authority; if code and these files disagree, trust the code and update the files.

The package is written in English on purpose (portable across assistants). Product/UI labels are quoted in Persian exactly as they appear in the admin panel, because that is what the owner says and sees.

## The project in one paragraph
A Persian-first (RTL, Jalali calendar) Laravel 13 + Filament v5 admin system that combines several businesses for one Iranian tourism/hospitality group: hotel/villa management («اقامتی و هتلداری»), ticketing/gate scanning for an attraction («گیشه بلیط»), CRM, contracts, forms/workflows, tasks/projects, HR attendance, documents, an in-panel AI assistant system, a large SEO centre, a digital-projects reporting module, an installable customer PWA («وب‌اپ مشتریان» at `/app`), and an experimental on-device **tourism smart guide** («راهنمای هوشمند گردشگری») that recognises museum/cave objects with the phone camera and plays a story video. It runs on a **cPanel shared host with no terminal**; deploys happen from a panel button.

## Files in this package (read in this order)
| # | File | Purpose |
|---|------|---------|
| 1 | `README.md` | This entry point |
| 2 | `INSTRUCTIONS.md` | Operating rules for any AI working here (conventions, workflow, safety, keeping this package current) |
| 3 | `CONTEXT.md` | The "project brain": architecture, modules, data, integrations, config, state |
| 4 | `HANDOFF-2026-11-06.md` | Standalone handoff: exact current state, work done/in progress, roadmap, resume plan, starter prompt |
| 5 | `DEPLOYMENT.md` | How production is deployed and operated (non-obvious, host-specific) |
| 6 | `DECISIONS.md` | Decision register (what was chosen and why, with confidence labels) |
| 7 | `TEST-LOG.md` | Validation record: what was actually run, what was not |
| 8 | `OPEN-QUESTIONS.md` | Unresolved questions and who can answer them |

Other docs that already exist (not replaced): root `README.md` (older AI-oriented overview, **partly outdated** — see `OPEN-QUESTIONS.md`), `CLAUDE.md` / `AGENTS.md` (Laravel Boost guidelines), `.ai/rules/*` (project rules), and the in-app guide at `resources/views/system-guide.blade.php` (served at `/system-guide`).

## Warning
Everything here reflects `origin/main` at commit `cc599ca` (PR #250) plus the open PR #251 on the working branch. Evidence labels used throughout: `CONFIRMED` (seen in code/CI/this session), `INFERRED`, `UNKNOWN`, `NOT TESTED`, `NEEDS VERIFICATION`.
