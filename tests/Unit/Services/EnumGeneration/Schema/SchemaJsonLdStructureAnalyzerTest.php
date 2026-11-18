<?php

declare(strict_types=1);

use App\Services\EnumGeneration\Schema\SchemaJsonLdStructureAnalyzer;

covers(SchemaJsonLdStructureAnalyzer::class);

/**
 * In-process tests for `SchemaJsonLdStructureAnalyzer`: the first pass over a
 * `@graph` list (ids, prefixes, `@type` values, keys per type), the recursive
 * walk's per-key statistics, both report renderings, and `runCli()`, which the
 * entry script `scripts/analyze-schema-jsonld.php` calls. That script's own
 * error paths are covered by `SchemaJsonLdStructureAnalyzerCliTest`.
 *
 * The report expectations were read from the analyzer before any change to it,
 * so they pin its behavior rather than restate a specification.
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/Schema/SchemaJsonLdStructureAnalyzerTest.php
 */

/**
 * Analyze the given data with a fresh analyzer and return it.
 */
function analyzedStructure(mixed $data): SchemaJsonLdStructureAnalyzer
{
    $analyzer = new SchemaJsonLdStructureAnalyzer;
    $analyzer->analyze($data);

    return $analyzer;
}

/**
 * The `_meta` block of a report, without the fixed ordering note.
 *
 * @return array<string, mixed>
 */
function structureMeta(SchemaJsonLdStructureAnalyzer $analyzer): array
{
    /** @var array<string, mixed> $meta */
    $meta = $analyzer->getReport()['_meta'];
    unset($meta['ordering_note']);

    return $meta;
}

/**
 * One entry of a report, narrowed to an array so a test can read its fields.
 *
 * @param  array<string, mixed>  $report  a report from `getReport()`
 * @return array<array-key, mixed>
 */
function structureEntry(array $report, string $key): array
{
    $entry = $report[$key] ?? null;
    expect($entry)->toBeArray();

    return is_array($entry) ? $entry : [];
}

it('records each string @id in order and counts nodes per prefix', function (): void {
    $analyzer = analyzedStructure([
        ['@id' => 'schema:A'],
        ['@id' => 'schema:B'],
        ['@id' => 'https://schema.org/C'],
        ['@id' => 'http://schema.org/E'],
        ['@id' => 'bibo:D'],
        ['@id' => 'urn/a/b'],
        ['@id' => 'plain'],
        ['@id' => 5],
        ['x' => 1],
    ]);

    expect($analyzer->getAllIds())->toBe([
        'schema:A', 'schema:B', 'https://schema.org/C', 'http://schema.org/E', 'bibo:D', 'urn/a/b', 'plain',
    ])->and(structureMeta($analyzer))->toBe([
        'all_ids_count' => 7,
        'id_prefix_distribution' => [
            'bibo:' => 1,
            'https://schema.org/' => 2,
            'plain' => 1,
            'schema:' => 2,
            'urn/a/' => 1,
        ],
        'distinct_type_values' => [],
        'keys_per_node_type' => [],
        'language_tags_seen' => [],
    ]);
});

it('collects @type values from strings, lists and @id references, and ignores anything else', function (): void {
    $analyzer = analyzedStructure([
        ['@type' => 'b:T'],
        ['@type' => ['a:T', 5, 'c:T']],
        ['@type' => ['@id' => 'd:T']],
        ['@type' => ['@id' => 5]],
        ['@type' => null],
        ['@type' => 7],
        ['@type' => ['k' => 'v']],
    ]);

    $meta = structureMeta($analyzer);

    expect($meta['distinct_type_values'])->toBe(['a:T', 'b:T', 'c:T', 'd:T'])
        ->and($meta['keys_per_node_type'])->toBe([
            'b:T' => ['@type' => 1],
            'a:T' => ['@type' => 1],
            'c:T' => ['@type' => 1],
            'd:T' => ['@type' => 1],
        ]);
});

it('counts the keys on each node type, sorted, without @context', function (): void {
    $analyzer = analyzedStructure([
        ['@type' => 'z:T', '@context' => ['x' => 'y'], 'b' => 1, 'a' => 2],
        ['@type' => ['z:T', 'y:T'], 'a' => 3],
    ]);

    expect(structureMeta($analyzer)['keys_per_node_type'])->toBe([
        'z:T' => ['@type' => 2, 'a' => 2, 'b' => 1],
        'y:T' => ['@type' => 1, 'a' => 1],
    ])->and($analyzer->getReport()['(root)[]'])->toBe([
        'value_types' => ['array (associative)' => 2],
        'array_element_types' => [],
        'object_shapes' => ['object(@context,@type,a,b)' => 1, 'object(@type,a)' => 1],
        'count' => 2,
        'examples' => [
            ['@type' => 'z:T', '@context' => ['x' => 'y'], 'b' => 1, '...' => '(truncated)'],
            ['@type' => ['z:T', 'y:T'], 'a' => 3],
        ],
        'nested_keys' => ['@type', 'b', 'a'],
        'array_length_min' => 2,
        'array_length_max' => 4,
    ]);
});

it('starts each analysis from nothing', function (): void {
    $analyzer = analyzedStructure([
        ['@id' => 'schema:A', '@type' => 'T', 'v' => ['@language' => 'en']],
    ]);
    $analyzer->analyze([]);

    expect($analyzer->getAllIds())->toBe([])
        ->and(structureMeta($analyzer))->toBe([
            'all_ids_count' => 0,
            'id_prefix_distribution' => [],
            'distinct_type_values' => [],
            'keys_per_node_type' => [],
            'language_tags_seen' => [],
        ])
        ->and(array_keys($analyzer->getReport()))->toBe(['_meta', '(root)']);
});

it('walks an associative root without a first pass, naming its keys without a root prefix', function (): void {
    $analyzer = analyzedStructure([
        '@id' => 'schema:R',
        '@language' => 'en',
        'a' => ['b' => 1],
        '@context' => ['s' => 't'],
    ]);
    $report = $analyzer->getReport();

    expect($analyzer->getAllIds())->toBe([])
        ->and(array_keys($report))->toBe(['_meta', '(root)', '@id', '@language', 'a', 'a.b'])
        ->and(structureEntry($report, '_meta')['language_tags_seen'])->toBe([])
        ->and($report['(root)'])->toBe([
            'value_types' => ['array (associative)' => 1],
            'array_element_types' => [],
            'object_shapes' => ['object(@context,@id,@language,a)' => 1],
            'count' => 1,
            'examples' => [['@id' => 'schema:R', '@language' => 'en', 'a' => ['b' => 1], '...' => '(truncated)']],
            'nested_keys' => ['@id', '@language', 'a'],
            'array_length_min' => 1,
            'array_length_max' => 1,
        ])
        ->and($report['a'])->toBe([
            'value_types' => ['array (associative)' => 1],
            'array_element_types' => [],
            'object_shapes' => ['object(b)' => 1],
            'count' => 1,
            'examples' => [['b' => 1]],
            'nested_keys' => ['b'],
            'array_length_min' => 1,
            'array_length_max' => 1,
        ])
        ->and($report['a.b'])->toBe([
            'value_types' => ['integer' => 1],
            'array_element_types' => [],
            'object_shapes' => [],
            'count' => 1,
            'examples' => [1],
            'nested_keys' => null,
        ]);
});

it('walks object values like associative arrays, without @context', function (): void {
    $report = analyzedStructure((object) ['a' => 1, '@context' => 'c', 'o' => (object) ['p' => true]])->getReport();

    expect(array_keys($report))->toBe(['_meta', '(root)', 'a', 'o', 'o.p'])
        ->and($report['(root)'])->toBe([
            'value_types' => ['object' => 1],
            'array_element_types' => [],
            'object_shapes' => ['object(@context,a,o)' => 1],
            'count' => 1,
            'examples' => ['object'],
            'nested_keys' => ['a', 'o'],
        ])
        ->and($report['o'])->toBe([
            'value_types' => ['object' => 1],
            'array_element_types' => [],
            'object_shapes' => ['object(p)' => 1],
            'count' => 1,
            'examples' => ['object'],
            'nested_keys' => ['p'],
        ])
        ->and(structureEntry($report, 'o.p')['value_types'])->toBe(['boolean' => 1]);
});

it('types each list element, and gives objects in a list their shape on the element key', function (): void {
    $report = analyzedStructure([['k' => [(object) ['p' => 1], ['q' => 2]]]])->getReport();

    expect($report['(root)[].k'])->toBe([
        'value_types' => ['array (indexed)' => 1],
        'array_element_types' => ['object' => 1, 'array (associative)' => 1],
        'object_shapes' => [],
        'count' => 1,
        'examples' => [['object', ['q' => 2]]],
        'nested_keys' => null,
        'array_length_min' => 2,
        'array_length_max' => 2,
    ])->and(structureEntry($report, '(root)[].k[]')['object_shapes'])->toBe(['object(p)' => 1, 'object(q)' => 1]);
});

it('tracks the shortest and longest array, counting an @id object as one', function (array $values, int $min, int $max): void {
    $graph = array_map(static fn (mixed $value): array => ['k' => $value], $values);
    $report = analyzedStructure($graph)->getReport();

    expect(structureEntry($report, '(root)[].k')['array_length_min'])->toBe($min)
        ->and(structureEntry($report, '(root)[].k')['array_length_max'])->toBe($max);
})->with([
    'lists and objects' => [[[1, 2], [1, 2, 3, 4, 5], ['@id' => 'x'], ['@id' => 'x', '@type' => 'T'], ['a' => 1, 'b' => 2, 'c' => 3]], 1, 5],
    'an empty list' => [[[3, 4, 5], []], 0, 3],
    'objects only' => [[['a' => 1, 'b' => 2], ['@id' => 'x', '@type' => 'T', 'z' => 1]], 1, 2],
    'growing' => [[[1], [1, 2, 3]], 1, 3],
    'shrinking' => [[[1, 2, 3], [1]], 1, 3],
]);

it('records the longest string and whether any held a double quote or a line break', function (): void {
    $report = analyzedStructure([
        ['s' => 'ab'],
        ['s' => 'a"b'],
        ['s' => "a\rb"],
        ['t' => 'xyz'],
        ['n' => "line\nbreak"],
        ['s' => 'a'],
    ])->getReport();

    expect($report['(root)[].s'])->toBe([
        'value_types' => ['string' => 4],
        'array_element_types' => [],
        'object_shapes' => [],
        'count' => 4,
        'examples' => ['ab', 'a"b', "a\rb"],
        'nested_keys' => null,
        'max_string_length' => 3,
        'string_contains_double_quote' => true,
        'string_contains_newline' => true,
    ])->and($report['(root)[].t'])->toBe([
        'value_types' => ['string' => 1],
        'array_element_types' => [],
        'object_shapes' => [],
        'count' => 1,
        'examples' => ['xyz'],
        'nested_keys' => null,
        'max_string_length' => 3,
    ])->and(structureEntry($report, '(root)[].n')['string_contains_newline'])->toBeTrue()
        ->and($report['(root)[].n'])->not->toHaveKey('string_contains_double_quote');
});

it('collects each non-empty @language string from value objects', function (): void {
    $analyzer = analyzedStructure([
        ['v' => ['@language' => 'en', '@value' => 'x']],
        ['v' => ['@language' => '', '@value' => 'y']],
        ['v' => ['@language' => 'fr']],
        ['v' => ['@language' => 5]],
        ['v' => ['@language' => 'en']],
    ]);

    expect(structureMeta($analyzer)['language_tags_seen'])->toBe(['en', 'fr']);
});

it('keeps three examples per key and truncates long strings, long lists and unprintable values', function (): void {
    $report = analyzedStructure([
        ['e' => str_repeat('a', 121)],
        ['e' => str_repeat('b', 120)],
        ['e' => [1, [2, 3, 4, 5], 3, 4]],
        ['e' => 'fourth'],
        ['f' => null],
        ['f' => false],
        ['f' => 1.5],
        ['f' => (object) []],
    ])->getReport();

    expect(structureEntry($report, '(root)[].e')['examples'])->toBe([
        str_repeat('a', 120).'...',
        str_repeat('b', 120),
        [0 => 1, 1 => [0 => 2, 1 => 3, 2 => 4, '...' => '(truncated)'], 2 => 3, '...' => '(truncated)'],
    ])->and(structureEntry($report, '(root)[].e')['count'])->toBe(4)
        ->and(structureEntry($report, '(root)[].e')['max_string_length'])->toBe(121)
        ->and(structureEntry($report, '(root)[].f')['examples'])->toBe(['NULL', false, 1.5])
        ->and(structureEntry($report, '(root)[].f')['value_types'])->toBe(['NULL' => 1, 'boolean' => 1, 'double' => 1, 'object' => 1])
        ->and(structureEntry($report, '(root)[].f')['object_shapes'])->toBe(['object()' => 1]);
});

it('names an object by its shape: reference, value, typed reference, type only, or its keys', function (): void {
    $report = analyzedStructure([
        ['s' => ['@id' => 'x']],
        ['s' => ['@value' => 'v']],
        ['s' => ['@value' => 'v', '@type' => 'T']],
        ['s' => ['@value' => 'v', '@type' => 'T', '@language' => 'en']],
        ['s' => ['@id' => 'x', '@type' => 'T']],
        ['s' => ['@id' => 'x', '@type' => 'T', 'z' => 1]],
        ['s' => ['@id' => 'x', '@type' => 'T', 'z' => 1, 'y' => 2]],
        ['s' => ['@type' => 'T']],
        ['s' => ['b' => 1, 'a' => 2]],
        ['s' => ['@id' => 'x', 'a' => 1]],
    ])->getReport();

    expect(structureEntry($report, '(root)[].s')['object_shapes'])->toBe([
        'reference(@id only)' => 1,
        'value(@value, optional @type)' => 2,
        'object(@language,@type,@value)' => 1,
        'reference_with_type(@id,@type)' => 2,
        'object(@id,@type,y,z)' => 1,
        'type_only(@type)' => 1,
        'object(a,b)' => 1,
        'object(@id,a)' => 1,
    ]);
});

it('formats an empty analysis with no optional lines', function (): void {
    expect(analyzedStructure(null)->formatReport())->toBe(implode("\n", [
        '# Schema.org JSON-LD structure analysis',
        '# Key → value types (recursive), with counts and examples',
        '',
        '# --- Gaps addressed ---',
        '',
        '## @id prefix distribution (node count per prefix)',
        '',
        '## Distinct @type values',
        '  ',
        '',
        '## Keys per node type (key => occurrence count on that type)',
        '## Language tags seen (in value objects)',
        '  (none)',
        '',
        '## Ordering',
        '  Array order is preserved as in source; schema.org does not define ordering for these predicates.',
        '',
        '## All @id values',
        '  Total: 0 (full list written to schema-jsonld-all-ids.txt)',
        '',
        '# --- Per-key structure ---',
        '',
        '## (root)',
        '  count: 1',
        '  value_types: NULL',
        '  examples: NULL',
        '',
    ]));
});

it('formats every section, optional line and example rendering of a full analysis', function (): void {
    $analyzer = analyzedStructure([
        ['@id' => 'schema:A', '@type' => 'rdfs:Class', 'label' => 'a "q"', 'list' => [1, 2]],
        [
            '@id' => 'x:B',
            '@type' => 'rdf:Property',
            'label' => ['@language' => 'en', '@value' => "l\n"],
            'list' => [],
            'long' => str_repeat('z', 90),
            'inf' => INF,
        ],
    ]);

    expect($analyzer->formatReport())->toBe(implode("\n", [
        '# Schema.org JSON-LD structure analysis',
        '# Key → value types (recursive), with counts and examples',
        '',
        '# --- Gaps addressed ---',
        '',
        '## @id prefix distribution (node count per prefix)',
        '  schema: => 1',
        '  x: => 1',
        '',
        '## Distinct @type values',
        '  rdf:Property, rdfs:Class',
        '',
        '## Keys per node type (key => occurrence count on that type)',
        '### rdfs:Class',
        '  @id => 1',
        '  @type => 1',
        '  label => 1',
        '  list => 1',
        '',
        '### rdf:Property',
        '  @id => 1',
        '  @type => 1',
        '  inf => 1',
        '  label => 1',
        '  list => 1',
        '  long => 1',
        '',
        '## Language tags seen (in value objects)',
        '  en',
        '',
        '## Ordering',
        '  Array order is preserved as in source; schema.org does not define ordering for these predicates.',
        '',
        '## All @id values',
        '  Total: 2 (full list written to schema-jsonld-all-ids.txt)',
        '',
        '# --- Per-key structure ---',
        '',
        '## (root)',
        '  count: 1',
        '  value_types: array (indexed)',
        '  array_element_types: array (associative)',
        '  array_length: 2 .. 2',
        '  examples: [{"@id":"schema:A","@type":"rdfs:Class","label":"a \"q\"","...":"(truncated)"...',
        '',
        '## (root)[]',
        '  count: 2',
        '  value_types: array (associative)',
        '  object_shapes: object(@id,@type,label,list), object(@id,@type,inf,label,list,long)',
        '  array_length: 1 .. 1',
        '  examples: {"@id":"schema:A","@type":"rdfs:Class","label":"a \"q\"","...":"(truncated)"} | {"@id":"x:B","@type":"rdf:Property","label":{"@language":"en","@value":"l\n"}...',
        '  nested_keys: @id, @type, label, list, long, inf',
        '',
        '## (root)[].@id',
        '  count: 2',
        '  value_types: string',
        '  max_string_length: 8',
        '  examples: schema:A | x:B',
        '',
        '## (root)[].@type',
        '  count: 2',
        '  value_types: string',
        '  max_string_length: 12',
        '  examples: rdfs:Class | rdf:Property',
        '',
        '## (root)[].inf',
        '  count: 1',
        '  value_types: double',
        '  examples: ',
        '',
        '## (root)[].label',
        '  count: 2',
        '  value_types: string, array (associative)',
        '  object_shapes: value(@value, optional @type)',
        '  array_length: 2 .. 2',
        '  max_string_length: 5',
        '  string_contains_double_quote: true',
        '  examples: a "q" | {"@language":"en","@value":"l\n"}',
        '  nested_keys: @language, @value',
        '',
        '## (root)[].label.@language',
        '  count: 1',
        '  value_types: string',
        '  max_string_length: 2',
        '  examples: en',
        '',
        '## (root)[].label.@value',
        '  count: 1',
        '  value_types: string',
        '  max_string_length: 2',
        '  string_contains_newline: true',
        "  examples: l\n",
        '',
        '## (root)[].list',
        '  count: 2',
        '  value_types: array (indexed)',
        '  array_element_types: integer',
        '  array_length: 0 .. 2',
        '  examples: [1,2] | []',
        '',
        '## (root)[].list[]',
        '  count: 2',
        '  value_types: integer',
        '  examples: 1 | 2',
        '',
        '## (root)[].long',
        '  count: 1',
        '  value_types: string',
        '  max_string_length: 90',
        '  examples: '.str_repeat('z', 77).'...',
        '',
    ]));
});

/**
 * Run `runCli()` in-process with the given argv, writing into `$dir`, and hand
 * back its exit code and everything it wrote to STDERR.
 *
 * @param  array<array-key, mixed>  $server  the `$_SERVER` the CLI reads argv from
 * @return array{exit: int, stderr: string}
 */
function runStructureCli(array $server, string $dir): array
{
    $stderr = fopen('php://memory', 'w+');
    expect($stderr)->not->toBeFalse();
    if ($stderr === false) {
        return ['exit' => -1, 'stderr' => ''];
    }

    $exit = SchemaJsonLdStructureAnalyzer::runCli($server, $dir, $stderr);
    rewind($stderr);
    $written = (string) stream_get_contents($stderr);
    fclose($stderr);

    return ['exit' => $exit, 'stderr' => $written];
}

/**
 * Run `runCli()` as `runStructureCli()` does, swallowing the PHP warning a
 * failed `file_put_contents()` raises, so a test can assert what the CLI
 * reports for that failure instead of the warning.
 *
 * @param  array<array-key, mixed>  $server  the `$_SERVER` the CLI reads argv from
 * @return array{exit: int, stderr: string}
 */
function runStructureCliQuietly(array $server, string $dir): array
{
    set_error_handler(static fn (): bool => true, E_WARNING);
    try {
        return runStructureCli($server, $dir);
    } finally {
        restore_error_handler();
    }
}

/**
 * A fresh, empty temp directory for one CLI run, removed after the test.
 */
function structureCliDir(): string
{
    $dir = sys_get_temp_dir().'/jsonld-analyzer-unit-'.bin2hex(random_bytes(6));
    mkdir($dir);

    return $dir;
}

/**
 * Remove a directory a CLI run wrote into, one level deep.
 */
function removeStructureCliDir(string $dir): void
{
    foreach (glob($dir.'/*') ?: [] as $path) {
        is_dir($path) ? rmdir($path) : unlink($path);
    }
    rmdir($dir);
}

it('names its own directory as the place for the default input and the reports', function (): void {
    $directory = SchemaJsonLdStructureAnalyzer::directory();

    expect(realpath($directory))->toBe(realpath(dirname(__DIR__, 5).'/app/Services/EnumGeneration/Schema'))
        ->and(is_file($directory.'/schemaorg-current-https.jsonld'))->toBeTrue();
});

it('reads the default input in the script directory when no path is given', function (): void {
    $dir = structureCliDir();
    try {
        file_put_contents($dir.'/schemaorg-current-https.jsonld', '{"@graph":[{"@id":"schema:B"},{"@id":"schema:A"}]}');

        $result = runStructureCli(['argv' => ['script.php', 7]], $dir);

        expect($result)->toBe([
            'exit' => 0,
            'stderr' => 'Wrote '.$dir.'/schema-jsonld-structure-report.txt'.PHP_EOL
                .'Wrote '.$dir."/schema-jsonld-all-ids.txt (2 ids)\n",
        ])->and(file_get_contents($dir.'/schema-jsonld-all-ids.txt'))->toBe("schema:A\nschema:B\n")
            ->and(file_get_contents($dir.'/schema-jsonld-structure-report.txt'))->toStartWith('# Schema.org JSON-LD structure analysis');
    } finally {
        removeStructureCliDir($dir);
    }
});

it('returns 1 naming the default path when it is missing', function (): void {
    $dir = structureCliDir();
    try {
        expect(runStructureCli([], $dir))->toBe([
            'exit' => 1,
            'stderr' => 'Error: File not found or not readable: '.$dir.'/schemaorg-current-https.jsonld'.PHP_EOL,
        ])->and(glob($dir.'/*'))->toBe([]);
    } finally {
        removeStructureCliDir($dir);
    }
});

it('returns 1 when the given path is a directory', function (): void {
    $dir = structureCliDir();
    try {
        expect(runStructureCli(['argv' => ['script.php', $dir]], $dir))->toBe([
            'exit' => 1,
            'stderr' => 'Error: File not found or not readable: '.$dir.PHP_EOL,
        ]);
    } finally {
        removeStructureCliDir($dir);
    }
});

it('returns 1 with the parser message when the input is not JSON', function (): void {
    $dir = structureCliDir();
    try {
        file_put_contents($dir.'/in.jsonld', '{');

        expect(runStructureCli(['argv' => ['script.php', $dir.'/in.jsonld']], $dir))->toBe([
            'exit' => 1,
            'stderr' => "Error: Invalid JSON: Syntax error\n",
        ]);
    } finally {
        removeStructureCliDir($dir);
    }
});

it('analyzes a JSON null document and writes no all-ids file', function (): void {
    $dir = structureCliDir();
    try {
        file_put_contents($dir.'/in.jsonld', 'null');

        expect(runStructureCli(['argv' => ['script.php', $dir.'/in.jsonld']], $dir))->toBe([
            'exit' => 0,
            'stderr' => 'Wrote '.$dir.'/schema-jsonld-structure-report.txt'.PHP_EOL,
        ])->and(array_map('basename', glob($dir.'/schema-jsonld-*') ?: []))->toBe(['schema-jsonld-structure-report.txt']);
    } finally {
        removeStructureCliDir($dir);
    }
});

it('analyzes a document without @graph as it stands', function (): void {
    $dir = structureCliDir();
    try {
        file_put_contents($dir.'/in.jsonld', '[{"@id":"x:1"}]');

        expect(runStructureCli(['argv' => ['script.php', $dir.'/in.jsonld']], $dir)['exit'])->toBe(0)
            ->and(file_get_contents($dir.'/schema-jsonld-all-ids.txt'))->toBe("x:1\n");
    } finally {
        removeStructureCliDir($dir);
    }
});

it('writes the report as pretty JSON when ANALYZE_OUTPUT is json', function (): void {
    $dir = structureCliDir();
    putenv('ANALYZE_OUTPUT=json');
    try {
        file_put_contents($dir.'/in.jsonld', '{"@graph":[{"@id":"https://schema.org/x"}]}');

        $result = runStructureCli(['argv' => ['script.php', $dir.'/in.jsonld']], $dir);
        $report = (string) file_get_contents($dir.'/schema-jsonld-structure-report.json');

        expect($result['stderr'])->toStartWith('Wrote '.$dir.'/schema-jsonld-structure-report.json'.PHP_EOL)
            ->and($report)->toStartWith("{\n    \"_meta\": {\n")
            ->and($report)->toContain('"https://schema.org/": 1')
            ->and($report)->toEndWith("}\n")
            ->and(file_exists($dir.'/schema-jsonld-structure-report.txt'))->toBeFalse();
    } finally {
        putenv('ANALYZE_OUTPUT');
        removeStructureCliDir($dir);
    }
});

it('returns 1 when the report cannot be written', function (): void {
    $dir = structureCliDir();
    try {
        file_put_contents($dir.'/in.jsonld', '[{"@id":"x:1"}]');
        mkdir($dir.'/schema-jsonld-structure-report.txt');

        $result = runStructureCliQuietly(['argv' => ['script.php', $dir.'/in.jsonld']], $dir);

        expect($result)->toBe([
            'exit' => 1,
            'stderr' => 'Error: Failed to write report to '.$dir.'/schema-jsonld-structure-report.txt'.PHP_EOL,
        ])->and(file_exists($dir.'/schema-jsonld-all-ids.txt'))->toBeFalse();
    } finally {
        removeStructureCliDir($dir);
    }
});

it('still returns 0 when only the all-ids file cannot be written', function (): void {
    $dir = structureCliDir();
    try {
        file_put_contents($dir.'/in.jsonld', '[{"@id":"x:1"}]');
        mkdir($dir.'/schema-jsonld-all-ids.txt');

        $result = runStructureCliQuietly(['argv' => ['script.php', $dir.'/in.jsonld']], $dir);

        expect($result)->toBe([
            'exit' => 0,
            'stderr' => 'Wrote '.$dir.'/schema-jsonld-structure-report.txt'.PHP_EOL
                .'Error: Failed to write all IDs to '.$dir.'/schema-jsonld-all-ids.txt'.PHP_EOL,
        ]);
    } finally {
        removeStructureCliDir($dir);
    }
});
