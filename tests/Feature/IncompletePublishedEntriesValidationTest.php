<?php

declare(strict_types=1);

use App\Widgets\IncompletePublishedEntries;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Validator;
use Illuminate\View\View;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\CollectionTree;
use Statamic\Structures\Page;
use Tests\Support\Doubles\IncompletePublishedEntriesWidgetTestDouble;
use Tests\Support\StacheIsolatedTestCase;

// Isolated Stache (UAMS-Web/uams-statamic-archive#1678): every test persists collections, blueprints, and entries.
uses(StacheIsolatedTestCase::class);

/**
 * Per-entry validation behavior of the incomplete-entries widget, pinned directly
 * through the test double rather than through a full scan (UAMS-Web/uams-statamic-archive#2544). None of these
 * paths is otherwise observable from the rendered widget without a much larger fixture.
 */
const VALIDATION_COLLECTION = 'incomplete_widget_validation';
const VALIDATION_OTHER_COLLECTION = 'incomplete_widget_validation_other';

/**
 * Persist a collection with a blueprint built from the given fields.
 *
 * @param  array<string, array<string, mixed>>  $fields
 * @param  callable(StatamicCollection): void|null  $configure  Adjusts the collection before it is saved
 */
function validationCollection(string $handle, array $fields, ?callable $configure = null): void
{
    /** @var StatamicCollection $collection */
    $collection = CollectionFacade::make($handle);
    $collection->title($handle);
    if ($configure !== null) {
        $configure($collection);
    }
    $collection->save();

    $blueprint = Blueprint::makeFromFields($fields);
    $blueprint->setHandle($handle);
    $blueprint->setNamespace('collections.'.$handle);
    $blueprint->save();
}

/**
 * Build a published entry, saving it unless told not to. An unsaved entry is
 * invisible to a uniqueness query, so it never collides with itself.
 *
 * @param  array<string, mixed>  $data
 */
function validationEntry(string $collection, string $slug, array $data, string $site = 'default', bool $save = true): StatamicEntry
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->locale($site);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($data);

    if ($save) {
        $entry->save();
    }

    return $entry;
}

function validationBlueprint(StatamicEntry $entry): StatamicBlueprint
{
    $blueprint = $entry->blueprint();
    Assert::assertInstanceOf(StatamicBlueprint::class, $blueprint);

    return $blueprint;
}

/**
 * @return array<string, array<string>>
 */
function validationErrors(StatamicEntry $entry): array
{
    return (new IncompletePublishedEntriesWidgetTestDouble)
        ->exposeValidateEntry($entry, validationBlueprint($entry))['errors']
        ->toArray();
}

it('skips a field hidden by its display condition but still validates it once shown', function (): void {
    validationCollection(VALIDATION_COLLECTION, [
        'intro' => ['type' => 'text'],
        'phone' => ['type' => 'text', 'validate' => ['sometimes', 'integer'], 'if' => ['intro' => 'equals yes']],
    ]);

    $hidden = validationEntry(VALIDATION_COLLECTION, 'hidden', ['title' => 'Hidden', 'intro' => 'no', 'phone' => 'not-a-number']);
    $shown = validationEntry(VALIDATION_COLLECTION, 'shown', ['title' => 'Shown', 'intro' => 'yes', 'phone' => 'not-a-number']);

    expect(validationErrors($hidden))->toBe([])
        ->and(array_keys(validationErrors($shown)))->toBe(['phone']);
});

/**
 * The fields the Control Panel entry editor would reject on save, rebuilt the way
 * `Statamic\Http\Controllers\CP\Collections\EntriesController::update()` validates:
 * the editor posts only its visible values (a field its display condition hides is
 * left out), and the controller validates them against the full blueprint (UAMS-Web/uams-statamic-archive#2588).
 *
 * @param  list<string>  $hiddenByCondition  Fields the editor would leave out of the save
 * @return list<string>
 */
function cpEditorRejectedFields(StatamicEntry $entry, array $hiddenByCondition): array
{
    /** @var Collection<string, mixed> $stored */
    $stored = $entry->data();

    /** @var array<string, mixed> $data */
    $data = $stored->except($hiddenByCondition)->all();
    $data['slug'] = $entry->slug();
    $data['published'] = $entry->published();

    /** @var StatamicBlueprint $blueprint */
    $blueprint = validationBlueprint($entry)->ensureField('published', ['type' => 'toggle']);

    /** @var Fields $fields */
    $fields = $blueprint->fields()->addValues($data);

    /** @var Statamic\Fields\Validator $statamicValidator */
    $statamicValidator = $fields->validator();

    /** @var Validator $validator */
    $validator = $statamicValidator->validator();

    /** @var list<string> $rejected */
    $rejected = $validator->errors()->keys();

    return $rejected;
}

it('fails a required field exactly when the entry editor would, whether its condition hides it or not', function (array $secret, string $intro, array $rejected): void {
    validationCollection(VALIDATION_COLLECTION, [
        'intro' => ['type' => 'text'],
        'secret' => array_merge(['type' => 'text', 'if' => ['intro' => 'equals yes']], $secret),
    ]);

    $entry = validationEntry(VALIDATION_COLLECTION, 'parity', ['title' => 'Parity', 'intro' => $intro]);
    $hidden = $intro === 'yes' ? [] : ['secret'];

    // The editor leaves a hidden value out, and `required` is an implicit rule, so it still
    // fails; `sometimes` is what lets a hidden field pass in both places
    expect(cpEditorRejectedFields($entry, $hidden))->toBe($rejected)
        ->and(array_keys(validationErrors($entry)))->toBe($rejected);
})->with([
    'hidden, plain required' => [['validate' => ['required']], 'no', ['secret']],
    'hidden, required|sometimes' => [['validate' => ['required', 'sometimes']], 'no', []],
    'shown and empty, plain required' => [['validate' => ['required']], 'yes', ['secret']],
]);

it('skips a replicator set field hidden within its row', function (): void {
    validationCollection(VALIDATION_COLLECTION, [
        'blocks' => [
            'type' => 'replicator',
            'sets' => [
                'main' => [
                    'sets' => [
                        'callout' => [
                            'fields' => [
                                ['handle' => 'style', 'field' => ['type' => 'text']],
                                ['handle' => 'phone', 'field' => ['type' => 'text', 'validate' => ['sometimes', 'integer'], 'if' => ['style' => 'equals phone']]],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $row = static fn (string $style): array => ['id' => 'row-'.$style, 'type' => 'callout', 'enabled' => true, 'style' => $style, 'phone' => 'not-a-number'];
    $hidden = validationEntry(VALIDATION_COLLECTION, 'hidden', ['title' => 'Hidden', 'blocks' => [$row('plain')]]);
    $shown = validationEntry(VALIDATION_COLLECTION, 'shown', ['title' => 'Shown', 'blocks' => [$row('phone')]]);

    expect(validationErrors($hidden))->toBe([])
        ->and(array_keys(validationErrors($shown)))->toBe(['blocks.0.phone']);
});

it('keeps title, slug, and published even when a condition hides their fields', function (): void {
    validationCollection(VALIDATION_COLLECTION, [
        'intro' => ['type' => 'text'],
        'title' => ['type' => 'text', 'validate' => ['required'], 'if' => ['intro' => 'equals yes']],
        'slug' => ['type' => 'slug', 'validate' => ['required'], 'if' => ['intro' => 'equals yes']],
        'published' => ['type' => 'toggle', 'validate' => ['accepted'], 'if' => ['intro' => 'equals yes']],
    ]);

    // Slug and published are entry properties, not data, so only the merge supplies them.
    $entry = validationEntry(VALIDATION_COLLECTION, 'kept', ['title' => 'Kept', 'intro' => 'no']);

    expect(validationErrors($entry))->toBe([]);
});

it('takes the title from the collection cascade, which the entry data does not carry', function (): void {
    validationCollection(VALIDATION_COLLECTION, [
        'title' => ['type' => 'text', 'validate' => ['required']],
    ], static function (StatamicCollection $collection): void {
        $collection->cascade(['title' => 'Injected title']);
    });

    $entry = validationEntry(VALIDATION_COLLECTION, 'cascaded', []);

    expect($entry->get('title'))->toBeNull()
        ->and(validationErrors($entry))->toBe([]);
});

it('scopes a unique value rule to the entry, its collection, and its site', function (): void {
    $unique = ['type' => 'text', 'validate' => ['new \Statamic\Rules\UniqueEntryValue({collection}, {id}, {site})']];
    validationCollection(VALIDATION_COLLECTION, ['code' => $unique], static function (StatamicCollection $collection): void {
        $collection->sites(['default', 'health']);
    });
    validationCollection(VALIDATION_OTHER_COLLECTION, ['code' => $unique]);

    // The entry itself is excluded by `{id}`.
    $solo = validationEntry(VALIDATION_COLLECTION, 'solo', ['title' => 'Solo', 'code' => 'solo']);

    // A match in another collection is out of scope through `{collection}`.
    validationEntry(VALIDATION_OTHER_COLLECTION, 'elsewhere', ['title' => 'Elsewhere', 'code' => 'other-collection']);
    $otherCollection = validationEntry(VALIDATION_COLLECTION, 'fresh-a', ['title' => 'Fresh A', 'code' => 'other-collection'], save: false);

    // A match on another site is out of scope through `{site}`.
    validationEntry(VALIDATION_COLLECTION, 'health-copy', ['title' => 'Health', 'code' => 'other-site'], site: 'health');
    $otherSite = validationEntry(VALIDATION_COLLECTION, 'fresh-b', ['title' => 'Fresh B', 'code' => 'other-site'], save: false);

    // Positive control: a real duplicate in the same collection and site fails.
    validationEntry(VALIDATION_COLLECTION, 'original', ['title' => 'Original', 'code' => 'taken']);
    $duplicate = validationEntry(VALIDATION_COLLECTION, 'fresh-c', ['title' => 'Fresh C', 'code' => 'taken'], save: false);

    expect(validationErrors($solo))->toBe([])
        ->and(validationErrors($otherCollection))->toBe([])
        ->and(validationErrors($otherSite))->toBe([])
        ->and(array_keys(validationErrors($duplicate)))->toBe(['code']);
});

it('adds the structure parent, which only the tree records', function (): void {
    validationCollection(VALIDATION_COLLECTION, [
        'parent' => ['type' => 'entries', 'max_items' => 1, 'collections' => [VALIDATION_COLLECTION]],
        'phone' => ['type' => 'text', 'validate' => ['sometimes', 'integer'], 'if' => ['parent' => 'empty']],
    ], static function (StatamicCollection $collection): void {
        $collection->structureContents(['root' => false, 'max_depth' => 3]);
    });

    $parent = validationEntry(VALIDATION_COLLECTION, 'parent', ['title' => 'Parent', 'phone' => 'not-a-number']);
    $child = validationEntry(VALIDATION_COLLECTION, 'child', ['title' => 'Child', 'phone' => 'not-a-number']);

    $collection = CollectionFacade::find(VALIDATION_COLLECTION);
    Assert::assertInstanceOf(StatamicCollection::class, $collection);
    $structure = $collection->structure();
    Assert::assertInstanceOf(CollectionStructure::class, $structure);
    $tree = $structure->in('default');
    Assert::assertInstanceOf(CollectionTree::class, $tree);
    $tree->tree([['entry' => $parent->id(), 'children' => [['entry' => $child->id()]]]]);
    $tree->save();

    $child = Entry::find($child->id());
    Assert::assertInstanceOf(StatamicEntry::class, $child);
    $structureParent = $child->parent();
    Assert::assertInstanceOf(Page::class, $structureParent);

    expect($child->get('parent'))->toBeNull()
        ->and($structureParent->id())->toBe($parent->id())
        ->and(validationErrors($child))->toBe([])
        ->and(array_keys(validationErrors($parent)))->toBe(['phone']);
});

it('returns field labels alongside the errors', function (): void {
    validationCollection(VALIDATION_COLLECTION, [
        'intro' => ['type' => 'text', 'display' => 'Introduction', 'validate' => ['required']],
    ]);
    $entry = validationEntry(VALIDATION_COLLECTION, 'labeled', ['title' => 'Labelled']);

    $result = (new IncompletePublishedEntriesWidgetTestDouble)->exposeValidateEntry($entry, validationBlueprint($entry));

    expect($result['attributes']['intro'] ?? null)->toBe('Introduction')
        ->and(array_keys($result['errors']->toArray()))->toBe(['intro']);
});

it('coerces a malformed replicator that follows another field', function (): void {
    validationCollection(VALIDATION_COLLECTION, [
        'intro' => ['type' => 'text'],
        'blocks' => ['type' => 'replicator', 'sets' => []],
    ]);
    $entry = validationEntry(VALIDATION_COLLECTION, 'legacy', ['title' => 'Legacy']);

    $sanitized = (new IncompletePublishedEntriesWidgetTestDouble)
        ->exposeSanitizeMalformedReplicatorValues(validationBlueprint($entry), ['intro' => 'kept', 'blocks' => 'legacy string']);

    expect($sanitized)->toBe(['intro' => 'kept', 'blocks' => []]);
});

it('coerces every malformed replicator and keeps only rows with a string set type', function (): void {
    validationCollection(VALIDATION_COLLECTION, [
        'first' => ['type' => 'replicator', 'sets' => []],
        'second' => ['type' => 'replicator', 'sets' => []],
    ]);
    $entry = validationEntry(VALIDATION_COLLECTION, 'legacy-rows', ['title' => 'Legacy rows']);

    $sanitized = (new IncompletePublishedEntriesWidgetTestDouble)->exposeSanitizeMalformedReplicatorValues(validationBlueprint($entry), [
        'first' => 'legacy string',
        'second' => ['legacy string', ['no' => 'type'], ['type' => 5], ['type' => 'callout', 'note' => 'Kept.']],
    ]);

    expect($sanitized)->toBe(['first' => [], 'second' => [['type' => 'callout', 'note' => 'Kept.']]]);
});

it('leaves a Bard field with sets to the ordinary field path', function (): void {
    validationCollection(VALIDATION_COLLECTION, [
        'body' => [
            'type' => 'bard',
            'sets' => [
                'main' => [
                    'sets' => [
                        'callout' => [
                            'fields' => [
                                ['handle' => 'note', 'field' => ['type' => 'text']],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    // Every ProseMirror node carries a `type`, so the replicator row filter would strip
    // `content` and `attrs`, and Bard's set rules then read the missing `attrs`.
    $entry = validationEntry(VALIDATION_COLLECTION, 'bard', ['title' => 'Bard', 'body' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Lead.']]],
        ['type' => 'set', 'attrs' => ['id' => 'set-1', 'values' => ['type' => 'callout', 'note' => 'Aside.']]],
    ]]);

    expect(validationErrors($entry))->toBe([]);
});

it('points the shared collection blueprint at the entry being validated', function (): void {
    validationCollection(VALIDATION_COLLECTION, ['intro' => ['type' => 'text']]);
    $first = validationEntry(VALIDATION_COLLECTION, 'first', ['title' => 'First']);
    $second = validationEntry(VALIDATION_COLLECTION, 'second', ['title' => 'Second']);

    // The collection hands every entry one blueprint instance, parented to whichever
    // entry resolved it last: here the second one, saved after the first.
    $blueprint = validationBlueprint($first);
    $parentBefore = $blueprint->parent();
    Assert::assertInstanceOf(StatamicEntry::class, $parentBefore);

    (new IncompletePublishedEntriesWidgetTestDouble)->exposeValidateEntry($first, $blueprint);
    $parentAfter = $blueprint->parent();
    Assert::assertInstanceOf(StatamicEntry::class, $parentAfter);

    expect($parentBefore->id())->toBe($second->id())
        ->and($parentAfter->id())->toBe($first->id());
});

it('reports a single page when the list is empty', function (): void {
    $widget = new IncompletePublishedEntries;
    $widget->setConfig([]);
    Cache::forever($widget->cacheKey(), ['rows' => [], 'scan' => ['scanned' => 0, 'total' => 0, 'truncated' => false], 'computed_at' => time()]);

    $view = $widget->html();
    Assert::assertInstanceOf(View::class, $view);
    $pagination = $view->getData()['pagination'];
    Assert::assertIsArray($pagination);

    expect($pagination['last_page'])->toBe(1)
        ->and($pagination['current_page'])->toBe(1);
});

it('keeps validating the fields that follow a replicator', function (): void {
    validationCollection(VALIDATION_COLLECTION, [
        'blocks' => ['type' => 'replicator', 'sets' => []],
        'phone' => ['type' => 'text', 'validate' => ['sometimes', 'integer']],
    ]);
    $entry = validationEntry(VALIDATION_COLLECTION, 'after-replicator', ['title' => 'After', 'blocks' => [], 'phone' => 'not-a-number']);

    expect(array_keys(validationErrors($entry)))->toBe(['phone']);
});

it('evaluates condition blocks by their key', function (array $config, array $values, bool $visible): void {
    /** @var array<string, mixed> $values */
    $field = new Field('phone', ['type' => 'text', ...$config]);

    expect((new IncompletePublishedEntriesWidgetTestDouble)->exposeFieldPassesConditions($field, $values))->toBe($visible);
})->with([
    'no conditions' => [[], [], true],
    'if passes' => [['if' => ['intro' => 'equals yes']], ['intro' => 'yes'], true],
    'if fails' => [['if' => ['intro' => 'equals yes']], ['intro' => 'no'], false],
    'if needs every condition' => [['if' => ['intro' => 'equals yes', 'other' => 'equals yes']], ['intro' => 'no', 'other' => 'yes'], false],
    'if_any needs one condition' => [['if_any' => ['intro' => 'equals yes', 'other' => 'equals yes']], ['intro' => 'no', 'other' => 'yes'], true],
    'unless hides on a match' => [['unless' => ['intro' => 'equals yes']], ['intro' => 'yes'], false],
    'unless shows otherwise' => [['unless' => ['intro' => 'equals yes']], ['intro' => 'no'], true],
    'hide_when hides on a match' => [['hide_when' => ['intro' => 'equals yes']], ['intro' => 'yes'], false],
    'hide_when shows otherwise' => [['hide_when' => ['intro' => 'equals yes']], ['intro' => 'no'], true],
]);

it('evaluates a single condition', function (string $rhs, mixed $lhs, bool $passes): void {
    expect((new IncompletePublishedEntriesWidgetTestDouble)->exposeEvaluateCondition('intro', $rhs, ['intro' => $lhs]))->toBe($passes);
})->with([
    'padded empty' => [' empty ', '', true],
    'empty string is empty' => ['empty', '', true],
    'empty list is empty' => ['empty', [], true],
    'null is empty' => ['empty', null, true],
    'text is not empty' => ['empty', 'text', false],
    'equals false as a boolean' => ['equals false', false, true],
    'equals true as a boolean' => ['equals true', 1, true],
    'equals compares loosely' => ['equals 5', 5, true],
    'equals mismatch' => ['equals 5', 6, false],
    'equals trims a NUL the regex keeps' => ["equals \0yes", 'yes', true],
    'null literal' => ['null', null, true],
    'null literal mismatch' => ['null', '', false],
    'true literal' => ['true', 'on', true],
    'false literal' => ['false', '', true],
    'false literal mismatch' => ['false', 'on', false],
    'not empty' => ['not empty', 'text', true],
    'not empty on empty' => ['not empty', '', false],
    'not empty spelled with extra space' => ['not  empty', '', false],
    'not a value' => ['not draft', 'live', true],
    'not a matching value' => ['not draft', 'draft', false],
    'not trims a NUL the regex keeps' => ["not \0draft", 'draft', false],
    'contains in a list' => ['contains red', ['red', 'blue'], true],
    'contains missing from a list' => ['contains green', ['red', 'blue'], false],
    'contains a substring' => ['contains ed', 'red', true],
    'contains a missing substring' => ['contains xy', 'red', false],
    'contains_any in a list' => ['contains_any green, blue', ['red', 'blue'], true],
    'contains_any missing from a list' => ['contains_any green, pink', ['red', 'blue'], false],
    'contains_any in a string, ignoring case' => ['contains_any green, RED', 'dark red', true],
    'contains_any quotes each option' => ['contains_any a.c', 'abc', false],
    'unknown operator compares loosely' => ['draft', 'draft', true],
    'unknown operator compares a number loosely' => ['5', 5, true],
    'not compares loosely' => ['not 5', 5, false],
    'contains_any matches its first option' => ['contains_any red, pink', ['red'], true],
    'contains_any on a number' => ['contains_any 5', 5, true],
    'contains_any never matches an object' => ['contains_any a', new stdClass, false],
    'contains trims a NUL the regex keeps' => ["contains \0red", ['red'], true],
    'contains on a number' => ['contains 5', 5, true],
    'contains never matches an object' => ['contains a', new stdClass, false],
]);

it('returns a numeric or missing field handle as a string', function (mixed $handle, string $expected): void {
    expect((new IncompletePublishedEntriesWidgetTestDouble)->exposeFieldHandle(new Field($handle, ['type' => 'text'])))->toBe($expected);
})->with([
    'string' => ['intro', 'intro'],
    'integer' => [123, '123'],
    'null' => [null, ''],
]);

it('drops a hidden nested replicator, even beside a visible handle it equals loosely', function (): void {
    $blocks = new Field('blocks', [
        'type' => 'replicator',
        'sets' => ['main' => ['sets' => ['stack' => ['fields' => [
            ['handle' => '10', 'field' => ['type' => 'text']],
            ['handle' => '1e1', 'field' => [
                'type' => 'replicator',
                'if' => ['10' => 'equals shown'],
                'sets' => ['main' => ['sets' => ['item' => ['fields' => [
                    ['handle' => 'label', 'field' => ['type' => 'text']],
                ]]]]],
            ]],
        ]]]]],
    ]);

    // PHP turns the '10' key into an integer, which the handles compare against as a string.
    /** @var array<int, array<string, mixed>> $rows */
    $rows = [[
        'type' => 'stack', '_id' => 'row-1', 'enabled' => true, '10' => 'hidden',
        '1e1' => [['type' => 'item', '_id' => 'item-1', 'enabled' => true, 'label' => 'Hidden.']],
    ]];

    expect((new IncompletePublishedEntriesWidgetTestDouble)->exposeFilterReplicatorRowsToVisibleFields($rows, $blocks))
        ->toBe([['type' => 'stack', '_id' => 'row-1', 'enabled' => true, '10' => 'hidden']]);
});

it('filters replicator rows to visible set fields, recursing into visible nested replicators', function (): void {
    $blocks = new Field('blocks', [
        'type' => 'replicator',
        'sets' => ['main' => ['sets' => ['stack' => ['fields' => [
            ['handle' => 'note', 'field' => ['type' => 'text', 'if' => ['mode' => 'equals boxed']]],
            ['handle' => 'mode', 'field' => ['type' => 'text']],
            ['handle' => 'items', 'field' => [
                'type' => 'replicator',
                'sets' => ['main' => ['sets' => ['item' => ['fields' => [
                    ['handle' => 'label', 'field' => ['type' => 'text']],
                    ['handle' => 'url', 'field' => ['type' => 'text', 'if' => ['label' => 'equals link']]],
                ]]]]],
            ]],
        ]]]]],
    ]);

    $rows = [
        // A row with no set type passes through, and the rows after it are still filtered.
        ['no-type' => 'Passed through.'],
        [
            'type' => 'stack', '_id' => 'row-1', 'enabled' => true, 'mode' => 'plain', 'note' => 'Hidden.', 'unknown' => 'Dropped.',
            'items' => [['type' => 'item', '_id' => 'item-1', 'enabled' => true, 'label' => 'text', 'url' => 'Hidden.']],
        ],
        // No `items` key: the nested replicator has no value to recurse into.
        ['type' => 'stack', '_id' => 'row-2', 'enabled' => false, 'mode' => 'plain'],
    ];

    expect((new IncompletePublishedEntriesWidgetTestDouble)->exposeFilterReplicatorRowsToVisibleFields($rows, $blocks))->toBe([
        ['no-type' => 'Passed through.'],
        [
            'type' => 'stack', '_id' => 'row-1', 'enabled' => true, 'mode' => 'plain',
            'items' => [['type' => 'item', '_id' => 'item-1', 'enabled' => true, 'label' => 'text']],
        ],
        ['type' => 'stack', '_id' => 'row-2', 'enabled' => false, 'mode' => 'plain'],
    ]);
});

it('prefers the stored parent over the tree only when revisions are enabled', function (bool $revisions): void {
    config(['statamic.editions.pro' => true, 'statamic.revisions.enabled' => true]);
    validationCollection(VALIDATION_COLLECTION, [
        'parent' => ['type' => 'entries', 'max_items' => 1, 'collections' => [VALIDATION_COLLECTION]],
    ], static function (StatamicCollection $collection) use ($revisions): void {
        $collection->structureContents(['root' => false, 'max_depth' => 3]);
        $collection->revisionsEnabled($revisions);
    });

    $treeParent = validationEntry(VALIDATION_COLLECTION, 'tree-parent', ['title' => 'Tree parent']);
    $child = validationEntry(VALIDATION_COLLECTION, 'child', ['title' => 'Child', 'parent' => 'stored-parent-id']);

    $collection = CollectionFacade::find(VALIDATION_COLLECTION);
    Assert::assertInstanceOf(StatamicCollection::class, $collection);
    $structure = $collection->structure();
    Assert::assertInstanceOf(CollectionStructure::class, $structure);
    $tree = $structure->in('default');
    Assert::assertInstanceOf(CollectionTree::class, $tree);
    $tree->tree([['entry' => $treeParent->id(), 'children' => [['entry' => $child->id()]]]]);
    $tree->save();

    $child = Entry::find($child->id());
    Assert::assertInstanceOf(StatamicEntry::class, $child);

    $values = (new IncompletePublishedEntriesWidgetTestDouble)->exposeExtractEntryValuesForValidation($child);

    expect($child->revisionsEnabled())->toBe($revisions)
        ->and($values['parent'] ?? null)->toBe($revisions ? ['stored-parent-id'] : [$treeParent->id()]);
})->with(['revisions on' => [true], 'revisions off' => [false]]);
