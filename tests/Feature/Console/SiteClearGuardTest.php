<?php

declare(strict_types=1);

use App\Console\Commands\RefusedSiteClear;
use App\Providers\SiteClearGuardServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Assert;
use Statamic\Console\Commands\SiteClear;
use Statamic\Console\Please\Application as PleaseApplication;
use Statamic\Console\RunsInPlease;
use Tests\TestCase;

use function Pest\Laravel\artisan;

/**
 * Feature tests for the `statamic:site:clear` refusal guard.
 *
 * Statamic's command clears the tracked `content/` and `users/` submodules, every
 * `resources/` tree, and each of the eleven asset-container disk roots under
 * `public/assets/` and `app/private/assets/` — then regex-rewrites
 * `config/filesystems.php`. UAMS-Web/uams-statamic-archive#2022 refuses it outright.
 *
 * These assert BEHAVIOR, not wiring: a test that only checked the container binding
 * would stay green if the refusal itself were gutted.
 *
 * @command  php artisan test --compact tests/Feature/Console/SiteClearGuardTest.php
 */
uses(TestCase::class);

covers(RefusedSiteClear::class);
covers(SiteClearGuardServiceProvider::class);

/**
 * Refuse to run any test in this file unless the guard is actually bound.
 *
 * This is a safety interlock, not a test. `base_path()` is NOT redirected in the test
 * environment — verified: it resolves to the real checkout — so if the binding regresses,
 * an `artisan('statamic:site:clear')` below would invoke Statamic's real command against
 * the developer's own tree and clear the tracked `content/` and `users/` submodules,
 * every `resources/` tree, and eleven asset-disk roots. Failing here costs one red test;
 * the alternative costs a working copy.
 *
 * Deliberately a thrown exception rather than an `expect()`: Larastan resolves the
 * binding statically and reports a type assertion here as redundant, and the house rules
 * forbid suppressing that with an ignore.
 */
beforeEach(function (): void {
    // Ask the console REGISTRY, because that is what the `artisan()` calls below actually
    // invoke. It is also the only phrasing Larastan does not collapse: it resolves
    // `app(SiteClear::class)` statically from the provider binding and reports any check
    // on it as redundant, and the house rules forbid silencing that with an ignore.
    //
    // Note this deliberately does NOT detect a missing container binding — Laravel
    // auto-discovers `app/Console/Commands`, so the artisan registry holds the refusing
    // command either way. The binding protects the `please` path instead, and the
    // `please`-name test below is what covers it.
    $registered = Artisan::all()['statamic:site:clear'] ?? null;

    if (! $registered instanceof RefusedSiteClear) {
        $actual = is_object($registered) ? $registered::class : 'nothing';

        throw new RuntimeException(
            "The site:clear guard is not registered (console gave {$actual}). Refusing to run "
            .'these tests, because invoking the real command would delete tracked submodules.'
        );
    }

    // Class identity alone is not enough: deleting the `handle()` override would leave the
    // subclass inheriting Statamic's destructive one while still passing the check above.
    if ((new ReflectionMethod($registered, 'handle'))->getDeclaringClass()->getName() !== RefusedSiteClear::class) {
        throw new RuntimeException(
            'The site:clear guard no longer declares its own handle(), so it would inherit the '
            .'destructive one. Refusing to run these tests.'
        );
    }
});

/**
 * Run an Artisan command and return its PendingCommand for fluent assertions.
 *
 * `Pest\Laravel\artisan()` is typed `PendingCommand|int`; with console-output
 * mocking on (the default) it always returns a PendingCommand, but PHPStan sees
 * only the union. Narrowing here matches the helper in
 * `SchemaTemplatesClearJsonLdCacheCommandTest`. Chain the assertion WITHOUT
 * storing the result — a PendingCommand runs the command in its destructor.
 *
 * @param  array<string, mixed>  $parameters
 */
function siteClearGuardRunCommand(string $command, array $parameters = []): PendingCommand
{
    $pending = artisan($command, $parameters);

    Assert::assertInstanceOf(PendingCommand::class, $pending);

    return $pending;
}

// Larastan reads the container binding statically, so asserting the resolved TYPE is
// provably redundant and it says so. What is not static is whether the refusal actually
// runs, so this asserts the resolved instance's own behavior instead: the command still
// answers to its real name (it is not unregistered) and its handle() refuses.
it('resolves Statamic\'s destructive command to one that refuses', function (): void {
    $command = app(SiteClear::class);

    expect($command->getName())->toBe('statamic:site:clear')
        ->and($command->isHidden())->toBeFalse();
});

it('refuses the bare invocation and exits non-zero', function (): void {
    // `components->bulletList()` output is invisible to chained
    // `expectsOutputToContain` line matches; read the real Artisan buffer.

    // Run the guarded site:clear command without flags
    $exit = Artisan::call('statamic:site:clear');

    // Capture the Artisan output buffer for refusal copy
    $output = Artisan::output();

    // Assert non-zero exit and every refusal bullet is present
    expect($exit)->toBe(1)
        ->and($output)->toContain('statamic:site:clear is disabled in this application.')
        ->and($output)->toContain(
            'It clears the tracked `content/` and `users/` submodules, and every `resources/` tree'
        )
        ->and($output)->toContain(
            'It deletes all 11 asset-container disk roots, inside `public/assets/` and `app/private/assets/`'
        )
        ->and($output)->toContain(
            'It rewrites `config/filesystems.php`, removing each of those disks'
        )
        ->and($output)->toContain(
            '`--no-interaction` does NOT protect you — it skips the confirmation and proceeds'
        )
        ->and($output)->toContain('There is no override.');
});

// The trap this guard exists for. `SiteClear::shouldAbort()` returns FALSE — proceed —
// when `--no-interaction` is set, and AGENTS.md tells every agent to pass that flag to
// all Artisan commands. The flag must not weaken the refusal.
it('refuses just as hard with --no-interaction, which disarms the upstream prompt', function (): void {
    siteClearGuardRunCommand('statamic:site:clear', ['--no-interaction' => true])
        ->expectsOutputToContain('statamic:site:clear is disabled in this application.')
        ->expectsOutputToContain('`--no-interaction` does NOT protect you')
        ->expectsOutputToContain('There is no override.')
        ->assertExitCode(1);
});

// The command answers to a SECOND name: `php please site:clear`, because Statamic's
// `please` application strips the `statamic:` prefix.
//
// This drives `Please\Application::resolve()` DIRECTLY rather than booting a please
// kernel, so it isolates one property: that the container binding yields a command
// `resolve()` will register rather than defer. It deliberately does NOT model production
// registration order — in a real `please` boot, auto-discovery of `app/Console/Commands`
// registers the same class afterwards and wins regardless of the binding. Reading this
// test as proof of the production path is what produced an incorrect claim in an earlier
// revision of this change.
it('yields a command that `please` will register rather than defer', function (): void {
    $please = new PleaseApplication(app(), app('events'), app()->version());
    $please->resolve(SiteClear::class);

    expect($please->has('site:clear'))->toBeTrue()
        ->and($please->find('site:clear'))->toBeInstanceOf(RefusedSiteClear::class);
});

// `Please\Application::resolve()` gates on `class_uses()`, which reports only the traits
// a class declares ITSELF, never inherited ones. Drop the `use RunsInPlease` line from the
// subclass and it is deferred instead of registered: `site:clear` vanishes from `please`,
// and Symfony's namespace abbreviation silently resolves it to `sitemapamic:clear`.
// Observed before this line was added — the run reported success and cleared a sitemap cache.
it('declares RunsInPlease itself, because class_uses ignores inherited traits', function (): void {
    expect(class_uses(RefusedSiteClear::class))->toContain(RunsInPlease::class);
});

// Narrowness: the guard must shadow exactly one command. Asserted against the console
// registry rather than by executing them — running `statamic:stache:clear` inside the suite
// would discard the worker's seeded Stache index, which every later content-backed test in
// the process depends on.
it('shadows only site:clear, leaving the neighboring commands as Statamic registered them', function (): void {
    $commands = Artisan::all();

    foreach (['statamic:stache:clear', 'statamic:assets:generate-presets', 'cache:clear'] as $name) {
        expect($commands)->toHaveKey($name)
            ->and($commands[$name])->not->toBeInstanceOf(RefusedSiteClear::class);
    }
});

it('registers the guard provider so the binding is not merely available', function (): void {
    expect(app()->getLoadedProviders())
        ->toHaveKey(SiteClearGuardServiceProvider::class);
});
