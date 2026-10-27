# History: Rule — a green chain is not a merge decision

The incident history behind [`.claude/rules/pre-merge-check.md`](../rules/pre-merge-check.md): the measurements, observations and worked examples that justify its instructions. It was moved here from the auto-loaded file by UAMS-Web/uams-statamic-archive#2693, and each section matches the heading it came from. The wording and dates are unchanged.

## Why this is a standing order

Two neighboring framings are both wrong here, and each was believed at some point. It is **not** "unprotected" — [`local-ci`](../skills/local-ci/SKILL.md) said so until UAMS-Web/uams-statamic-archive#2017 corrected it, and the conclusion survived only because `required_status_checks` is null for an unrelated reason. It is also **not** "protected and enforcing nothing", which is a sibling repository's measured state; here two protections are genuinely active and simply do not gate merges.

And the specific failures are on record. Each passed its own chain:

- **A branch validated while behind an interacting merge.** UAMS-Web/uams-statamic-archive#1073 sat 43 commits behind an app-wide sweep that had reformatted the same surface it touched. Its own suite stayed green because it never saw the interacting change — see [`sync-pr-branch`](../rules/sync-pr-branch.md).

- **A branch file-disjoint but not interaction-free.** UAMS-Web/uams-statamic-archive#2228 shared no file with any intervening merge and still reported 11 spell issues on its tip against 0 after merging `origin/main`, because `project-words.txt` is a job input rather than a source file.

- **Acceptance criteria never checked against the diff.** No job observes them, which is why [`closing-a-ticket`](../rules/closing-a-ticket.md) has to.

- **A diff that passes every gate and is still wrong**, which is the standing premise of [`adversarial-review`](../rules/adversarial-review.md).
