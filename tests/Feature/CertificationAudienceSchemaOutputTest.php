<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Term as TermFacade;
use Tests\TestCase;

/**
 * Mutation: 100% on App\Services\SchemaTemplates\SchemaOutputService (799 mutants, --covered-only).
 */
uses(TestCase::class);

it('resolves dot name with activity term overlay', function (): void {
    $person = EntryFacade::find('721e22af-79f0-4222-a9b6-51858016a855');
    expect($person)->not->toBeNull();

    $term = TermFacade::find('certifications::american-board-of-orthopaedic-surgery-orthopaedic-surgery');
    expect($term)->not->toBeNull();

    /** @var EntryContract $person */
    /** @var Term $term */
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod('resolveSingleReplicatorRowToValue');
    $method->setAccessible(true);

    $row = [
        'type' => 'antlers',
        'enabled' => true,
        'value' => [
            'code' => '{{ schema_primary_related_taxonomy_term.name }}',
            'mode' => 'html',
        ],
    ];

    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];

    $out = $method->invokeArgs(
        $service,
        [
            $row,
            $person,
            [],
            [],
            &$emittedEntryIds,
            &$emittedNodeIds,
            &$materializedFullNodeAtIds,
            ['activity' => collect([$term])],
            null,
        ]
    );

    expect($out)->toBeString();
    expect(is_string($out) ? $out : '')->toContain('Orthopaedic');
});

/**
 * Mirrors schema_objects `credentials-taxonomy-term-certification` audience row 1:
 * nested loops must emit when overlay carries the certification term.
 */
it('resolves audience antlers row with activity term overlay', function (): void {
    $person = EntryFacade::find('721e22af-79f0-4222-a9b6-51858016a855');
    expect($person)->not->toBeNull('Fixture person c-lowry-barnes missing.');

    $term = TermFacade::find('certifications::american-board-of-orthopaedic-surgery-orthopaedic-surgery');
    expect($term)->not->toBeNull('Fixture certification term missing.');

    /** @var EntryContract $person */
    /** @var Term $term */
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod('resolveSingleReplicatorRowToValue');
    $method->setAccessible(true);

    $row = [
        'type' => 'antlers',
        'enabled' => true,
        'value' => [
            'code' => <<<'ANTLERS'
{{ schema_primary_related_taxonomy_term.audience }}
{{ title }}
{{ /schema_primary_related_taxonomy_term.audience }}
ANTLERS,
            'mode' => 'html',
        ],
    ];

    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];

    $out = $method->invokeArgs(
        $service,
        [
            $row,
            $person,
            [],
            [],
            &$emittedEntryIds,
            &$emittedNodeIds,
            &$materializedFullNodeAtIds,
            ['activity' => collect([$term])],
            null,
        ]
    );

    expect($out)->toBeString();
    expect(trim(is_string($out) ? $out : ''))->not->toBe('');
    expect(strtolower(is_string($out) ? $out : ''))->toContain('orthopaedic');
});

/**
 * Same as live YAML: activity stored as bare slug (certifications replicator).
 */
it('resolves audience antlers row with activity slug string overlay', function (): void {
    $person = EntryFacade::find('721e22af-79f0-4222-a9b6-51858016a855');
    expect($person)->not->toBeNull();

    /** @var EntryContract $person */
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod('resolveSingleReplicatorRowToValue');
    $method->setAccessible(true);

    $row = [
        'type' => 'antlers',
        'enabled' => true,
        'value' => [
            'code' => <<<'ANTLERS'
{{ schema_primary_related_taxonomy_term.audience }}
{{ title }}
{{ /schema_primary_related_taxonomy_term.audience }}
ANTLERS,
            'mode' => 'html',
        ],
    ];

    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];

    $out = $method->invokeArgs(
        $service,
        [
            $row,
            $person,
            [],
            [],
            &$emittedEntryIds,
            &$emittedNodeIds,
            &$materializedFullNodeAtIds,
            ['activity' => 'american-board-of-orthopaedic-surgery-orthopaedic-surgery'],
            null,
        ]
    );

    expect($out)->toBeString();
    expect(trim(is_string($out) ? $out : ''))->not->toBe('');
    expect(strtolower(is_string($out) ? $out : ''))->toContain('orthopaedic');
});
