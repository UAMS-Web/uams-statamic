<?php

declare(strict_types=1);

use App\Scopes\EntriesByBlueprint;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;
use Statamic\Query\Scopes\Scope;
use Tests\TestCase;

/**
 * Pins when `EntriesByBlueprint` reads the collection blueprints its aliases
 * come from (UAMS-Web/uams-statamic#1515).
 *
 * Statamic registers app scopes while the service providers register. On
 * `statamic/cms` 6.41.0 and later a blueprint is parsed through the cache
 * store, which is a deferred binding and does not exist yet then, so a scope
 * that reads blueprints while it registers stops the application booting. The
 * scope therefore binds its own handle at registration and its blueprint
 * aliases only once the application has booted.
 *
 * Each case registers the scope against a fresh application that has not
 * booted yet, so the order is observable; the test application's container is
 * restored afterwards.
 *
 * @covers \App\Scopes\EntriesByBlueprint
 *
 * @command  php artisan test --compact tests/Feature/Scopes/EntriesByBlueprintRegistrationTest.php
 */
uses(TestCase::class);

/**
 * Hand the callback a fresh application that has not booted, with an empty
 * Statamic extension registry, restoring the test application's container
 * afterwards.
 *
 * @param  Application  $testApplication  the application under test, restored afterwards
 * @param  Closure(Application): void  $callback
 */
function withFreshApplication(Application $testApplication, Closure $callback): void
{
    try {
        // The constructor makes the fresh application the global container,
        // so `app()` inside the scope resolves against it.
        $application = new Application($testApplication->basePath());
        $application->instance('statamic.extensions', collect());

        $callback($application);
    } finally {
        Container::setInstance($testApplication);
    }
}

/**
 * The scope bindings in an application's Statamic extension registry.
 *
 * @return Collection<array-key, mixed>
 *
 * @throws RuntimeException when the application holds no scope registry
 */
function boundScopes(Application $application): Collection
{
    $scopes = $application->make('statamic.extensions')->get(Scope::class);

    if (! $scopes instanceof Collection) {
        throw new RuntimeException('The application holds no Statamic scope registry.');
    }

    return $scopes;
}

it('binds only its own handle while the application has not booted', function () {
    withFreshApplication($this->app, function (Application $application): void {
        EntriesByBlueprint::register();

        expect($application->isBooted())->toBeFalse()
            ->and(boundScopes($application)->keys()->all())->toBe(['entries_by_blueprint']);
    });
});

it('reads no blueprint until the application boots', function () {
    $repository = Blueprint::getFacadeRoot();

    if (! $repository instanceof BlueprintRepository) {
        throw new RuntimeException('The Blueprint facade does not resolve to a blueprint repository.');
    }

    // Count the namespace reads the aliases come from, forwarding every call.
    $counting = new class($repository)
    {
        public int $reads = 0;

        public function __construct(private readonly BlueprintRepository $repository) {}

        /**
         * Count a namespace read and forward it.
         */
        public function in(string $namespace): mixed
        {
            $this->reads++;

            return $this->repository->in($namespace);
        }

        /**
         * Forward every other call to the real repository.
         *
         * @param  array<int, mixed>  $arguments
         */
        public function __call(string $method, array $arguments): mixed
        {
            return $this->repository->{$method}(...$arguments);
        }
    };

    Blueprint::swap($counting);

    try {
        withFreshApplication($this->app, function (Application $application) use ($counting): void {
            EntriesByBlueprint::register();
            $readsBeforeBoot = $counting->reads;

            $application->boot();

            // The second figure shows the counter sees the reads it is guarding against.
            expect($readsBeforeBoot)->toBe(0)
                ->and($counting->reads)->toBeGreaterThan(0);
        });
    } finally {
        Blueprint::swap($repository);
    }
});

it('binds every blueprint alias once the application boots', function () {
    $expected = EntriesByBlueprint::aliases();

    withFreshApplication($this->app, function (Application $application) use ($expected): void {
        EntriesByBlueprint::register();
        $application->boot();

        $bound = boundScopes($application);

        expect($expected)->not->toBeEmpty()
            ->and($bound->keys()->sort()->values()->all())
            ->toBe(collect(['entries_by_blueprint', ...$expected])->sort()->values()->all())
            ->and($bound->unique()->values()->all())->toBe([EntriesByBlueprint::class]);
    });
});

it('binds the aliases at once when the application has already booted', function () {
    withFreshApplication($this->app, function (Application $application): void {
        $application->boot();
        EntriesByBlueprint::register();

        expect(boundScopes($application)->keys())
            ->toContain('entries_by_blueprint', 'entries_by_blueprint_building');
    });
});

it('keeps the scopes already in the registry', function () {
    withFreshApplication($this->app, function (Application $application): void {
        $application->instance('statamic.extensions', collect([
            Scope::class => collect(['enterprise_scope' => 'Kirk\\Scope']),
        ]));

        EntriesByBlueprint::register();
        $application->boot();

        expect(boundScopes($application)->get('enterprise_scope'))->toBe('Kirk\\Scope')
            ->and(boundScopes($application)->get('entries_by_blueprint'))->toBe(EntriesByBlueprint::class);
    });
});
