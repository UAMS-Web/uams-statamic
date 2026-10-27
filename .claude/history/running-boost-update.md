# History: Rule — run `boost:update` only as `--ignore-skills`, deliberately, never mid-ticket

The incident history behind [`.claude/rules/running-boost-update.md`](../rules/running-boost-update.md): the measurements, observations and worked examples that justify its instructions. It was moved here from the auto-loaded file by UAMS-Web/uams-statamic-archive#2693, and each section matches the heading it came from. The wording and dates are unchanged.

## Why this is a standing order

UAMS-Web/uams-statamic-archive#2168 caught it only because that branch was checking `git status` against an expected file list, and it had to revert the churn **twice**.
