<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Statamic\Facades\Stache;
use Statamic\Facades\User;
use Statamic\Git\Git;
use Statamic\Stache\Stores\Store;
use Tests\Support\SeedAccounts;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Pins that the `users` allowlist (UAMS-Web/uams-statamic-archive#2454) holds when STATAMIC commits, not only
 * when a person does (UAMS-Web/uams-statamic-archive#2629).
 *
 * `STATAMIC_GIT_AUTOMATIC` is `false` today, and `base_path('users')` is in
 * `statamic.git.paths`. Turning automatic commits on would have Statamic run
 * `statamic.git.commands` over that directory after each save, through
 * {@see Git::commit()}, which is what its queued `CommitJob` calls.
 * {@see RegistrantRecordsAreGitIgnoredTest} pins the ignore rule with
 * `git check-ignore`, which says nothing about what those commands stage.
 *
 * These run Statamic's real commit path, with the configured commands, against a
 * THROWAWAY repository: the users store directory this base re-roots per test,
 * initialized as its own git repository and given the real `users/.gitignore`.
 * The real `users` submodule and its remote are never touched.
 *
 * The seed account is the first record the real `users/.gitignore` re-admits, read
 * through {@see SeedAccounts}, so no staff address is written here (UAMS-Web/uams-statamic-archive#2746).
 *
 * Measured against `statamic/cms` v6.26.0 with the commands pinned below. A change
 * to either is the thing to re-check here.
 *
 * @command  php artisan test --compact tests/Feature/Users/RegistrantRecordsStayOutOfAutomaticCommitsTest.php
 */
uses(StacheIsolatedTestCase::class);

const AUTOMATIC_COMMIT_REGISTRANT_EMAIL = 'zz-registrant@example.test';

/**
 * The email of the first seed account the real `users/.gitignore` re-admits.
 *
 * Called only after the `beforeEach` skip, so the file is present.
 */
function automaticCommitSeedEmail(): string
{
    $records = SeedAccounts::fromUsersSubmodule();

    if ($records === []) {
        throw new RuntimeException('users/.gitignore re-admits no seed record.');
    }

    return substr($records[0], 0, -strlen('.yaml'));
}

/**
 * Run git in the throwaway users repository and return its trimmed output.
 *
 * @param  list<string>  $arguments  The git arguments.
 */
function gitInThrowawayUsers(string $repository, array $arguments): string
{
    $result = Process::path($repository)->run(['git', ...$arguments]);

    if (! $result->successful()) {
        throw new RuntimeException('git '.implode(' ', $arguments).' failed: '.$result->errorOutput());
    }

    return trim($result->output());
}

/**
 * Turn the re-rooted users store into a repository with the given ignore rules and
 * one committed seed account, then point Statamic's git automation at it alone.
 *
 * @return string The repository path.
 */
function throwawayUsersRepository(string $gitignore): string
{
    $store = mustBe(Store::class, Stache::store('users'), 'the users store');
    $repository = rtrim((string) $store->directory(), '/\\');
    File::ensureDirectoryExists($repository);

    gitInThrowawayUsers($repository, ['init', '--quiet']);
    gitInThrowawayUsers($repository, ['config', 'user.email', 'tests@example.test']);
    gitInThrowawayUsers($repository, ['config', 'user.name', 'Tests']);
    gitInThrowawayUsers($repository, ['config', 'core.autocrlf', 'false']);

    File::put($repository.'/.gitignore', $gitignore);

    // The seed account exists and is tracked before anything else happens, as the
    // real seed accounts are.
    /** @var Statamic\Auth\File\User $seed */
    $seed = User::make();
    $seed->email(automaticCommitSeedEmail());
    $seed->data(['name' => 'Seed Account']);
    $seed->save();
    gitInThrowawayUsers($repository, ['add', '--force', '.gitignore', automaticCommitSeedEmail().'.yaml']);
    gitInThrowawayUsers($repository, ['commit', '--quiet', '-m', 'seed']);

    config([
        'statamic.git.enabled' => true,
        'statamic.git.paths' => [$repository],
    ]);

    return $repository;
}

/**
 * Save a registrant and a change to the seed account through Statamic, then run
 * Statamic's commit, exactly as the automatic-commit job would after those saves.
 */
function saveAndCommitThroughStatamic(): void
{
    /** @var Statamic\Auth\File\User $registrant */
    $registrant = User::make();
    $registrant->email(AUTOMATIC_COMMIT_REGISTRANT_EMAIL);
    $registrant->data(['name' => 'Montgomery Scott']);
    $registrant->save();

    $seed = mustBe(Statamic\Auth\File\User::class, User::findByEmail(automaticCommitSeedEmail()), 'the seed account');
    $seed->set('name', 'Seed Account, renamed');
    $seed->save();

    (new Git)->commit('Content saved');
}

beforeEach(function (): void {
    if (! File::exists(base_path('users/.gitignore'))) {
        $this->markTestSkipped('The users submodule is not checked out, so there is no allowlist to test.');
    }
});

it('runs the git commands this test was measured against', function (): void {
    // A `--force`, `-A` or explicit file list here could stage an ignored record,
    // so a change to these is the prompt to re-run the cases below.
    expect(config('statamic.git.commands'))->toBe([
        '{{ git }} add {{ paths }}',
        '{{ git }} -c "user.name={{ name }}" -c "user.email={{ email }}" commit -m "{{ message }}"',
    ])
        ->and(config('statamic.git.paths'))->toContain(base_path('users'));
});

it('commits a seed account change and leaves a registrant record out, under the real allowlist', function (): void {
    $repository = throwawayUsersRepository((string) File::get(base_path('users/.gitignore')));

    saveAndCommitThroughStatamic();

    $registrant = AUTOMATIC_COMMIT_REGISTRANT_EMAIL.'.yaml';
    $seed = automaticCommitSeedEmail().'.yaml';

    expect(File::exists($repository.'/'.$registrant))->toBeTrue()
        // Statamic committed: the seed change is in a new commit, not left in the tree
        ->and(gitInThrowawayUsers($repository, ['rev-list', '--count', 'HEAD']))->toBe('2')
        ->and(gitInThrowawayUsers($repository, ['show', '--name-only', '--format=', 'HEAD']))->toBe($seed)
        // ...and the registrant record is neither tracked nor pending, only ignored
        ->and(explode("\n", gitInThrowawayUsers($repository, ['ls-files'])))->not->toContain($registrant)
        ->and(gitInThrowawayUsers($repository, ['status', '--porcelain', '--ignored']))->toBe('!! '.$registrant);
});

it('commits the registrant record when the allowlist is absent, so the case above can fail', function (): void {
    // The pre-#2454 ignore file: the control that shows this commit path stages a
    // registrant whenever nothing ignores it.
    $repository = throwawayUsersRepository(".DS_Store\n");

    saveAndCommitThroughStatamic();

    expect(explode("\n", gitInThrowawayUsers($repository, ['ls-files'])))
        ->toContain(AUTOMATIC_COMMIT_REGISTRANT_EMAIL.'.yaml');
});
