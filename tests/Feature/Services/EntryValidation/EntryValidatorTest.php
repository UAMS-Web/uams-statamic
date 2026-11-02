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

// Isolated Stache: every test persists a collection, its blueprint, and entries
uses(StacheIsolatedTestCase::class);

/**
 * The entry validation service on its own (#275): the errors it reports, the
 * fields it judges visible under the blueprint's display conditions, and its
 * agreement with the Incomplete Published Entries widget it was extracted from.
 */
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
 * Validate an entry against its own blueprint through the service.
 */
function entryValidatorResult(StatamicEntry $entry): EntryValidationResult
{
    $blueprint = $entry->blueprint();
    Assert::assertInstanceOf(StatamicBlueprint::class, $blueprint);

    return app(EntryValidator::class)->validate($entry, $blueprint);
}

/**
 * A blueprint whose `phone` is required only while `contact` is `phone`.
 *
 * @return array<string, array<string, mixed>>
 */
function entryValidatorConditionalFields(string $conditionKey): array
{
    return [
        'contact' => ['type' => 'text'],
        'phone' => ['type' => 'text', 'validate' => ['required'], $conditionKey => ['contact' => 'equals phone']],
    ];
}

it('does not report a field its if condition hides, though the field is required', function (): void {
    entryValidatorCollection(entryValidatorConditionalFields('if'));

    $result = entryValidatorResult(entryValidatorEntry('kirk', ['title' => 'James Kirk', 'contact' => 'email']));

    expect($result->passes())->toBeTrue()
        ->and($result->errors->keys())->toBe([])
        ->and($result->visibleFields)->toContain('contact')
        ->and($result->visibleFields)->not->toContain('phone');
});

it('reports a conditionally required field once its if condition shows it', function (): void {
    entryValidatorCollection(entryValidatorConditionalFields('if'));

    $missing = entryValidatorResult(entryValidatorEntry('spock', ['title' => 'Spock', 'contact' => 'phone']));
    $filled = entryValidatorResult(entryValidatorEntry('mccoy', ['title' => 'Leonard McCoy', 'contact' => 'phone', 'phone' => '501-555-0100']));

    expect($missing->passes())->toBeFalse()
        ->and($missing->errors->keys())->toBe(['phone'])
        ->and($missing->attributes)->toHaveKey('phone')
        ->and($missing->visibleFields)->toContain('phone')
        ->and($filled->passes())->toBeTrue()
        ->and($filled->visibleFields)->toContain('phone');
});

it('applies an unless condition on both sides of it', function (): void {
    entryValidatorCollection(entryValidatorConditionalFields('unless'));

    // `unless contact equals phone` hides the field exactly when the if form shows it
    $hidden = entryValidatorResult(entryValidatorEntry('scott', ['title' => 'Montgomery Scott', 'contact' => 'phone']));
    $shown = entryValidatorResult(entryValidatorEntry('uhura', ['title' => 'Nyota Uhura', 'contact' => 'email']));

    expect($hidden->passes())->toBeTrue()
        ->and($hidden->visibleFields)->not->toContain('phone')
        ->and($shown->errors->keys())->toBe(['phone'])
        ->and($shown->visibleFields)->toContain('phone');
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

it('reports the same errors and labels as the widget, for a passing and a failing entry', function (): void {
    entryValidatorCollection([
        'contact' => ['type' => 'text', 'display' => 'Contact method'],
        'phone' => ['type' => 'text', 'display' => 'Phone', 'validate' => ['required'], 'if' => ['contact' => 'equals phone']],
        'rank' => ['type' => 'text', 'display' => 'Rank', 'validate' => ['required']],
    ]);

    $widget = new IncompletePublishedEntriesWidgetTestDouble;

    foreach ([
        entryValidatorEntry('chekov', ['title' => 'Pavel Chekov', 'contact' => 'phone']),
        entryValidatorEntry('chapel', ['title' => 'Christine Chapel', 'contact' => 'email', 'rank' => 'Nurse']),
    ] as $entry) {
        $blueprint = $entry->blueprint();
        Assert::assertInstanceOf(StatamicBlueprint::class, $blueprint);

        $fromWidget = $widget->exposeValidateEntry($entry, $blueprint);
        $fromService = app(EntryValidator::class)->validate($entry, $blueprint);

        expect($fromService->errors->toArray())->toBe($fromWidget['errors']->toArray())
            ->and($fromService->attributes)->toBe($fromWidget['attributes']);
    }

    // The two entries really do differ, so the agreement covers both outcomes
    expect(entryValidatorResult(Entry::query()->where('slug', 'chekov')->first())->errors->keys())->toBe(['phone', 'rank'])
        ->and(entryValidatorResult(Entry::query()->where('slug', 'chapel')->first())->passes())->toBeTrue();
});

it('reports unreadable entry data as a single error and no visible fields', function (): void {
    entryValidatorCollection(['rank' => ['type' => 'text', 'validate' => ['required']]]);

    $entry = entryValidatorEntry('rand', ['title' => 'Janice Rand']);
    $blueprint = $entry->blueprint();
    Assert::assertInstanceOf(StatamicBlueprint::class, $blueprint);

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

    $result = app(EntryValidator::class)->validate($entry, $failing);

    expect($result->errors->keys())->toBe(['__pre_process__'])
        ->and($result->attributes)->toBe(['__pre_process__' => 'Entry data'])
        ->and($result->visibleFields)->toBe([]);
});
