# `tests/manual/`

Human-run smoke checklists. **No PHP test code lives here** — only Markdown documents describing steps a person should perform and what to verify.

## When to add one

When automated coverage isn't practical for the behavior:

- **Visual / UX correctness in the Control Panel** — combobox sizing, dropdown overflow, focus rings, dark-mode rendering.
- **Third-party-package smoke tests after upgrades** — verify a Statamic / Laravel / addon bump didn't quietly break a custom integration that no automated test exercises.
- **Cross-browser or cross-OS spot checks** that CI can't run.
- **One-shot post-deploy verifications** for high-stakes changes.

If a behavior *can* reasonably be exercised by PHPUnit (HTTP test, computed-field assertion, Antlers render diff), write that instead — automation doesn't drift, checklists do.

## Format

One Markdown file per checklist, kebab-cased and descriptive:

```
cp-combobox-smoke-checklist.md
post-statamic-5-upgrade-checklist.md
```

Each file should open with:

1. **What this checks** — one paragraph.
2. **When to run it** — the trigger event (e.g. "after upgrading `statamic/cms`", "before each release of #X").
3. **Preconditions** — what state the environment must be in (built assets, logged-in CP user, etc.).
4. **Steps** — bulleted or numbered, each step's expected outcome stated.

The existing [`cp-combobox-smoke-checklist.md`](cp-combobox-smoke-checklist.md) is a working example to copy.

## Lifecycle

A checklist that hasn't been run in over a year should be reviewed and either deleted or updated. Stale manual checks are worse than no check — they imply coverage that isn't there.

## CI

Manual checklists are ignored by PHPUnit and CI. They are *documentation*, not test executables.
