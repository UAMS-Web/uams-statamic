<?php

declare(strict_types=1);

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Every listener in `app/Listeners` is registered once, through
 * `EventServiceProvider::$listen`, for exactly the events its `handle*()` methods
 * take (UAMS-Web/uams-statamic-archive#3225, UAMS-Web/uams-statamic-archive#3240).
 *
 * The expected pairs are read from each listener's signatures, which is the rule
 * Laravel's listener discovery applied. With discovery on as well as `$listen`,
 * every listed pair was registered twice; a listener left out of `$listen` with
 * discovery off is registered for nothing. Both fail here.
 *
 * @command  php artisan test --compact tests/Feature/Providers/EventServiceProviderTest.php
 */
uses(TestCase::class);

/**
 * The (event, listener method) pairs each listener's `handle*()` methods take.
 *
 * @return list<string> Pairs written `Event => Listener@method`, sorted
 */
function eventServiceProviderExpectedPairs(): array
{
    $pairs = [];

    // Read every class file under app/Listeners
    foreach (glob(app_path('Listeners/*.php')) ?: [] as $file) {
        $class = 'App\\Listeners\\'.basename($file, '.php');

        // A file under app/Listeners that declares no class of its name is a defect
        if (! class_exists($class)) {
            throw new RuntimeException($file.' does not declare '.$class);
        }

        $reflection = new ReflectionClass($class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Take the methods discovery treated as handlers
            if (! str_starts_with($method->getName(), 'handle') || $method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            $type = ($method->getParameters()[0] ?? null)?->getType();
            $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

            // Pair the method with each event class its first parameter accepts
            foreach ($types as $named) {
                if ($named instanceof ReflectionNamedType && ! $named->isBuiltin()) {
                    $pairs[] = $named->getName().' => '.$class.'@'.$method->getName();
                }
            }
        }
    }

    sort($pairs);

    return $pairs;
}

/**
 * The (event, listener method) pairs the dispatcher holds for `App\Listeners`.
 *
 * A listener registered by class name alone runs its `handle()` method.
 *
 * @return list<string> Pairs written `Event => Listener@method`, sorted, with
 *                      a pair registered twice listed twice
 */
function eventServiceProviderRegisteredPairs(): array
{
    $pairs = [];

    foreach (Event::getRawListeners() as $event => $listeners) {
        foreach (mustBeArray($listeners, 'the '.$event.' listeners') as $listener) {
            // Keep the app's own listeners, named by class
            if (! is_string($listener) || ! str_starts_with($listener, 'App\\Listeners\\')) {
                continue;
            }

            $pairs[] = $event.' => '.(str_contains($listener, '@') ? $listener : $listener.'@handle');
        }
    }

    sort($pairs);

    return $pairs;
}

it('registers each listener in app/Listeners once for each event it handles', function (): void {
    $expected = eventServiceProviderExpectedPairs();

    // A listener file the reader found nothing in would make this pass vacuously
    expect(count($expected))->toBeGreaterThanOrEqual(count(glob(app_path('Listeners/*.php')) ?: []));

    expect(eventServiceProviderRegisteredPairs())->toBe($expected);
});

it('keeps listener discovery off', function (): void {
    // Discovery registers the same pairs a second time, after `$listen`
    expect((new EventServiceProvider(app()))->shouldDiscoverEvents())->toBeFalse();
});
