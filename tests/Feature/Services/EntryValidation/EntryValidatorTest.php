<?php

declare(strict_types=1);

use App\Services\EntryValidation\EntryValidationResult;
use App\Services\EntryValidation\EntryValidator;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Fields\Fields;
use Tests\Support\Doubles\IncompletePublishedEntriesWidgetTestDouble;
use Tests\Support\StacheIsolatedTestCase;

// The entry validation service on its own (#275): the errors it reports, the
// fields it judges visible under the blueprint's display conditions, and the
// widget output it feeds. Isolated Stache: every test persists a collection,
// its blueprint, and entries.
uses(StacheIsolatedTestCase::class);

const ENTRY_VALIDATOR_COLLECTION = 'entry_validator_service';

/**
 * Persist the service's collection with a blueprint built from the given fields.
 *
 * @param  array<string, array<string, mixed>>  $fields
 */
function entryValidatorCollection(array $fields): void
{
    /** @var StatamicCollection $collection */
    $collection = CollectionFacade::make(ENTRY_VALIDATOR_COLLECTION);
    $collection->title('Entry validator service');
    $collection->save();

    $blueprint = Blueprint::makeFromFields($fields);
    $blueprint->setHandle(ENTRY_VALIDATOR_COLLECTION);
    $blueprint->setNamespace('collections.'.ENTRY_VALIDATOR_COLLECTION);
    $blueprint->save();
}

/**
 * Save a published entry in the service's collection.
 *
 * @param  array<string, mixed>  $data
 */
function entryValidatorEntry(string $slug, array $data): StatamicEntry
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection(ENTRY_VALIDATOR_COLLECTION);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($data);
    $entry->save();

    return $entry;
}

/**
 * The entry's own blueprint.
 */
function entryValidatorBlueprint(StatamicEntry $entry): StatamicBlueprint
{
    $blueprint = $entry->blueprint();
    Assert::assertInstanceOf(StatamicBlueprint::class, $blueprint);

    return $blueprint;
}

/**
 * Validate an entry against its own blueprint through the service.
 */
function entryValidatorResult(StatamicEntry $entry): EntryValidationResult
{
    return app(EntryValidator::class)->validate($entry, entryValidatorBlueprint($entry));
}

/**
 * A blueprint whose `phone` is required only while `contact` is `phone`.
 *
 * The `required` + `sometimes` pairing is how a blueprint here declares a field
 * required only while visible (see docs/DECISIONS.md); without `sometimes`, the
 * rule also fires on the hidden field, which a separate case pins.
 *
 * @param  list<string>  $rules
 * @return array<string, array<string, mixed>>
 */
function entryValidatorConditionalFields(string $conditionKey, array $rules = ['required', 'sometimes']): array
{
    return [
        'contact' => ['type' => 'text'],
        'phone' => ['type' => 'text', 'validate' => $rules, $conditionKey => ['contact' => 'equals phone']],
    ];
}

it('does not report a conditionally required field its if condition hides', function (): void {
    entryValidatorCollection(entryValidatorConditionalFields('if'));

    $result = entryValidatorResult(entryValidatorEntry('kirk', ['title' => 'James Kirk', 'contact' => 'email']));

    expect($result->errors->keys())->toBe([])
        ->and($result->passes())->toBeTrue()
        ->and($result->visibleFields)->toContain('contact')
        ->and($result->visibleFields)->not->toContain('phone');
});

it('reports a conditionally required field once its if condition shows it', function (): void {
    entryValidatorCollection(entryValidatorConditionalFields('if'));

    $missing = entryValidatorResult(entryValidatorEntry('spock', ['title' => 'Spock', 'contact' => 'phone']));
    $filled = entryValidatorResult(entryValidatorEntry('mccoy', ['title' => 'Leonard McCoy', 'contact' => 'phone', 'phone' => '501-555-0100']));

    expect($missing->errors->keys())->toBe(['phone'])
        ->and($missing->passes())->toBeFalse()
        ->and($missing->attributes)->toHaveKey('phone')
        ->and($missing->visibleFields)->toContain('phone')
        ->and($filled->errors->keys())->toBe([])
        ->and($filled->visibleFields)->toContain('phone');
});

it('applies an unless condition on both sides of it', function (): void {
    entryValidatorCollection(entryValidatorConditionalFields('unless'));

    // `unless contact equals phone` hides the field exactly when the if form shows it
    $hidden = entryValidatorResult(entryValidatorEntry('scott', ['title' => 'Montgomery Scott', 'contact' => 'phone']));
    $shown = entryValidatorResult(entryValidatorEntry('uhura', ['title' => 'Nyota Uhura', 'contact' => 'email']));

    expect($hidden->errors->keys())->toBe([])
        ->and($hidden->visibleFields)->not->toContain('phone')
        ->and($shown->errors->keys())->toBe(['phone'])
        ->and($shown->visibleFields)->toContain('phone');
});

it('still reports a hidden field whose rules require it without sometimes', function (): void {
    // The value is left out, as the Control Panel leaves a hidden field out of its
    // submission, but a bare `required` rejects an absent key all the same
    entryValidatorCollection(entryValidatorConditionalFields('if', ['required']));

    $result = entryValidatorResult(entryValidatorEntry('rand', ['title' => 'Janice Rand', 'contact' => 'email']));

    expect($result->errors->keys())->toBe(['phone'])
        ->and($result->visibleFields)->not->toContain('phone');
});

it('lists the visible fields in blueprint order', function (): void {
    entryValidatorCollection([
        'contact' => ['type' => 'text'],
        'phone' => ['type' => 'text', 'if' => ['contact' => 'equals phone']],
        'email' => ['type' => 'text', 'if' => ['contact' => 'equals email']],
        'notes' => ['type' => 'textarea'],
    ]);

    $result = entryValidatorResult(entryValidatorEntry('sulu', ['title' => 'Hikaru Sulu', 'contact' => 'email']));

    expect(array_values(array_intersect($result->visibleFields, ['contact', 'phone', 'email', 'notes'])))
        ->toBe(['contact', 'email', 'notes']);
});

it('gives the widget the errors and labels it reports, for a failing and a passing entry', function (): void {
    entryValidatorCollection([
        'contact' => ['type' => 'text', 'display' => 'Contact method'],
        'phone' => ['type' => 'text', 'display' => 'Phone', 'validate' => ['required', 'sometimes'], 'if' => ['contact' => 'equals phone']],
        'rank' => ['type' => 'text', 'display' => 'Rank', 'validate' => ['required']],
    ]);

    $widget = new IncompletePublishedEntriesWidgetTestDouble;

    // Each case states its expected errors outright: the widget delegates to the
    // service, so comparing the two to each other alone could not catch a change
    $cases = [
        'failing' => [entryValidatorEntry('chekov', ['title' => 'Pavel Chekov', 'contact' => 'phone']), ['phone', 'rank']],
        'passing' => [entryValidatorEntry('chapel', ['title' => 'Christine Chapel', 'contact' => 'email', 'rank' => 'Nurse']), []],
    ];

    foreach ($cases as [$entry, $expectedErrors]) {
        $blueprint = entryValidatorBlueprint($entry);

        $fromWidget = $widget->exposeValidateEntry($entry, $blueprint);
        $fromService = app(EntryValidator::class)->validate($entry, $blueprint);

        expect($fromService->errors->keys())->toBe($expectedErrors)
            ->and($fromWidget['errors']->toArray())->toBe($fromService->errors->toArray())
            ->and($fromWidget['attributes'])->toBe($fromService->attributes);
    }
});

it('reports unreadable entry data as a single error and no visible fields', function (): void {
    entryValidatorCollection(['rank' => ['type' => 'text', 'validate' => ['required']]]);

    $entry = entryValidatorEntry('leslie', ['title' => 'Leslie']);
    $blueprint = entryValidatorBlueprint($entry);

    // The first fields() call, which sanitizes replicator values, succeeds; the
    // preProcess pass that follows throws, as malformed stored data does
    $realFields = $blueprint->fields();
    $calls = 0;
    $failing = Mockery::mock($blueprint)->makePartial();
    $failing->shouldReceive('fields')->andReturnUsing(function () use (&$calls, $realFields): Fields {
        $calls++;
        if ($calls > 1) {
            throw new RuntimeException('Malformed field data');
        }

        return $realFields;
    });

    Assert::assertInstanceOf(StatamicBlueprint::class, $failing);

    $result = app(EntryValidator::class)->validate($entry, $failing);

    expect($result->errors->keys())->toBe(['__pre_process__'])
        ->and($result->attributes)->toBe(['__pre_process__' => 'Entry data'])
        ->and($result->visibleFields)->toBe([]);
});
