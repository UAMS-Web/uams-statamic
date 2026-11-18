<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\Schema;

/**
 * Analyzes schema.org JSON-LD file structure recursively.
 *
 * For each key encountered anywhere in the document, records:
 * - All PHP value types seen (string, int, float, bool, null, array, object)
 * - For arrays: element value types and (for objects) object shapes
 * - For objects: whether they are @id references, have @type, or other structure
 * - Occurrence count and one example value (truncated)
 *
 * Also addresses gaps (see schema-jsonld-normalization.md §9):
 * - Keys per node type: which keys appear on rdfs:Class vs rdf:Property vs etc.
 * - @id prefix distribution: node count per namespace (e.g., schema:, bibo:)
 * - Distinct @type values: full list of type strings seen
 * - List length: min/max for array-valued keys
 * - Language tags: distinct @language values in value objects
 * - Ordering note: array order preserved; schema.org does not define order
 * - String length/escaping: max_string_length, string_contains_double_quote/newline
 * - All @id values: full list written to schema-jsonld-all-ids.txt
 *
 * The root of analysis is @graph (the document root is unwrapped). @context is
 * not recursed into (its namespace map is ignored).
 *
 * Run it from the project root through its entry script:
 *   php scripts/analyze-schema-jsonld.php
 *   php scripts/analyze-schema-jsonld.php [path-to.jsonld]
 *
 * Writes schema-jsonld-structure-report.txt (or .json if ANALYZE_OUTPUT=json)
 * and schema-jsonld-all-ids.txt into this class's directory, which also holds
 * the default input. The class has no load-time code, so autoloading or
 * including this file only defines the class (UAMS-Web/uams-statamic-archive#1008).
 */
final class SchemaJsonLdStructureAnalyzer
{
    /**
     * Per-key value statistics accumulated while walking the JSON-LD samples.
     *
     * @var array<string, array{
     *     value_types: array<string, int>,
     *     array_element_types: array<string, int>,
     *     object_shapes: array<string, int>,
     *     count: int,
     *     examples: list<mixed>,
     *     nested_keys: array<string, string>,
     *     array_length_min?: int,
     *     array_length_max?: int,
     *     max_string_length?: int,
     *     string_contains_double_quote?: true,
     *     string_contains_newline?: true,
     * }>
     */
    private array $keyStats = [];

    /** @var list<string> */
    private array $allIds = [];

    /** @var array<string, int> Prefix (e.g., schema:, bibo:) => count */
    private array $idPrefixCounts = [];

    /** @var array<string, string> Distinct @type values seen across nodes, each keyed by itself */
    private array $distinctTypes = [];

    /** @var array<string, array<string, int>> Node type (e.g., rdfs:Class) => (key => occurrence count) */
    private array $keysPerNodeType = [];

    /** @var array<string, string> Distinct @language values (e.g., en) from value objects, each keyed by itself */
    private array $languageTagsSeen = [];

    private const int EXAMPLE_MAX_LENGTH = 120;

    private const int EXAMPLE_MAX_ITEMS = 3;

    /**
     * The directory holding the default input and receiving the reports: this
     * class's own directory, where the entry script points `runCli()`.
     */
    public static function directory(): string
    {
        return __DIR__;
    }

    /**
     * Run the analysis as the CLI does: read the `.jsonld` file named by the
     * first argument (or the default in `$scriptDir`), analyze its `@graph`,
     * and write the report and the sorted `@id` list into `$scriptDir`.
     *
     * @param  array<array-key, mixed>  $server  the process's `$_SERVER`, for argv
     * @param  string  $scriptDir  directory holding the default input and receiving the reports
     * @param  resource  $stderr  stream the progress and error lines are written to
     * @return int the process exit code: 0 on success, 1 on an error that stops the run
     */
    public static function runCli(array $server, string $scriptDir, $stderr): int
    {
        // Resolve the input path from the optional first argument.
        $argv = $server['argv'] ?? [];
        $defaultPath = $scriptDir.'/schemaorg-current-https.jsonld';
        $argPath = is_array($argv) && isset($argv[1]) && is_string($argv[1]) ? $argv[1] : null;
        $path = $argPath ?? $defaultPath;

        if (! is_file($path) || ! is_readable($path)) {
            fwrite($stderr, sprintf('Error: File not found or not readable: %s%s', $path, PHP_EOL));

            return 1;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            fwrite($stderr, "Error: Failed to read file.\n");

            return 1;
        }

        // Decode, then unwrap the document root to its @graph.
        $data = json_decode($json, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            fwrite($stderr, 'Error: Invalid JSON: '.json_last_error_msg()."\n");

            return 1;
        }

        if (is_array($data) && array_key_exists('@graph', $data)) {
            $data = $data['@graph'];
        }

        $analyzer = new self;
        $analyzer->analyze($data);

        // Write the report, as JSON when ANALYZE_OUTPUT asks for it.
        $outputJson = getenv('ANALYZE_OUTPUT') === 'json';
        $reportFile = $scriptDir.'/schema-jsonld-structure-report.'.($outputJson ? 'json' : 'txt');
        $content = $outputJson
            ? json_encode($analyzer->getReport(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
            : $analyzer->formatReport();

        if (file_put_contents($reportFile, $content) === false) {
            fwrite($stderr, sprintf('Error: Failed to write report to %s%s', $reportFile, PHP_EOL));

            return 1;
        }

        fwrite($stderr, sprintf('Wrote %s%s', $reportFile, PHP_EOL));

        // Write the sorted @id list; a failure here is reported but does not fail the run.
        $allIds = $analyzer->getAllIds();
        if ($allIds !== []) {
            sort($allIds);
            $allIdsFile = $scriptDir.'/schema-jsonld-all-ids.txt';
            if (file_put_contents($allIdsFile, implode("\n", $allIds)."\n") === false) {
                fwrite($stderr, sprintf('Error: Failed to write all IDs to %s%s', $allIdsFile, PHP_EOL));
            } else {
                fwrite($stderr, sprintf('Wrote %s (', $allIdsFile).count($allIds)." ids)\n");
            }
        }

        return 0;
    }

    public function analyze(mixed $data): void
    {
        $this->keyStats = [];
        $this->allIds = [];
        $this->idPrefixCounts = [];
        $this->distinctTypes = [];
        $this->keysPerNodeType = [];
        $this->languageTagsSeen = [];

        if (is_array($data) && array_is_list($data)) {
            /** @var list<array<string, mixed>> $graph */
            $graph = $data;
            $this->firstPassGraph($graph);
        }

        $this->walk('', $data);
        ksort($this->keyStats);
    }

    /**
     * First pass over graph: collect @id list and prefix counts, distinct @type values, and keys per node type.
     *
     * @param  list<array<string, mixed>>  $graph
     */
    private function firstPassGraph(array $graph): void
    {
        foreach ($graph as $node) {
            $id = $node['@id'] ?? null;
            if (is_string($id)) {
                $this->allIds[] = $id;
                $prefix = $this->idPrefix($id);
                $this->idPrefixCounts[$prefix] = ($this->idPrefixCounts[$prefix] ?? 0) + 1;
            }

            $types = $this->normalizeTypeList($node['@type'] ?? null);
            foreach ($types as $t) {
                $this->distinctTypes[$t] = $t;
            }

            foreach ($node as $key => $unused) {
                if ($key === '@context') {
                    continue;
                }

                foreach ($types as $t) {
                    $this->keysPerNodeType[$t][$key] ??= 0;

                    $this->keysPerNodeType[$t][$key]++;
                }
            }
        }

        ksort($this->distinctTypes);
        ksort($this->idPrefixCounts);
        foreach (array_keys($this->keysPerNodeType) as $t) {
            ksort($this->keysPerNodeType[$t]);
        }
    }

    private function idPrefix(string $id): string
    {
        if (str_starts_with($id, 'schema:')) {
            return 'schema:';
        }

        if (str_contains($id, 'schema.org/')) {
            return 'https://schema.org/';
        }

        $colon = strpos($id, ':');
        if ($colon !== false) {
            return substr($id, 0, $colon + 1);
        }

        $slash = strrpos($id, '/');
        if ($slash !== false) {
            return substr($id, 0, $slash + 1);
        }

        return $id;
    }

    /**
     * @param  mixed  $v  @type value (string or array)
     * @return list<string>
     */
    private function normalizeTypeList(mixed $v): array
    {
        if (is_string($v)) {
            return [$v];
        }

        if (! is_array($v)) {
            return [];
        }

        if (array_is_list($v)) {
            $out = [];
            foreach ($v as $item) {
                if (is_string($item)) {
                    $out[] = $item;
                }
            }

            return $out;
        }

        $id = $v['@id'] ?? null;

        return is_string($id) ? [$id] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getReport(): array
    {
        $out = [
            '_meta' => [
                'all_ids_count' => count($this->allIds),
                'id_prefix_distribution' => $this->idPrefixCounts,
                'distinct_type_values' => array_keys($this->distinctTypes),
                'keys_per_node_type' => $this->keysPerNodeType,
                'language_tags_seen' => array_keys($this->languageTagsSeen),
                'ordering_note' => 'Array order is preserved as in source; schema.org does not define ordering for these predicates.',
            ],
        ];
        foreach ($this->keyStats as $key => $stats) {
            $entry = [
                'value_types' => $stats['value_types'],
                'array_element_types' => $stats['array_element_types'],
                'object_shapes' => $stats['object_shapes'],
                'count' => $stats['count'],
                'examples' => $this->truncateExamples($stats['examples']),
                'nested_keys' => $stats['nested_keys'] === [] ? null : $this->summarizeNested($stats['nested_keys']),
            ];
            if (isset($stats['array_length_min'], $stats['array_length_max'])) {
                $entry['array_length_min'] = $stats['array_length_min'];
                $entry['array_length_max'] = $stats['array_length_max'];
            }

            if (isset($stats['max_string_length'])) {
                $entry['max_string_length'] = $stats['max_string_length'];
                if (! empty($stats['string_contains_double_quote'])) {
                    $entry['string_contains_double_quote'] = true;
                }

                if (! empty($stats['string_contains_newline'])) {
                    $entry['string_contains_newline'] = true;
                }
            }

            $out[$key] = $entry;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function getAllIds(): array
    {
        return $this->allIds;
    }

    public function formatReport(): string
    {
        $lines = [];
        $lines[] = '# Schema.org JSON-LD structure analysis';
        $lines[] = '# Key → value types (recursive), with counts and examples';
        $lines[] = '';

        $lines[] = '# --- Gaps addressed ---';
        $lines[] = '';
        $lines[] = '## @id prefix distribution (node count per prefix)';
        foreach ($this->idPrefixCounts as $prefix => $count) {
            $lines[] = '  '.$prefix.' => '.$count;
        }

        $lines[] = '';
        $lines[] = '## Distinct @type values';
        $lines[] = '  '.implode(', ', array_keys($this->distinctTypes));
        $lines[] = '';
        $lines[] = '## Keys per node type (key => occurrence count on that type)';
        foreach ($this->keysPerNodeType as $nodeType => $keyCounts) {
            $lines[] = '### '.$nodeType;
            foreach ($keyCounts as $k => $c) {
                $lines[] = '  '.$k.' => '.$c;
            }

            $lines[] = '';
        }

        $lines[] = '## Language tags seen (in value objects)';
        $lines[] = '  '.($this->languageTagsSeen === [] ? '(none)' : implode(', ', array_keys($this->languageTagsSeen)));
        $lines[] = '';
        $lines[] = '## Ordering';
        $lines[] = '  Array order is preserved as in source; schema.org does not define ordering for these predicates.';
        $lines[] = '';
        $lines[] = '## All @id values';
        $lines[] = '  Total: '.count($this->allIds).' (full list written to schema-jsonld-all-ids.txt)';
        $lines[] = '';

        $lines[] = '# --- Per-key structure ---';
        $lines[] = '';

        foreach ($this->keyStats as $key => $stats) {
            $lines[] = '## '.$key;
            $lines[] = '  count: '.$stats['count'];
            $lines[] = '  value_types: '.implode(', ', array_keys($stats['value_types']));
            if (! empty($stats['array_element_types'])) {
                $lines[] = '  array_element_types: '.implode(', ', array_keys($stats['array_element_types']));
            }

            if (! empty($stats['object_shapes'])) {
                $lines[] = '  object_shapes: '.implode(', ', array_keys($stats['object_shapes']));
            }

            if (isset($stats['array_length_min'], $stats['array_length_max'])) {
                $lines[] = '  array_length: '.$stats['array_length_min'].' .. '.$stats['array_length_max'];
            }

            if (isset($stats['max_string_length'])) {
                $lines[] = '  max_string_length: '.$stats['max_string_length'];
                if (! empty($stats['string_contains_double_quote'])) {
                    $lines[] = '  string_contains_double_quote: true';
                }

                if (! empty($stats['string_contains_newline'])) {
                    $lines[] = '  string_contains_newline: true';
                }
            }

            if (! empty($stats['examples'])) {
                $ex = $this->truncateExamples($stats['examples']);
                $lines[] = '  examples: '.$this->formatExampleForLine($ex);
            }

            if ($stats['nested_keys'] !== []) {
                $lines[] = '  nested_keys: '.implode(', ', array_keys($stats['nested_keys']));
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function walk(string $path, mixed $value): void
    {
        $key = $path !== '' ? $path : '(root)';
        $this->ensureKey($key);

        // Read the full stats entry into a local so the shape is preserved across updates
        $stats = $this->keyStats[$key];
        $stats['count']++;

        $phpType = gettype($value);
        $valueTypeLabel = is_array($value)
            ? ('array ('.(array_is_list($value) ? 'indexed' : 'associative').')')
            : $phpType;
        $valueTypes = $stats['value_types'];
        $valueTypes[$valueTypeLabel] = ($valueTypes[$valueTypeLabel] ?? 0) + 1;
        $stats['value_types'] = $valueTypes;
        $this->keyStats[$key] = $stats;

        $this->addExample($key, $value);

        $this->recordArrayLength($key, $value);
        $this->recordStringStats($key, $value);
        $this->recordLanguageTag($key, $value);

        if (is_array($value)) {
            if (array_is_list($value)) {
                $this->describeArrayElements($key, $value);
                foreach ($value as $item) {
                    $this->walk($key.'[]', $item);
                }
            } else {
                /** @var array<string, mixed> $value */
                $this->describeObjectShape($key, $value);
                foreach ($value as $childKey => $childValue) {
                    if ($childKey === '@context') {
                        continue;
                    }

                    $childPath = $path === '' ? $childKey : $path.'.'.$childKey;
                    $this->keyStats[$key]['nested_keys'][$childKey] = $childKey;

                    $this->walk($childPath, $childValue);
                }
            }

            return;
        }

        if (is_object($value)) {
            /** @var array<string, mixed> $objectVars */
            $objectVars = get_object_vars($value);
            $this->describeObjectShape($key, $objectVars);
            foreach ($objectVars as $childKey => $childValue) {
                if ($childKey === '@context') {
                    continue;
                }

                $childPath = $path === '' ? $childKey : $path.'.'.$childKey;
                $this->keyStats[$key]['nested_keys'][$childKey] = $childKey;

                $this->walk($childPath, $childValue);
            }
        }
    }

    private function ensureKey(string $key): void
    {
        $this->keyStats[$key] ??= [
            'value_types' => [],
            'array_element_types' => [],
            'object_shapes' => [],
            'count' => 0,
            'examples' => [],
            'nested_keys' => [],
        ];
    }

    /**
     * Track the shortest and longest array seen for a key. An object with an
     * `@id` counts as one (a reference), any other array by its element count.
     */
    private function recordArrayLength(string $key, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $len = isset($value['@id']) ? 1 : count($value);
        $stats = $this->keyStats[$key];
        $stats['array_length_min'] = min($stats['array_length_min'] ?? $len, $len);
        $stats['array_length_max'] = max($stats['array_length_max'] ?? $len, $len);
        $this->keyStats[$key] = $stats;
    }

    private function recordStringStats(string $key, mixed $value): void
    {
        if (! is_string($value)) {
            return;
        }

        $stats = $this->keyStats[$key];
        $len = strlen($value);
        $stats['max_string_length'] = max($stats['max_string_length'] ?? $len, $len);

        if (str_contains($value, '"')) {
            $stats['string_contains_double_quote'] = true;
        }

        if (str_contains($value, "\n") || str_contains($value, "\r")) {
            $stats['string_contains_newline'] = true;
        }

        $this->keyStats[$key] = $stats;
    }

    private function recordLanguageTag(string $key, mixed $value): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (str_ends_with($key, '.@language')) {
            $this->languageTagsSeen[$value] = $value;
        }
    }

    private function addExample(string $key, mixed $value): void
    {
        // Read the full stats entry into a local so the shape is preserved across updates
        $stats = $this->keyStats[$key];
        $ex = $stats['examples'];
        if (count($ex) >= self::EXAMPLE_MAX_ITEMS) {
            return;
        }

        $ex[] = $value;
        $stats['examples'] = $ex;
        $this->keyStats[$key] = $stats;
    }

    /**
     * @param  array<array-key, mixed>  $arr
     */
    private function describeArrayElements(string $key, array $arr): void
    {
        $stats = $this->keyStats[$key];
        $elementTypes = $stats['array_element_types'];
        foreach ($arr as $item) {
            $elementTypeLabel = is_array($item)
                ? ('array ('.(array_is_list($item) ? 'indexed' : 'associative').')')
                : gettype($item);
            $elementTypes[$elementTypeLabel] = ($elementTypes[$elementTypeLabel] ?? 0) + 1;
        }

        $stats['array_element_types'] = $elementTypes;
        $this->keyStats[$key] = $stats;
    }

    /**
     * @param  array<string, mixed>  $obj
     */
    private function describeObjectShape(string $key, array $obj): void
    {
        $shape = $this->objectShape($obj);
        $this->keyStats[$key]['object_shapes'][$shape] = ($this->keyStats[$key]['object_shapes'][$shape] ?? 0) + 1;
    }

    /**
     * @param  array<string, mixed>  $obj
     */
    private function objectShape(array $obj): string
    {
        $keys = array_keys($obj);
        sort($keys);
        $onlyId = $keys === ['@id'];
        $hasId = in_array('@id', $keys, true);
        $hasType = in_array('@type', $keys, true);
        $hasValue = in_array('@value', $keys, true);
        if ($onlyId) {
            return 'reference(@id only)';
        }

        if ($hasValue && count($keys) <= 2) {
            return 'value(@value, optional @type)';
        }

        if ($hasId && $hasType && count($keys) <= 3) {
            return 'reference_with_type(@id,@type)';
        }

        if ($hasType && count($keys) === 1) {
            return 'type_only(@type)';
        }

        return 'object('.implode(',', $keys).')';
    }

    /**
     * @param  array<int, mixed>  $examples
     * @return array<int, mixed>
     */
    private function truncateExamples(array $examples): array
    {
        $out = [];
        foreach ($examples as $ex) {
            $out[] = $this->truncateExample($ex);
        }

        return $out;
    }

    private function truncateExample(mixed $v): mixed
    {
        if (is_string($v)) {
            if (strlen($v) > self::EXAMPLE_MAX_LENGTH) {
                return substr($v, 0, self::EXAMPLE_MAX_LENGTH).'...';
            }

            return $v;
        }

        if (is_array($v)) {
            $trimmed = [];
            foreach (array_slice($v, 0, self::EXAMPLE_MAX_ITEMS, true) as $k => $item) {
                $trimmed[$k] = $this->truncateExample($item);
            }

            if (count($v) > self::EXAMPLE_MAX_ITEMS) {
                $trimmed['...'] = '(truncated)';
            }

            return $trimmed;
        }

        if (is_bool($v) || is_int($v) || is_float($v)) {
            return $v;
        }

        return gettype($v);
    }

    /**
     * @param  array<int, mixed>  $examples
     */
    private function formatExampleForLine(array $examples): string
    {
        $parts = [];
        foreach ($examples as $ex) {
            $s = is_string($ex) ? $ex : json_encode($ex, JSON_UNESCAPED_SLASHES);
            if ($s === false) {
                $s = '';
            }

            if (strlen($s) > 80) {
                $s = substr($s, 0, 77).'...';
            }

            $parts[] = $s;
        }

        return implode(' | ', $parts);
    }

    /**
     * @param  array<string, string>  $nestedKeys
     * @return array<string>
     */
    private function summarizeNested(array $nestedKeys): array
    {
        return array_keys($nestedKeys);
    }
}
