# History: Rule — sync every PR branch from `origin/main` before the gating run

The incident history behind [`.claude/rules/sync-pr-branch.md`](../rules/sync-pr-branch.md): the measurements, observations and worked examples that justify its instructions. It was moved here from the auto-loaded file by UAMS-Web/uams-statamic-archive#2693, and each section matches the heading it came from. The wording and dates are unchanged.

Observed: PR UAMS-Web/uams-statamic-archive#1073 (`805-normalize-treatment-blueprint`) sat **43 commits behind `origin/main`**, missing the UAMS-Web/uams-statamic-archive#1031 app-wide Rector/typing sweep that had reformatted the same `app/.../Scopes` surface it touched, while the concurrent wave PRs were current (0 behind).

## How to apply

   **The measured instance.** PR UAMS-Web/uams-statamic-archive#2228 was file-disjoint from every merge since its branch point — zero overlap — and `npm run spell` still reported **11 issues in 1 file** on its tip against **0 issues, 2,596 files** after merging `origin/main`. One was purely the stale base: UAMS-Web/uams-statamic-archive#2216 had added `PYTHONIOENCODING` to [`project-words.txt`](../../project-words.txt), the branch predates that entry, and its test file uses the word three times. The word is unknown on the branch and known on `main`. The disjointness check passed and was the wrong question.

It moves regularly here and not only for dependency bumps: measured 2026-09-06, its four most recent touches were UAMS-Web/uams-statamic-archive#2244 (a Stache fix), then importer version adoptions UAMS-Web/uams-statamic-archive#2077, UAMS-Web/uams-statamic-archive#2075 and UAMS-Web/uams-statamic-archive#2072. Stated as a dated observation rather than as "the last N", which decays on the next merge.
