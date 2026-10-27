<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * Coverage for the two identity-keyed memo keys in {@see SchemaOutputService} (UAMS-Web/uams-statamic-archive#1967).
 *
 * Both fell back to `spl_object_hash()` when an entity exposed no id. That hash is
 * a memory address: free an object and the next allocation is handed the same one,
 * so two unrelated id-less entities computed the SAME memo key and the second was
 * served the first's context bag or terms-augment payload. UAMS-Web/uams-statamic-archive#1909 fixed the identical
 * defect one layer over, in the persisted output cache, and named these as residue.
 *
 * The fix here could NOT be UAMS-Web/uams-statamic-archive#1909's — that one declines to cache, which costs a
 * recompose. These memos exist precisely to avoid that recompose on a path measured
 * at 12.5s versus 1.6s (UAMS-Web/uams-statamic-archive#1842). So they pin the object instead, which makes the
 * collision impossible by construction rather than merely detectable.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceMemoIdentityTest.php
 */
uses(TestCase::class);

/**
 * Call one of the private key builders.
 */
function schemaMemoKeyFor(string $method, object $subject): string
{
    $service = app(SchemaOutputService::class);

    $key = (new ReflectionMethod(SchemaOutputService::class, $method))->invoke($service, $subject);

    Assert::assertIsString($key);

    return $key;
}

/**
 * A freshly made entry, narrowed to the concrete model.
 *
 * Real objects rather than Mockery doubles throughout this file: Mockery retains
 * every mock until `Mockery::close()`, so `unset()` never frees one and the id is
 * never recycled — which would make every collision test here pass vacuously.
 */
function schemaMemoFixtureEntry(): EntryModel
{
    $entry = Entry::make();
    Assert::assertInstanceOf(EntryModel::class, $entry);

    return $entry;
}

it('rests on a premise that still holds: PHP recycles a freed object id', function (): void {
    // The negative control. Every assertion below is only meaningful because the
    // runtime really does reissue a freed object's id — if it stopped doing so,
    // the collision tests would pass without exercising anything.
    //
    // Real entries rather than Mockery doubles on purpose: Mockery retains every
    // mock in its container until `Mockery::close()`, so `unset()` does not free
    // one and the id is never recycled. UAMS-Web/uams-statamic-archive#1909's own control caught that.
    //
    // Deliberately allocated through the SAME helper the collision tests use, not
    // through a bare `new`. The premise that has to hold is "the objects those
    // tests build recycle ids", and allocating the control differently would
    // assert it of a path they do not take — an adversarial review of this file
    // argued the container's own `ReflectionClass` should claim the freed handle
    // first, which would make that divergence invisible. Measured here, it does
    // not: this passes, and removing the pin reds the two collision tests below,
    // which can only happen if the id really was reissued.
    $first = schemaMemoFixtureEntry();
    $firstId = spl_object_id($first);
    unset($first);

    $second = schemaMemoFixtureEntry();

    expect(spl_object_id($second))->toBe(
        $firstId,
        'A freed entry id was not reissued to the next one. Either PHP stopped '
        .'recycling ids, or something now allocates between the two (the container '
        .'reflecting on the class, say) and takes the freed handle first. Both '
        .'break the premise of the collision tests below, so fix this before '
        .'trusting them — do not simply relax it.'
    );
});

it('confirms the fixture really is id-less, so the fallback is the path under test', function (): void {
    // Without this, every test here could be exercising the id branch and passing
    // for the wrong reason.
    expect(schemaMemoFixtureEntry()->id())->not->toBeString();
});

it('does not hand a second id-less entity the first one\'s context memo key', function (): void {
    $first = schemaMemoFixtureEntry();
    $firstKey = schemaMemoKeyFor('antlersEntityContextCacheKey', $first);

    // Drop the only local reference. The pin inside the service is now the sole
    // thing keeping this object alive — which is exactly what stops its id being
    // reissued to the next allocation.
    unset($first);

    $second = schemaMemoFixtureEntry();
    $secondKey = schemaMemoKeyFor('antlersEntityContextCacheKey', $second);

    expect($secondKey)->not->toBe($firstKey);
});

it('does not hand a second id-less parent the first one\'s terms-augment memo key', function (): void {
    $first = schemaMemoFixtureEntry();
    $firstKey = schemaMemoKeyFor('memoKeyForTermsAugmentParent', $first);

    unset($first);

    $second = schemaMemoFixtureEntry();
    $secondKey = schemaMemoKeyFor('memoKeyForTermsAugmentParent', $second);

    expect($secondKey)->not->toBe($firstKey);
});

it('still memoizes an id-less entity rather than skipping the memo', function (string $method): void {
    // The acceptance criterion this guards: the fix must not become a
    // decline-to-cache change. One entity has to key stably to itself, or every
    // lookup misses and the compose path pays the recompose the memo exists to
    // avoid.
    $entity = schemaMemoFixtureEntry();

    expect(schemaMemoKeyFor($method, $entity))->toBe(schemaMemoKeyFor($method, $entity));
})->with([
    'antlers context' => ['antlersEntityContextCacheKey'],
    'terms augment parent' => ['memoKeyForTermsAugmentParent'],
]);

it('keys an entity by its id when it has one, never by identity', function (): void {
    // The pin is the FALLBACK. An entity with a real id must still key on it, so
    // the memo stays stable across the many objects Statamic builds for one entry.
    $entry = schemaMemoFixtureEntry();
    $entry->id('entry-with-an-id');

    expect(schemaMemoKeyFor('antlersEntityContextCacheKey', $entry))->toBe('entry-with-an-id');
});
