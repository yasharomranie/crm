---
paths:
  - '**/*'
---

# Keep `project-continuity/` and the training centre current
Whenever a feature, fix, module, permission, route, scheduled job, migration, setting or decision is added or changed, update the files in `project-continuity/` in the same PR and update the training centre (the owner's standing instruction, 2026-11-06, extended the same day):

- `CONTEXT.md` - modules, architecture, data, configuration (when structure or behaviour changed)
- `HANDOFF-<date>.md` - a new dated handoff when a session ends with meaningful change (keep older ones)
- `DECISIONS.md` - one entry per non-trivial decision
- `TEST-LOG.md` - only what was actually run, with the real result (never mark unrun checks as passed)
- `OPEN-QUESTIONS.md` - add or close questions
- `DEPLOYMENT.md` - when deploy or host behaviour changes

- **مرکز آموزش** (the in-app training centre: `/system-guide`, file `resources/views/system-guide.blade.php`) - add or update the chapter/section for the feature, in Persian, in the same style as the existing chapters

A change is not finished until both `project-continuity/` and the training centre are updated. Never put secrets in them.
