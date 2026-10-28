<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\Support\SeedAccounts;
use Tests\TestCase;

/**
 * Pins the allowlist in the `users` submodule's `.gitignore` (UAMS-Web/uams-statamic-archive#2454): registrant
 * records are ignored by default and only the named seed accounts are re-admitted.
 *
 * Without this, restoring that file to its pre-#2454 body — a single `.DS_Store`
 * line — makes every self-service registrant's record committable again, and
 * nothing else in the suite notices.
 *
 * **Scope.** This asserts that records cannot reach *git*. It says nothing about
 * how registrant records are protected at rest on the server or in backups, which
 * is open on #223 and #224 (split from UAMS-Web/uams-statamic-archive#2455). What Statamic's own automatic
 * commits stage is pinned by RegistrantRecordsStayOutOfAutomaticCommitsTest (UAMS-Web/uams-statamic-archive#2629).
 *
 * **Where this runs.** In BOTH gates, and in every developer checkout and worktree
 * slot. Since UAMS-Web/uams-statamic-archive#2477, `ci.yml`'s pest job and `scripts/local-ci.mjs` check out
 * `users` alongside `content` (it is a 2 KB pack holding the two seed accounts and
 * this allowlist, and nothing else since UAMS-Web/uams-statamic-archive#2454), so a reverted allowlist reds the
 * chain rather than passing it. The skips below fire only where the submodule is
 * genuinely absent -- an ephemeral worktree, or a checkout that never ran
 * `git submodule update --init users` -- and `tests/skip-baseline.json` pins the
 * gate's parallel band at a count that assumes these three cases RUN, so a gate
 * that stops checking `users` out surfaces as an unexpected skip there, and
 * tests/Feature/GatesInitializeUsersSubmoduleTest.php pins the checkout itself.
 *
 * **The seed names are read, not written (UAMS-Web/uams-statamic-archive#2746).** The re-admitted names come from
 * that same `.gitignore` through {@see SeedAccounts}, so the staff addresses live only
 * in the private submodule (UAMS-Web/uams-statamic-archive#2740). Each case first requires that the file re-admits
 * at least one record, so an allowlist that re-admits nothing fails rather than passing
 * an empty loop.
 *
 * Accounts that Feature tests create (`FrontEndAccountFlowTest`'s registrants,
 * `TriggerActivateRouteTest`'s users) are written into this same directory by the
 * file driver and land under the allowlist's ignore rule, which is the behavior
 * this test asserts; that was equally true before the gates checked the submodule
 * out, when they wrote into a plain directory instead. The browser suite is not
 * among the writers: `Tests\Support\BrowserWriteIsolation` re-roots its users
 * store at a throwaway directory (UAMS-Web/uams-statamic-archive#1920).
 */
uses(TestCase::class);

function usersSubmodulePath(): string
{
    return base_path('users');
}

/**
 * Whether git reports the path as ignored, read from the EXIT STATUS.
 *
 * `git check-ignore -v` prints a line for negated patterns too, so a re-admitted
 * seed account appears in the output with its `!` rule and is NOT ignored.
 * Treating "there was output" as "ignored" reports every `*.yaml` path as ignored
 * and makes a broken allowlist look correct.
 */
function gitReportsIgnored(string $relativePath): bool
{
    $result = Process::path(usersSubmodulePath())
        ->run(['git', 'check-ignore', '--no-index', '-q', $relativePath]);

    return $result->exitCode() === 0;
}

beforeEach(function (): void {
    if (! File::isDirectory(usersSubmodulePath())) {
        $this->markTestSkipped('The users submodule is not checked out.');
    }

    if (! File::exists(usersSubmodulePath().'/.gitignore')) {
        $this->markTestSkipped('The users submodule is checked out but holds no .gitignore.');
    }

    // A submodule directory that exists but is not a repository would make every
    // check-ignore call fail identically, which is indistinguishable from "not
    // ignored" on the exit status this test reads.
    $isRepository = Process::path(usersSubmodulePath())
        ->run(['git', 'rev-parse', '--is-inside-work-tree'])
        ->exitCode() === 0;

    if (! $isRepository) {
        $this->markTestSkipped('The users submodule directory is not a git repository.');
    }
});

it('ignores a registrant user record', function (): void {
    expect(gitReportsIgnored('newregistrant@example.com.yaml'))->toBeTrue()
        ->and(gitReportsIgnored('alice@example.org.yaml'))->toBeTrue();
});

it('re-admits the seed accounts the Control Panel and slot provisioning depend on', function (): void {
    // The control. An allowlist that ignored everything would satisfy the case
    // above and be useless; the re-admitted seed records separate the two outcomes.
    $records = SeedAccounts::fromUsersSubmodule();

    expect($records)->not->toBeEmpty();

    foreach ($records as $record) {
        expect(gitReportsIgnored($record))->toBeFalse();
    }
});

it('keeps the seed accounts tracked', function (): void {
    $tracked = Process::path(usersSubmodulePath())->run(['git', 'ls-files']);

    expect($tracked->exitCode())->toBe(0);

    // Split on LF after stripping CR rather than with `preg_split('/\R/')`: that
    // pattern can return `false`, and it also matches a bare 0x85, which is a UTF-8
    // continuation byte -- so it corrupts multi-byte output while looking correct.
    //
    // Escaped sequences, never the literal bytes: the merged version of this file
    // carried a raw CR inside the single-quoted search string, and any tool that
    // reads the file in a newline-translating text mode rewrites it to LF -- which
    // turned the parser into a one-element array during a docblock edit on UAMS-Web/uams-statamic-archive#2477,
    // the change that put this test into the gate. Pint is not that tool: measured
    // on the merged file, `pint --test` passes it and `pint` leaves the byte alone.
    $files = collect(explode("\n", str_replace("\r", '', trim($tracked->output()))))
        ->filter()
        ->all();

    $records = SeedAccounts::fromUsersSubmodule();

    expect($records)->not->toBeEmpty();

    foreach ($records as $record) {
        expect($files)->toContain($record);
    }
});
