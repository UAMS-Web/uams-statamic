# Runbook: the rulesets on `main`, and recovering from them

`main` in `UAMS-Web/uams-statamic` is governed by **three rulesets**: two organization rulesets, both `active` since 2026-10-05, and a repository ruleset requiring status checks (#337). The two organization rulesets replaced the classic branch protection applied on 2026-07-31 (UAMS-Web/uams-statamic-archive#1841), and they target four repositories: `uams-claude-skills`, `uams-statamic`, `uamswp-migration-api` and `wordpress-importer`. **Neither has a bypass list**, so they bind administrators like everyone else. That is a decision, not a default: see the [decision log](../DECISIONS.md) entry for UAMS-Web/uams-statamic-archive#2016.

This runbook records **what is set**, **how to read it back**, and **how to get out of it** when a force-push or a branch deletion is genuinely required.

## What is set

| Ruleset (id) | Rules | Effect on `main` |
| --- | --- | --- |
| `main: no deletion or force push` (`24525022`) | `deletion`, `non_fast_forward` | The branch cannot be deleted, and no push may rewrite its history. |
| `main: pull request required` (`24525074`) | `pull_request` | Every change arrives through a pull request, so a direct push to `main` is refused. |

The `pull_request` rule's parameters are `required_approving_review_count: 0`, `required_reviewers: []`, `required_review_thread_resolution: false`, `require_code_owner_review: false`, `require_last_push_approval: false`, `dismiss_stale_reviews_on_push: false`, `require_extra_approval_for_unattributed_changes: true` and `allowed_merge_methods: ["merge", "squash", "rebase"]`. So this rule requires **a pull request and nothing else**: no approval and no resolved threads. The status checks come from the repository ruleset below. The repository's own merge settings narrow the method to squash.

Both rulesets have `bypass_actors: []`, and both target `refs/heads/main` with nothing excluded.

A third ruleset, the repository ruleset `main: required checks`, reaches this repository alone and requires the status checks `CI`, `Attribution footer` and `Closing keywords` (#337). It comes from the `code-quality` skill's `required-checks-ruleset.json`, applied with `apply-required-checks-ruleset.mjs`. The two organization rulesets above are unchanged.

**Classic branch protection is gone.** `GET .../branches/main/protection` answers `404 Branch not protected`, while `GET .../branches/main` still reports `protected: true` because the rulesets apply. Neither reading alone describes the enforcement, so read the rulesets.

The refusal of a direct push is GitHub's documented effect of the `pull_request` rule. It was not measured here by attempting a push to `main`.

## Reading it back

The repository endpoints work with an ordinary repository token. The organization endpoint (`orgs/UAMS-Web/rulesets`) answers `404` for a token without `admin:org`, so read through the repository:

```bash
gh api repos/UAMS-Web/uams-statamic/rulesets --jq '.[] | [.id, .name, .enforcement, .source_type] | @tsv'
gh api repos/UAMS-Web/uams-statamic/rulesets/24525074 --jq '{enforcement, bypass_actors, conditions, rules: [.rules[] | {type, parameters}]}'
gh api repos/UAMS-Web/uams-statamic/rules/branches/main --jq '.[] | [.type, .ruleset_id] | @tsv'
```

The last command lists the rules in force on `main`, whatever their source. Read on 2026-10-07 (macOS), it returned exactly `deletion` and `non_fast_forward` from `24525022` and `pull_request` from `24525074`.

## Recovery: force-pushing or deleting `main`

A repository ruleset cannot relax an organization ruleset: rules from every applicable ruleset stack, so the change has to be made to the organization ruleset, by an **organization owner**. It affects all four target repositories for as long as it is in place, so keep the window short.

Prefer a **temporary bypass entry** on the one ruleset you need over disabling it. Setting a ruleset's enforcement to `disabled` lifts its rules for everyone in all four repositories; a bypass entry lifts them for the acting role only. A temporary entry removed in the same sitting is a recovery step, not a standing bypass, so it does not reopen UAMS-Web/uams-statamic-archive#2016.

1. **Read the ruleset first and keep it**, beside the repository rather than in it. It is the record of the pre-state.

   ```bash
   gh api repos/UAMS-Web/uams-statamic/rulesets/24525022 > ruleset-before.json
   ```

2. **Add the bypass entry** in the organization's settings: **Settings, Rules, Rulesets**, open `main: no deletion or force push`, and add **Organization admin** to the bypass list with the **Always** mode. For a deletion and for a force-push this is the same ruleset; `main: pull request required` does not need to change.

3. **Perform the operation.** The branch is unguarded against that role for the duration, and the validate-and-merge loop does not know that.

4. **Remove the bypass entry** in the same screen.

5. **Read it back and diff it against step 1.** The settings screen saving is not proof:

   ```bash
   gh api repos/UAMS-Web/uams-statamic/rulesets/24525022 > ruleset-after.json
   diff <(jq 'del(.updated_at, ._links)' ruleset-before.json) <(jq 'del(.updated_at, ._links)' ruleset-after.json) && echo 'restored'
   ```

   Diff the whole object, not a few fields. Any difference other than the timestamp means the edit changed more than the bypass list; fix it before moving on. Then delete the two scratch files.

This procedure has not been run since the rulesets replaced classic protection.

## Merging under the required checks

- **A workflow template and its copy land in one branch.** `scripts/workflow-copies.test.mjs` and `scripts/handed-back-workflow.test.mjs` run in the `js` job and fail when a copy under `.github/workflows/` differs from its template in a synced skill, and the sync does not write `.github/`. So a sync pull request that moves a template stays red until the same branch carries the copy.
- **A Dependabot bump of an action in a copied workflow cannot go green alone.** It changes the copy and not the template, and a hand edit to the template fails `synced-files`. The bump has to arrive through the template in `UAMS-Web/uams-claude-skills`.
- **An open pull request reports `CI` only once its branch carries the job.** Until it is updated from `main`, the check stays pending.
- **After a flaked `pest` shard, re-run the whole workflow.** Re-running only the failed job can restore newer shard timings than the other shards read, and `pest-report` then refuses the run.
- **A pull request from a fork cannot pass `CI`.** GitHub passes a fork's run no secrets, so the private submodules do not clone. Copy its commits to a branch in this repository.

## The failure this guards against

No organization or repository variable named `RUN_REMOTE_CI` reaches this repository (read 2026-11-01), so every `ci.yml` job runs. A job skipped by its `if:` reports success to a required check, so a ruleset naming those jobs would pass with nothing run once the switch was set to `false`. The ruleset therefore names only the aggregate `CI` job, which carries no such gate and fails on a skipped job: with the switch off every merge is held rather than waved through. A check stays pending only when its whole workflow never runs, for example under a path filter or `[skip ci]`, so a head commit carrying `[skip ci]` cannot merge.

## History

From 2026-07-31 to 2026-10-05 `main` carried classic branch protection with `allow_force_pushes: false`, `allow_deletions: false` and `enforce_admins: false`. UAMS-Web/uams-statamic-archive#1843 measured it: merges and direct fast-forward pushes were allowed, force-pushes and deletion were refused for administrators too, and `enforce_admins` changed nothing. Its recovery procedure (a full-replacement `PUT` to `.../branches/main/protection`) no longer applies, since there is no protection object to write.

## Related

- [`docs/DECISIONS.md`](../DECISIONS.md): the UAMS-Web/uams-statamic-archive#2016 entry (no bypass list, and the rejected alternatives), which supersedes the UAMS-Web/uams-statamic-archive#1843 entry.
- UAMS-Web/uams-statamic-archive#2016: the decision, with both bypass lists read empty in the organization's settings on 2026-10-05.
- UAMS-Web/uams-statamic-archive#1843: the spike that measured classic protection.
- UAMS-Web/uams-statamic-archive#1841: applied classic protection here on 2026-07-31.
- [`.claude/rules/pre-merge-check.md`](../../.claude/rules/pre-merge-check.md): why a required pull request is not a merge gate.
- [`.claude/rules/github-api-budget.md`](../../.claude/rules/github-api-budget.md): why `PUT` under `repos/` is allow-listed, and why the deny list is a guardrail rather than a boundary.
