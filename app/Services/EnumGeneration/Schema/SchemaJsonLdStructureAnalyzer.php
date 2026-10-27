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
 * Usage (from project root):
 *   php app/Services/EnumGeneration/Schema/SchemaJsonLdStructureAnalyzer.php
 *   php app/Services/EnumGeneration/Schema/SchemaJsonLdStructureAnalyzer.php [path-to.jsonld]
 *
 * Writes schema-jsonld-structure-report.txt (or .json if ANALYZE_OUTPUT=json)
 * and schema-jsonld-all-ids.txt in the script directory.
 */
(function (): void {
    // Only run the CLI bootstrap when this file is executed directly (e.g.
    // `php app/Services/EnumGeneration/Schema/SchemaJsonLdStructureAnalyzer.php`).
    // When the file is autoloaded or `include`d by another tool — Rector,
    // PHPStan, the test suite — the entry script differs from this file, so the
    // bootstrap is skipped and only the class below is defined. Without this
    // guard the closure ran on every include and `exit()`ed in the host process.
    //
    // $argv is not in scope inside this closure; read it from $_SERVER, for both
    // the entry-script check here and the optional [path-to.jsonld] argument.
    $argv = $_SERVER['argv'] ?? [];
    $entryScript = is_array($argv) && isset($argv[0]) && is_string($argv[0]) ? $argv[0] : '';
    if (realpath($entryScript) !== __FILE__) {
        return;
    }

    $scriptDir = __DIR__;
    $defaultPath = $scriptDir.'/schemaorg-current-https.jsonld';
    $argPath = is_array($argv) && isset($argv[1]) && is_string($argv[1]) ? $argv[1] : null;
    $path = $argPath ?? $defaultPath;

    if (! is_file($path) || ! is_readable($path)) {
        fwrite(STDERR, sprintf('Error: File not found or not readable: %s%s', $path, PHP_EOL));
        exit(1);
    }

    $json = file_get_contents($path);
    if ($json === false) {
        fwrite(STDERR, "Error: Failed to read file.\n");
        exit(1);
    }

    $data = json_decode($json, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, 'Error: Invalid JSON: '.json_last_error_msg()."\n");
        exit(1);
    }

    if (is_array($data) && array_key_exists('@graph', $data)) {
        $data = $data['@graph'];
    }

    $analyzer = new SchemaJsonLdStructureAnalyzer;
    $analyzer->analyze($data);

    $outputJson = (getenv('ANALYZE_OUTPUT') ?: '') === 'json';
    $reportFile = $scriptDir.'/schema-jsonld-structure-report.'.($outputJson ? 'json' : 'txt');
    $content = $outputJson
        ? json_encode($analyzer->getReport(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        : $analyzer->formatReport();

    if (file_put_contents($reportFile, $content) === false) {
        fwrite(STDERR, sprintf('Error: Failed to write report to %s%s', $reportFile, PHP_EOL));
        exit(1);
    }

    fwrite(STDERR, sprintf('Wrote %s%s', $reportFile, PHP_EOL));

    $allIds = $analyzer->getAllIds();
    if ($allIds !== []) {
        sort($allIds);
        $allIdsFile = $scriptDir.'/schema-jsonld-all-ids.txt';
        if (file_put_contents($allIdsFile, implode("\n", $allIds)."\n") === false) {
            fwrite(STDERR, sprintf('Error: Failed to write all IDs to %s%s', $allIdsFile, PHP_EOL));
        } else {
            fwrite(STDERR, sprintf('Wrote %s (', $allIdsFile).count($allIds)." ids)\n");
        }
    }
})();

/**
 * Analyzes the structure of schema.org JSON-LD vocabulary (types, properties, hierarchy)
 * to support schema enum generation; runnable as a standalone CLI script or autoloaded as
 * a service.
 */
final class SchemaJsonLdStructureAnalyzer
{
    /**
     * @var array<string, array{
     *     value_types: array<string, int>,
     *     array_element_types: array<string, int>,
     *     object_shapes: array<string, int>,
     *     count: int,
     *     examples: list<mixed>,
     *     nested_keys: array<string, true>,
     *     array_length_min?: int,
     *     array_length_max?: int,
     *     max_string_length?: int,
     *     string_contains_double_quote?: true,
     *     string_contains_newline?: true,
     * }>
     */
    /**
     * Per-key value statistics accumulated while walking the JSON-LD samples.
     *
     * @var array<string, array{
     *     value_types: array<string, int>,
     *     array_element_types: array<string, int>,
     *     object_shapes: array<string, int>,
     *     count: int,
     *     examples: list<mixed>,
     *     nested_keys: array<string, true>,
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

    /** @var array<string, true> Distinct @type values seen across nodes */
    private array $distinctTypes = [];

    /** @var array<string, array<string, int>> Node type (e.g., rdfs:Class) => (key => occurrence count) */
    private array $keysPerNodeType = [];

    /** @var array<string, true> Distinct @language values (e.g., en) from value objects */
    private array $languageTagsSeen = [];

    private const int EXAMPLE_MAX_LENGTH = 120;

    private const int EXAMPLE_MAX_ITEMS = 3;

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
                $this->distinctTypes[$t] = true;
            }

            foreach ($node as $key => $unused) {
                if ($key === '@context') {
                    continue;
                }

                foreach ($types as $t) {
                    if (! isset($this->keysPerNodeType[$t][$key])) {
                        $this->keysPerNodeType[$t][$key] = 0;
                    }

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
        if ($v === null) {
            return [];
        }

        if (is_string($v)) {
            return [$v];
        }

        if (is_array($v) && array_is_list($v)) {
            $out = [];
            foreach ($v as $item) {
                if (is_string($item)) {
                    $out[] = $item;
                }
            }

            return $out;
        }

        if (is_array($v) && isset($v['@id'])) {
            $id = $v['@id'];
            if (is_string($id)) {
                return [$id];
            }
        }

        return [];
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
                'nested_keys' => empty($stats['nested_keys']) ? null : $this->summarizeNested($stats['nested_keys']),
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

            if (! empty($stats['nested_keys'])) {
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

        $this->recordArrayLength($key, $value, $phpType);
        $this->recordStringStats($key, $value, $phpType);
        $this->recordLanguageTag($key, $value);

        if ($value === null || $value === []) {
            return;
        }

        if ($phpType === 'array' && is_array($value)) {
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
                    $this->ensureKey($key);
                    if (! isset($this->keyStats[$key]['nested_keys'][$childKey])) {
                        $this->keyStats[$key]['nested_keys'][$childKey] = true;
                    }

                    $this->walk($childPath, $childValue);
                }
            }

            return;
        }

        if ($phpType === 'object' && is_object($value)) {
            /** @var array<string, mixed> $objectVars */
            $objectVars = get_object_vars($value);
            $this->describeObjectShape($key, $objectVars);
            foreach ($objectVars as $childKey => $childValue) {
                if ($childKey === '@context') {
                    continue;
                }

                $childPath = $path === '' ? $childKey : $path.'.'.$childKey;
                $this->ensureKey($key);
                if (! isset($this->keyStats[$key]['nested_keys'][$childKey])) {
                    $this->keyStats[$key]['nested_keys'][$childKey] = true;
                }

                $this->walk($childPath, $childValue);
            }
        }
    }

    private function ensureKey(string $key): void
    {
        if (! isset($this->keyStats[$key])) {
            $this->keyStats[$key] = [
                'value_types' => [],
                'array_element_types' => [],
                'object_shapes' => [],
                'count' => 0,
                'examples' => [],
                'nested_keys' => [],
            ];
        }
    }

    private function recordArrayLength(string $key, mixed $value, string $phpType): void
    {
        if ($phpType !== 'array') {
            return;
        }

        $len = $this->effectiveArrayLength($value);
        if ($len === null) {
            return;
        }

        $this->ensureKey($key);
        $stats = $this->keyStats[$key];
        if (! isset($stats['array_length_min']) || $len < $stats['array_length_min']) {
            $stats['array_length_min'] = $len;
        }

        if (! isset($stats['array_length_max']) || $len > $stats['array_length_max']) {
            $stats['array_length_max'] = $len;
        }

        $this->keyStats[$key] = $stats;
    }

    /**
     * Length for list-valued or single-reference keys (for min/max stats).
     */
    private function effectiveArrayLength(mixed $value): ?int
    {
        if (! is_array($value)) {
            return null;
        }

        if (array_is_list($value)) {
            return count($value);
        }

        if (isset($value['@id']) && count($value) === 1) {
            return 1;
        }

        if (isset($value['@id'])) {
            return 1;
        }

        return count($value);
    }

    private function recordStringStats(string $key, mixed $value, string $phpType): void
    {
        if ($phpType !== 'string' || ! is_string($value)) {
            return;
        }

        $this->ensureKey($key);
        $stats = $this->keyStats[$key];
        $len = strlen($value);
        if (! isset($stats['max_string_length']) || $len > $stats['max_string_length']) {
            $stats['max_string_length'] = $len;
        }

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
            $this->languageTagsSeen[$value] = true;
        }
    }

    private function addExample(string $key, mixed $value): void
    {
        $this->ensureKey($key);
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
        $this->ensureKey($key);
        $stats = $this->keyStats[$key];
        $elementTypes = $stats['array_element_types'];
        $objectShapes = $stats['object_shapes'];
        foreach ($arr as $item) {
            $t = gettype($item);
            $elementTypeLabel = is_array($item)
                ? ('array ('.(array_is_list($item) ? 'indexed' : 'associative').')')
                : $t;
            $elementTypes[$elementTypeLabel] = ($elementTypes[$elementTypeLabel] ?? 0) + 1;
            if ($t === 'object' && is_array($item)) {
                /** @var array<string, mixed> $item */
                $shape = $this->objectShape($item);
                $objectShapes[$shape] = ($objectShapes[$shape] ?? 0) + 1;
            }
        }

        $stats['array_element_types'] = $elementTypes;
        $stats['object_shapes'] = $objectShapes;
        $this->keyStats[$key] = $stats;
    }

    /**
     * @param  array<string, mixed>  $obj
     */
    private function describeObjectShape(string $key, array $obj): void
    {
        $this->ensureKey($key);
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
            $isList = array_is_list($v);
            $trimmed = [];
            $i = 0;
            foreach ($v as $k => $item) {
                if ($i >= self::EXAMPLE_MAX_ITEMS) {
                    $trimmed['...'] = '(truncated)';

                    break;
                }

                $trimmed[$k] = $this->truncateExample($item);
                $i++;
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
     * @param  array<string, true>  $nestedKeys
     * @return array<string>
     */
    private function summarizeNested(array $nestedKeys): array
    {
        return array_keys($nestedKeys);
    }
}
