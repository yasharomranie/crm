---
paths:
  - '**/*'
---

# Keep `project-continuity/` current
Whenever a feature, fix, module, permission, route, scheduled job, migration, setting or decision is added or changed, update the files in `project-continuity/` in the same PR (the owner's standing instruction, 2026-11-06):

- `CONTEXT.md` - modules, architecture, data, configuration (when structure or behaviour changed)
- `HANDOFF-<date>.md` - a new dated handoff when a session ends with meaningful change (keep older ones)
- `DECISIONS.md` - one entry per non-trivial decision
- `TEST-LOG.md` - only what was actually run, with the real result (never mark unrun checks as passed)
- `OPEN-QUESTIONS.md` - add or close questions
- `DEPLOYMENT.md` - when deploy or host behaviour changes

A change is not finished until these are updated. Never put secrets in them.
