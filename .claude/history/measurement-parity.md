# History: Rule — mirror the *job*, not the command, before comparing to a baseline

The incident history behind [`.claude/rules/measurement-parity.md`](../rules/measurement-parity.md): the measurements, observations and worked examples that justify its instructions. It was moved here from the auto-loaded file by UAMS-Web/uams-statamic-archive#2693, and each section matches the heading it came from. The wording and dates are unchanged.

**Why this is a standing order.** On 2026-07-31 a post-split band was measured against the UAMS-Web/uams-statamic-archive#1786 soak's 1300–1332s baseline. The pest invocation was copied byte-for-byte. What was *not* copied was the harness's `php artisan route:cache` step, run once up front — so every one of ~6070 per-test app rebuilds re-registered every route. The run reached 66% in 2563s and was heading for roughly double the baseline; with the route cache it hit 88% by ~600s and finished at **806s**. The uncached number would have been published as "the split made things worse," which is the exact opposite of the truth. This was the second instance of the same error in one work stream: an earlier bench also mirrored the pest *command* while omitting the job's Stache seed, and labeled the result "(gate parity)".

## How to apply

Measured here on 2026-09-05: two worktrees of this repository held the same vendored package reporting `v0.16.0` and reference `45c8db52c`, differing only in bytes — `sha256 348b62297a5d` against `df4f01af21b2`, one with `captureSubmoduleStates()` and the other with `captureContentSubmoduleState()`.

UAMS-Web/uams-statamic-archive#2249 was reported green with a mutation control attached, both measured against code that does not ship, and UAMS-Web/uams-statamic-archive#2119 was handed back with its acceptance criteria unmet.

All ten UAMS-Web/uams-statamic-archive#1786 soak bands recorded `verdict=DEVIATION` with an empty `tests=`, purely because the extraction used an ANSI-anchored `grep '^\s*Tests:'` against a line that begins with an escape sequence. The bands were clean.
