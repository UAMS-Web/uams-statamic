<!--
  Skeleton only — .claude/skills/writing-pull-requests/SKILL.md is the source of truth.
  Delete the sections you don't need, and delete these comments before submitting.

  Title — it renders verbatim into the GitHub Release notes, so write it for someone
  skimming "what shipped": imperative, verb-first, no trailing period, no Conventional-Commit
  prefix, and no internal-process references (no `Wave N`, `Group X`, `Iteration N`, batch or
  merge-order hints). Correct acronym casing; "and", never "&".

  Before opening: run `npm run spell` if you touched prose or identifiers, run
  `composer mutate-ignore` if you added or changed a `@pest-mutate-ignore` annotation, and open as a
  DRAFT unless the branch is ready to merge once its required remote checks pass. Docs-only
  PRs open ready.

  Closing multiple issues? Repeat the keyword — `Closes #1, Closes #2.` A comma-separated
  list links only the FIRST issue.
-->

Closes #

<!-- One to three sentences: what changed, and why now. Lead with the user-visible effect or
     the root cause, not the implementation. -->

## The bug

<!-- Fix PRs only. Walk the root cause before the fix. -->

## Approach

<!-- When a path was chosen over alternatives, or reviewers need the design rationale. -->

## Files

<!-- - **edit** [`path/to/file.php`](https://github.com/UAMS-Web/uams-statamic/blob/<branch>/path/to/file.php) — reason. -->

## Verification

<!-- Evidence gathered before opening. If something couldn't be verified, say so plainly. -->

## Companion PRs

<!-- Sibling PRs in uams-statamic-content, wordpress-importer, etc. When this depends on a
     submodule companion, state the merge order and add the gated pointer bump to the test plan. -->

## Out of scope

## Follow-up

## Test plan

<!-- Mandatory, and always last. `- [x]` = verified locally; `- [ ]` = deferred to CI, review,
     or staging. Name the command, URL, or observation. -->

- [ ] Local CI green (`node scripts/local-ci.mjs --branch <branch>`).
