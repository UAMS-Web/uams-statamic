<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Tests\TestCase;

uses(TestCase::class);

covers(SchemaOutputService::class);

/**
 * Pins `replayEmitSideEffectsFromCachedNodeFragment()` of {@see SchemaOutputService}:
 * a cached node fragment leaves the shared emit maps as the live build would,
 * recording every full node in the tree and the schema_objects entry ids its
 * `@id` fragments carry.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceEmitReplayTest.php
 */
const SOS_REPLAY_UUID = '0f8fad5b-d9cb-469f-a165-70867728950e';

const SOS_REPLAY_OTHER_UUID = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

/**
 * Replay a fragment onto the given maps, and return the three maps after.
 *
 * @param  array<int, string>  $entryIds
 * @param  array<string, bool>  $nodeIds
 * @return array{0: array<int, string>, 1: array<string, bool>, 2: array<string, bool>}
 */
function sosReplay(mixed $fragment, array $entryIds = [], array $nodeIds = []): array
{
    $materialized = [];
    app(SchemaOutputService::class)->replayEmitSideEffectsFromCachedNodeFragment($fragment, $entryIds, $nodeIds, $materialized);

    return [$entryIds, $nodeIds, $materialized];
}

it('records every full node in a fragment tree, and the entry ids its fragments carry', function (): void {
    $fragment = [
        '@type' => 'Organization',
        '@id' => 'https://example.test/#'.SOS_REPLAY_UUID,
        'member' => [
            ['@type' => 'Person', '@id' => 'https://example.test/#'.SOS_REPLAY_OTHER_UUID.'-2', 'name' => 'A'],
            ['@type' => 'Person', '@id' => 'https://example.test/#named'],
        ],
        'name' => 'UAMS',
    ];

    expect(sosReplay($fragment, ['seen']))->toBe([
        ['seen', SOS_REPLAY_UUID, SOS_REPLAY_OTHER_UUID],
        ['https://example.test/#'.SOS_REPLAY_UUID => true, 'https://example.test/#'.SOS_REPLAY_OTHER_UUID.'-2' => true, 'https://example.test/#named' => true],
        ['https://example.test/#'.SOS_REPLAY_UUID => true, 'https://example.test/#'.SOS_REPLAY_OTHER_UUID.'-2' => true, 'https://example.test/#named' => true],
    ]);
});

it('records no node for a fragment without both an @id and a @type, or with an unusable @id', function (array $fragment): void {
    expect(sosReplay($fragment))->toBe([[], [], []]);
})->with([
    'no @type' => [['@id' => 'https://example.test/#'.SOS_REPLAY_UUID]],
    'no @id' => [['@type' => 'Thing']],
    'a numeric @id' => [['@type' => 'Thing', '@id' => 5]],
    'an empty @id' => [['@type' => 'Thing', '@id' => '']],
]);

it('appends an entry id already emitted only once', function (): void {
    expect(sosReplay(['@type' => 'Thing', '@id' => '#'.SOS_REPLAY_UUID], [SOS_REPLAY_UUID])[0])->toBe([SOS_REPLAY_UUID]);
});

it('ignores a scalar fragment', function (): void {
    expect(sosReplay('https://example.test/#'.SOS_REPLAY_UUID))->toBe([[], [], []]);
});
