# History: Rule — file a defect in the repository that owns it, and claim it first when a race is likely

The incident history behind [`.claude/rules/filing-defects-across-repos.md`](../rules/filing-defects-across-repos.md): the measurements, observations and worked examples that justify its instructions. It was moved here from the auto-loaded file by UAMS-Web/uams-statamic-archive#2693, and each section matches the heading it came from. The wording and dates are unchanged.

## How to apply

Observed: `claude-skills#68` and `#74` were filed minutes apart by different sessions, both rewriting the same `--doctor` block from opposite ends — one gating whether it speaks, the other fixing what it says — and the overlap was flagged between the sessions but reached neither body until it was added by hand.
