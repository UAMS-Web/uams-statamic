<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1881 — the Claude Code permission rules in `.claude/settings.json`.
 *
 * No `App\` PHP surface is under test: this pins a checked-in configuration
 * file, so there is no class to target for mutation testing. The rules below
 * are verified by running the repo's own documented `gh api` recipes through a
 * model of the harness matcher instead.
 *
 * The matcher model is deliberately literal, because the real one is: a
 * `Bash(<prefix>:*)` rule is a case-sensitive prefix match against the raw
 * command text. Verified on 2026-08-04 against the live harness —
 * `gh api -x DELETE …` (lowercase flag) ran, while `gh api -X DELETE …` was
 * refused, so no case folding happens; and `git branch "-D" …` ran, so quoting
 * any part of the matched prefix defeats the rule. That last point is why the
 * allow entries below are scoped by leading token rather than by owner: an
 * owner-scoped rule would have to enumerate every casing of every org and would
 * still miss the quoted endpoints this repo's own recipes use.
 *
 * **This pins the PROJECT file only.** Claude Code merges `permissions.allow` across
 * enterprise, project, and user scopes, so a blanket `Bash(gh api *)` in a developer's
 * own `~/.claude/settings.json` still widens the effective policy on that machine. A
 * green run here means the repo's contribution is narrow, not that the merged policy
 * is — the per-machine half cannot be asserted from a checked-in test.
 */

/**
 * Model the harness's Bash permission matcher for a single rule.
 *
 * @param  string  $rule  A rule as written in `.claude/settings.json`
 * @param  string  $command  The Bash command to test against it
 * @return bool True when the rule matches the command
 */
$ruleMatchesCommand = static function (string $rule, string $command): bool {
    if (! str_starts_with($rule, 'Bash(') || ! str_ends_with($rule, ')')) {
        return false;
    }

    $inner = substr($rule, 5, -1);

    // `Bash(cmd:*)` is a prefix rule; `Bash(cmd)` matches that command exactly.
    if (str_ends_with($inner, ':*')) {
        return str_starts_with($command, substr($inner, 0, -2));
    }

    return $command === $inner;
};

/**
 * Read the checked-in permission rules.
 *
 * @return array{allow: list<string>, deny: list<string>} The two rule lists
 */
$permissionRules = static function (): array {
    $raw = file_get_contents(base_path('.claude/settings.json'));

    expect($raw)->toBeString();

    /** @var mixed $decoded */
    $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray();

    /** @var array{permissions?: array{allow?: mixed, deny?: mixed}} $decoded */
    $allow = $decoded['permissions']['allow'] ?? null;
    $deny = $decoded['permissions']['deny'] ?? null;

    expect($allow)->toBeArray()->and($deny)->toBeArray();

    /** @var list<string> $allowRules */
    $allowRules = array_values(array_filter((array) $allow, is_string(...)));

    /** @var list<string> $denyRules */
    $denyRules = array_values(array_filter((array) $deny, is_string(...)));

    // Guard against a vacuous pass: an empty or non-string list would satisfy
    // every "no rule matches" assertion below for the wrong reason.
    expect($allowRules)->not->toBeEmpty()->and($denyRules)->not->toBeEmpty();

    return ['allow' => $allowRules, 'deny' => $denyRules];
};

it('parses as JSON and carries string-only allow and deny rules', function () use ($permissionRules): void {
    $rules = $permissionRules();

    $raw = (string) file_get_contents(base_path('.claude/settings.json'));

    /** @var array{permissions: array{allow: array<int, mixed>, deny: array<int, mixed>}} $decoded */
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    // Every entry survived the is_string filter, so nothing was silently dropped.
    expect($rules['allow'])->toHaveCount(count($decoded['permissions']['allow']))
        ->and($rules['deny'])->toHaveCount(count($decoded['permissions']['deny']));
});

it('no longer allows every REST call the CLI can make', function () use ($permissionRules): void {
    // UAMS-Web/uams-statamic-archive#1841 widened this to a blanket entry so REST calls would stop prompting;
    // UAMS-Web/uams-statamic-archive#1881 replaces it with the enumerated surfaces the flows actually use.
    expect($permissionRules()['allow'])->not->toContain('Bash(gh api:*)');
});

/**
 * The endpoints in `.claude/rules/github-api-budget.md`, plus the board and
 * rate-limit reads the wave and local-CI flows issue. Each must be auto-approved
 * or an autonomous run stalls on an approval prompt nobody is there to answer.
 */
it('auto-approves the routine gh api calls the wave and local-CI flows issue', function (string $command) use ($permissionRules, $ruleMatchesCommand): void {
    $rules = $permissionRules();

    $allowed = array_any($rules['allow'], static fn (string $rule): bool => $ruleMatchesCommand($rule, $command));
    $denied = array_any($rules['deny'], static fn (string $rule): bool => $ruleMatchesCommand($rule, $command));

    expect($allowed)->toBeTrue("No allow rule matches [{$command}], so an autonomous run would stall on a prompt.");
    expect($denied)->toBeFalse("A deny rule matches [{$command}], and deny beats allow.");
})->with([
    'rate limit read' => ["gh api rate_limit --jq '.resources.graphql'"],
    'graphql' => ["gh api graphql -f query='query { viewer { login } }'"],
    'issue read' => ['gh api repos/UAMS-Web/uams-statamic/issues/1881'],
    'dependency read' => ['gh api repos/UAMS-Web/uams-statamic/issues/1881/dependencies/blocked_by --jq length'],
    'sub-issue read' => ['gh api repos/UAMS-Web/uams-statamic/issues/1881/sub_issues'],
    'timeline read' => ['gh api repos/UAMS-Web/uams-statamic/issues/1881/timeline --jq .[].event'],
    'compare read' => ['gh api repos/UAMS-Web/uams-statamic/compare/main...099ef594c'],
    'sibling repo, lowercase owner' => ['gh api repos/uams-web/wordpress-importer/issues/5'],
    'paginated read' => ['gh api --paginate repos/UAMS-Web/uams-statamic/issues'],
    'handle lookup' => ['gh api user --jq .login'],
    'PR body read' => ['gh api repos/UAMS-Web/uams-statamic/pulls/1841 --jq .body'],
    'issue create' => ["gh api -X POST repos/UAMS-Web/uams-statamic/issues -F title='Fix it' -F body=@body.md"],
    'issue body edit' => ['gh api -X PATCH repos/UAMS-Web/uams-statamic/issues/1881 -F body=@body.md'],
    'issue close' => ['gh api -X PATCH repos/UAMS-Web/uams-statamic/issues/1881 -f state=closed -f state_reason=completed'],
    'issue type' => ['gh api -X PATCH repos/UAMS-Web/uams-statamic/issues/1881 -f type=Task'],
    'comment' => ['gh api -X POST repos/UAMS-Web/uams-statamic/issues/1881/comments -F body=@c.md'],
    'label' => ["gh api -X POST repos/UAMS-Web/uams-statamic/issues/1881/labels -f 'labels[]=afk'"],
    'gate verdict, a hand-back (#2845)' => ['node scripts/post-verdict.mjs 1881 verdict.md --hand-back'],
    'gate verdict, a green (#2845)' => ['node scripts/post-verdict.mjs 1881 verdict.md'],
    'blocked_by edge' => ['gh api -X POST repos/UAMS-Web/uams-statamic/issues/1881/dependencies/blocked_by -F issue_id=123'],
    'sub-issue edge' => ['gh api -X POST repos/UAMS-Web/uams-statamic/issues/1876/sub_issues -F sub_issue_id=123'],
    'open a PR' => ['gh api -X POST repos/UAMS-Web/uams-statamic/pulls --input -'],
    'long-form write' => ['gh api --method PATCH repos/UAMS-Web/uams-statamic/issues/1881 -F body=@body.md'],
    // `PUT` is the MERGE verb — see the note above `it('scopes PUT …')` below.
    'merge a PR' => ['gh api -X PUT repos/UAMS-Web/uams-statamic/pulls/1906/merge --input -'],
    'merge a companion content PR' => ['gh api -X PUT repos/UAMS-Web/uams-statamic-content/pulls/44/merge -f merge_method=merge'],
    'merge, long-form' => ['gh api --method PUT repos/UAMS-Web/uams-statamic/pulls/1906/merge --input -'],
]);

/**
 * `PUT` belongs to the `POST`/`PATCH` verb class here, not to `DELETE`.
 *
 * It reads like a dangerous write verb, and an earlier revision of this branch denied
 * both its spellings on that reasoning. That was wrong: **`PUT` is how a pull request
 * merges** — `gh api -X PUT repos/{o}/{r}/pulls/{n}/merge` is the shape
 * `.claude/rules/github-api-budget.md` prescribes and the merge path THIS FILE is built
 * around, including the cross-repo `uams-statamic-content` merges that need
 * `merge_method=merge` to keep submodule pointers reachable. (The `local-ci` skill's
 * merge table prescribes the equivalent `gh pr merge <n> --<method>`; both reach the
 * same end state, and which one runs unprompted is a function of the merged permission
 * scopes.) Denying the verb would have made
 * every merge wait on a human, with no fallback **in this file**: `gh pr merge` is not
 * allow-listed here, and is GraphQL-backed besides — the budget the same rule exists to
 * protect. A developer's own `~/.claude/settings.json` may allow-list `Bash(gh pr *)` and
 * on the primary development machine does, but that is machine-specific: this file is the
 * portable artifact, so it is the one an autonomous run's merge path must be provable from.
 *
 * So `PUT` is scoped to `repos/` exactly as `POST` and `PATCH` are. Do not re-deny it.
 */
it('scopes PUT to the repos namespace rather than denying it', function () use ($permissionRules, $ruleMatchesCommand): void {
    $rules = $permissionRules();

    $matchesDeny = static fn (string $command): bool => array_any(
        $rules['deny'],
        static fn (string $rule): bool => $ruleMatchesCommand($rule, $command)
    );

    // No deny entry may mention PUT at all — a prefix rule cannot tell a merge from a
    // branch-protection write, so denying the verb denies merging.
    foreach ($rules['deny'] as $rule) {
        expect($rule)->not->toContain('PUT');
    }

    expect($matchesDeny('gh api -X PUT repos/UAMS-Web/uams-statamic/pulls/1906/merge --input -'))->toBeFalse()
        ->and($matchesDeny('gh api --method PUT repos/UAMS-Web/uams-statamic/pulls/1906/merge --input -'))->toBeFalse();
});

/**
 * Both spellings of every destructive verb **written flag-first**, since the rules are
 * prefix matches and `-X` / `--method` are interchangeable. UAMS-Web/uams-statamic-archive#1881 was filed on the
 * premise that `-X DELETE` was uncovered; it was already closed by UAMS-Web/uams-statamic-archive#1841, and these
 * pin it so a future edit cannot reopen it.
 *
 * Flag-LAST is a different matter — see the next test.
 */
it('denies the flag-first spelling of every destructive call', function (string $command) use ($permissionRules, $ruleMatchesCommand): void {
    $denied = array_any($permissionRules()['deny'], static fn (string $rule): bool => $ruleMatchesCommand($rule, $command));

    expect($denied)->toBeTrue("No deny rule matches [{$command}].");
})->with([
    'short-form DELETE' => ['gh api -X DELETE repos/UAMS-Web/uams-statamic/git/refs/heads/wave-group-du'],
    'long-form DELETE' => ['gh api --method DELETE repos/UAMS-Web/uams-statamic/git/refs/heads/wave-group-du'],
    'force push' => ['git push --force origin main'],
    'short force push' => ['git push -f origin main'],
    'delete remote branch' => ['git push --delete origin wave/group-du'],
    'short delete remote branch' => ['git push -d origin wave/group-du'],
    'force delete branch' => ['git branch -D main'],
]);

/**
 * A KNOWN, STRUCTURAL GAP, pinned rather than left implicit.
 *
 * `gh` accepts its method flag anywhere in the argument list, but a rule is a literal
 * PREFIX — so `gh api repos/…/git/refs/heads/main -X DELETE` matches the allow entry
 * `Bash(gh api repos/:*)` and no deny entry, and runs without a prompt. No arrangement
 * of prefix rules can close it: denying the flag-last form would require matching mid-
 * string, and dropping the bare `repos/` allow entry would make every ordinary read
 * prompt. The gap predates this change — `main`'s blanket `Bash(gh api:*)` admitted the
 * same shapes and more.
 *
 * The **case-sensitivity** row is the same class and worth keeping pinned: matching does
 * no case folding, verified live on 2026-08-04 — `gh api -x DELETE …` ran while
 * `gh api -X DELETE …` was refused. A lowercase typo is therefore not caught either.
 *
 * This asserts the gap so it is visible and versioned. If the matcher ever gains
 * substring or case-insensitive support these go red, which is the signal to tighten
 * the rules.
 */
it('cannot deny a destructive call the prefix matcher does not reach', function (string $command) use ($permissionRules, $ruleMatchesCommand): void {
    $denied = array_any($permissionRules()['deny'], static fn (string $rule): bool => $ruleMatchesCommand($rule, $command));

    expect($denied)->toBeFalse("[{$command}] is now denied — the prefix matcher gained reach, so tighten the rules and delete this dataset row.");
})->with([
    'flag-last DELETE' => ['gh api repos/UAMS-Web/uams-statamic/git/refs/heads/wave-group-du -X DELETE'],
    'quoted flag' => ['gh api "-X" DELETE repos/UAMS-Web/uams-statamic/git/refs/heads/wave-group-du'],
    'lowercase flag' => ['gh api -x DELETE repos/UAMS-Web/uams-statamic/git/refs/heads/wave-group-du'],
]);

/**
 * The point of the narrowing: a `gh api` write outside the `repos/` namespace is
 * no longer auto-approved. These are not denied — they fall through to an
 * approval prompt, which is the intended fail-closed behavior.
 */
it('stops auto-approving gh api writes outside the repos namespace', function (string $command) use ($permissionRules, $ruleMatchesCommand): void {
    $allowed = array_any($permissionRules()['allow'], static fn (string $rule): bool => $ruleMatchesCommand($rule, $command));

    expect($allowed)->toBeFalse("An allow rule still matches [{$command}].");
})->with([
    'create a repo' => ['gh api -X POST orgs/UAMS-Web/repos -f name=rogue'],
    'create a repo, flag-last' => ['gh api user/repos -X POST -f name=rogue'],
    // PUT is scoped, not blanket-allowed: outside `repos/` it still prompts.
    'block an org member' => ['gh api -X PUT orgs/UAMS-Web/blocks/someone'],
    'star a repo as the account' => ['gh api -X PUT user/starred/UAMS-Web/uams-statamic'],
    'edit the account profile' => ['gh api -X PATCH user -f name=rogue'],
    'edit the account profile, flag-last' => ['gh api user -X PATCH -f name=rogue'],
    'add an org member' => ['gh api -X POST orgs/UAMS-Web/invitations -f email=rogue@example.com'],
    'site admin endpoint' => ['gh api admin/hooks'],
    'arbitrary org read' => ['gh api orgs/UAMS-Web/members'],
]);
