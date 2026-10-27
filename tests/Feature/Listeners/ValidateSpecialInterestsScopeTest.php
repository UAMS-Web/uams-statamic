<?php

declare(strict_types=1);

use App\Listeners\ValidateSpecialInterestsScope;
use App\Services\ComputedFields\Ontology\ImpliedOntologyComputedValues;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/*
|--------------------------------------------------------------------------
| ValidateSpecialInterestsScope (UAMS-Web/uams-statamic-archive#1064 B — specialty-scoped save guard)
|--------------------------------------------------------------------------
|
| The special-interest fields on a Provider (people) / Location (clinical_locations)
| — the typed `special_interest_clinical_conditions` and
| `special_interest_clinical_services` pickers — must stay within the entity's own
| specialty-derived candidate set (the transitive overlap the UAMS-Web/uams-statamic-archive#1063 inference draws
| from). This listener enforces that on EVERY save path (Control Panel / REST API /
| programmatic import) over the UNION of both fields, so no out-of-scope selection can
| persist by any route. These mock the injected resolver to isolate the accept/reject
| decision from the overlap query (covered in the resolver test).
|
| @covers \App\Listeners\ValidateSpecialInterestsScope
|
| @command php artisan test --compact tests/Feature/Listeners/ValidateSpecialInterestsScopeTest.php
*/

/**
 * Split a combined special-interest selection into the two typed fields by id prefix:
 * a `svc*` id lands in `special_interest_clinical_services`, everything else (`cond*`
 * and any malformed non-string value) in `special_interest_clinical_conditions`. This
 * lets the existing single-list fixtures exercise the real two-field union the listener
 * reads. A non-array `$selected` (e.g. null) is routed whole to the conditions field so
 * the listener's non-array guard is tested on a real field read.
 *
 * @return array{0: mixed, 1: list<mixed>}
 */
function splitSpecialInterest(mixed $selected): array
{
    if (! is_array($selected)) {
        return [$selected, []];
    }

    $conditions = [];
    $services = [];
    foreach ($selected as $value) {
        if (is_string($value) && str_starts_with($value, 'svc')) {
            $services[] = $value;
        } else {
            $conditions[] = $value;
        }
    }

    return [$conditions, $services];
}

/**
 * An EntrySaving event over a mock entry with the given collection + combined
 * special-interest selection (split across the two typed fields). `$previouslySaved`
 * stubs the persisted "before" state the listener reads to compute the newly-added
 * delta (null = a new, not-yet-persisted entry).
 *
 * @param  ?array<int, mixed>  $previouslySaved
 */
function specialInterestsSavingEvent(string $collection, mixed $selected, ?string $id = 'entry-1', ?array $previouslySaved = null): EntrySaving
{
    [$conditions, $services] = splitSpecialInterest($selected);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')->andReturn($collection);
    $entry->shouldReceive('get')->with('special_interest_clinical_conditions', [])->andReturn($conditions);
    $entry->shouldReceive('get')->with('special_interest_clinical_services', [])->andReturn($services);
    $entry->shouldReceive('id')->andReturn($id);

    if (is_string($id)) {
        // The listener resolves the persisted entry to read its prior special interests.
        $existing = null;
        if ($previouslySaved !== null) {
            [$prevConditions, $prevServices] = splitSpecialInterest($previouslySaved);
            $existing = Mockery::mock(Entry::class);
            $existing->shouldReceive('get')->with('special_interest_clinical_conditions', [])->andReturn($prevConditions);
            $existing->shouldReceive('get')->with('special_interest_clinical_services', [])->andReturn($prevServices);
        }
        EntryFacade::shouldReceive('find')->with($id)->andReturn($existing);
    }

    return new EntrySaving($entry);
}

/**
 * An EntrySaving event with explicit per-field values (bypassing the prefix split),
 * for cases that must set the two typed fields independently. Models a new entry.
 */
function specialInterestsSavingEventFields(string $collection, mixed $conditions, mixed $services, ?string $id = 'entry-1'): EntrySaving
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')->andReturn($collection);
    $entry->shouldReceive('get')->with('special_interest_clinical_conditions', [])->andReturn($conditions);
    $entry->shouldReceive('get')->with('special_interest_clinical_services', [])->andReturn($services);
    $entry->shouldReceive('id')->andReturn($id);

    if (is_string($id)) {
        EntryFacade::shouldReceive('find')->with($id)->andReturn(null);
    }

    return new EntrySaving($entry);
}

/**
 * The listener with its resolver stubbed to return $candidates as the in-scope set (or never consulted).
 *
 * @param  array<int, string>  $candidates
 */
function specialInterestsListener(array $candidates, bool $expectResolverCall = true): ValidateSpecialInterestsScope
{
    $resolver = Mockery::mock(ImpliedOntologyComputedValues::class);

    if ($expectResolverCall) {
        $resolver->shouldReceive('candidateOverrideIds')->once()->andReturn($candidates);
    } else {
        $resolver->shouldReceive('candidateOverrideIds')->never();
    }

    return new ValidateSpecialInterestsScope($resolver);
}

it('accepts a selection fully within the specialty-derived candidate set', function () {
    // cond-a → conditions field, svc-a → services field: exercises BOTH typed fields.
    $event = specialInterestsSavingEvent('people', ['svc-a', 'cond-a']);
    $listener = specialInterestsListener(['svc-a', 'svc-b', 'cond-a']);

    // Nothing thrown → the in-scope selection is accepted. Asserted through
    // throwableFrom() rather than `->not->toThrow(…)`, which passes on a throwable
    // that is not the named class and so would stay green on a TypeError (UAMS-Web/uams-statamic-archive#1759).
    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('rejects a selection containing a C/T outside the candidate set', function () {
    $event = specialInterestsSavingEvent('people', ['svc-a', 'svc-x']);
    $listener = specialInterestsListener(['svc-a', 'cond-a']);

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, 'must be a condition or service associated');
});

it('rejects an out-of-scope Condition even when the Service selection is in scope', function () {
    // cond-x (conditions field) is out of scope; svc-a (services field) is in scope —
    // proves the conditions field is genuinely validated, not just the services field.
    $event = specialInterestsSavingEvent('people', ['cond-x', 'svc-a']);
    $listener = specialInterestsListener(['svc-a']);

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, 'must be a condition or service associated');
});

it('rejects any selection when the entity has no specialties (empty candidate set)', function () {
    // Empty candidate set → every selection is out of scope.
    $event = specialInterestsSavingEvent('clinical_locations', ['svc-a']);
    $listener = specialInterestsListener([]);

    expect(fn () => $listener->handle($event))->toThrow(Exception::class);
});

it('skips the scope check for a collection that does not author special interests', function () {
    // A clinical_conditions entry is ignored — the resolver is never consulted even
    // if it carries special-interest-shaped values.
    $event = specialInterestsSavingEvent('clinical_conditions', ['svc-a']);
    $listener = specialInterestsListener([], expectResolverCall: false);

    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('accepts (and issues no overlap query) when no special interest is authored', function () {
    $event = specialInterestsSavingEvent('people', []);
    $listener = specialInterestsListener([], expectResolverCall: false);

    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('accepts a null (unset) field without consulting the resolver', function () {
    // An entity that never set the field reads back a non-array value; the guard must
    // short-circuit rather than fall through to the array operations.
    $event = specialInterestsSavingEvent('people', null);
    $listener = specialInterestsListener([], expectResolverCall: false);

    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('scope-checks only the string entry-ids, ignoring any malformed non-string selection value', function () {
    // A well-formed selection is a list of id strings; a stray non-string is dropped
    // (Statamic's entries field drops those too), NOT treated as an out-of-scope
    // reference that would wrongly block the save.
    $event = specialInterestsSavingEvent('people', ['svc-a', 123, null]);
    $listener = specialInterestsListener(['svc-a']); // only svc-a is a candidate

    // 123 / null are ignored → only svc-a checked → in scope → no throw.
    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('reads the services field even when the conditions field holds a corrupt non-array value', function () {
    // A corrupt (non-array) conditions field must not stop the services field from being
    // scope-checked — the guard reads BOTH typed fields (continue past the bad field, not
    // break out of the loop). An out-of-scope service still throws.
    $event = specialInterestsSavingEventFields('people', null, ['svc-x']);
    $listener = specialInterestsListener(['svc-a']); // svc-x is out of scope

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, 'must be a condition or service associated');
});

it('enforces the scope on both Provider and Location saves', function (string $collection) {
    $event = specialInterestsSavingEvent($collection, ['svc-x']);
    $listener = specialInterestsListener(['svc-a']);

    expect(fn () => $listener->handle($event))->toThrow(Exception::class);
})->with([
    'provider' => ['people'],
    'location' => ['clinical_locations'],
]);

it('grandfathers an already-saved selection: an existing out-of-scope id is not re-checked', function () {
    // svc-old was in scope when picked but the entity has since narrowed its expertise,
    // so svc-old is no longer a candidate. This save re-persists svc-old AND adds the
    // in-scope svc-a. Only svc-a is checked; the save is NOT blocked by svc-old.
    $event = specialInterestsSavingEvent('people', ['svc-old', 'svc-a'], id: 'entry-1', previouslySaved: ['svc-old']);
    $listener = specialInterestsListener(['svc-a', 'cond-a']); // candidate set excludes svc-old

    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('does not scope-check (nor consult the resolver) when a save only removes selections', function () {
    // previously [svc-a, svc-b]; now [svc-a] — a pure removal. No newly-added ids, so the
    // resolver is never consulted and an unrelated save cannot be blocked.
    $event = specialInterestsSavingEvent('people', ['svc-a'], id: 'entry-1', previouslySaved: ['svc-a', 'svc-b']);
    $listener = specialInterestsListener([], expectResolverCall: false);

    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('still rejects a NEWLY-ADDED out-of-scope id even when other selections are grandfathered', function () {
    // svc-old is grandfathered (persisted, not re-checked); svc-x is newly added and out
    // of scope → the guarantee holds for additions.
    $event = specialInterestsSavingEvent('people', ['svc-old', 'svc-x'], id: 'entry-1', previouslySaved: ['svc-old']);
    $listener = specialInterestsListener(['svc-a']); // neither svc-old nor svc-x is a candidate; only svc-x is newly added

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, 'must be a condition or service associated');
});
