<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\CredentialRegistry;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Builds the CredentialRegistry enum from Credential Engine registry fixtures.
 *
 * Class hierarchy comes from fixtures/subclasses_map.json (nested JSON tree).
 * Property domain and range lists come from fixtures/description_set_item_maps.yml
 * (subject_types / target_types per property_path). Labels, definitions, and
 * canonical rdfs:subClassOf are merged from credreg.net CTDL JSON term documents
 * when available.
 *
 * @see https://github.com/CredentialEngine/CredentialRegistry
 * @see https://credreg.net/ctdl/terms/
 */
final class CredentialRegistryEnumGenerator
{
    private const string ENUM_PATH = 'app/Enums/CredentialRegistry.php';

    private const string SUBCLASSES_MAP_URL = 'https://raw.githubusercontent.com/CredentialEngine/CredentialRegistry/refs/heads/master/fixtures/subclasses_map.json';

    private const string DESCRIPTION_SET_MAPS_URL = 'https://raw.githubusercontent.com/CredentialEngine/CredentialRegistry/refs/heads/master/fixtures/description_set_item_maps.yml';

    /**
     * Release ID for credreg.net CTDL JSON term URLs (matches published vocabulary).
     */
    private const string CTDL_JSON_RELEASE_ID = '20260327';

    /**
     * PHP keywords and reserved words that cannot be used as enum case names.
     *
     * @var list<string>
     */
    private const array RESERVED_CASE_NAMES = [
        'Abstract',
        'And',
        'Array',
        'As',
        'Break',
        'Callable',
        'Case',
        'Catch',
        'Class',
        'Clone',
        'Const',
        'Continue',
        'Declare',
        'Default',
        'Die',
        'Do',
        'Echo',
        'Else',
        'Elseif',
        'Empty',
        'Enddeclare',
        'Endfor',
        'Endforeach',
        'Endif',
        'Endswitch',
        'Endwhile',
        'Enum',
        'Eval',
        'Exit',
        'Extends',
        'False',
        'Final',
        'Finally',
        'Fn',
        'For',
        'Foreach',
        'Function',
        'Global',
        'Goto',
        'If',
        'Implements',
        'Include',
        'Include_once',
        'Instanceof',
        'Insteadof',
        'Interface',
        'Isset',
        'List',
        'Match',
        'Namespace',
        'New',
        'Null',
        'Or',
        'Parent',
        'Print',
        'Private',
        'Protected',
        'Public',
        'Readonly',
        'Require',
        'Require_once',
        'Return',
        'Self',
        'Static',
        'Switch',
        'Throw',
        'Trait',
        'True',
        'Try',
        'Unset',
        'Use',
        'Var',
        'While',
        'Xor',
        'Yield',
    ];

    /**
     * Generate app/Enums/CredentialRegistry.php from remote fixtures.
     *
     * @return string Written path relative to base path
     */
    public function generate(): string
    {
        // Fetch subclass tree JSON from Credential Registry fixtures
        $treeJson = $this->fetchRemote(self::SUBCLASSES_MAP_URL);
        // Decode tree into associative array
        $tree = json_decode($treeJson, true, 512, JSON_THROW_ON_ERROR);
        if (! \is_array($tree)) {
            throw new \RuntimeException('Invalid subclasses_map.json: expected object');
        }

        /** @var array<string, mixed> $tree */

        // Collect directed edges parent -> child from nested tree
        $edges = [];
        $this->collectEdgesFromTree(null, $tree, $edges);

        // Build parent and child maps (unique shorthands per term)
        $parentsOf = [];
        $childrenOf = [];
        foreach ($edges as [$parent, $child]) {
            // Record immediate parent for child
            $parentsOf[$child] ??= [];
            if (! \in_array($parent, $parentsOf[$child], true)) {
                $parentsOf[$child][] = $parent;
            }

            // Record immediate child for parent
            $childrenOf[$parent] ??= [];
            if (! \in_array($child, $childrenOf[$parent], true)) {
                $childrenOf[$parent][] = $child;
            }
        }

        // Collect every term that appears anywhere in the tree
        $allTerms = $this->collectAllTermsFromTree($tree);
        foreach (array_keys($parentsOf) as $c) {
            $allTerms[$c] = true;
        }

        foreach (array_keys($childrenOf) as $p) {
            $allTerms[$p] = true;
        }

        // Normalize to sorted list of shorthand strings
        $termList = array_keys($allTerms);
        sort($termList);

        // Prefetch credreg.net CTDL JSON once per term (metadata and canonical children)
        $graphByTerm = [];
        foreach ($termList as $term) {
            $graphByTerm[$term] = $this->fetchTermGraphNode($term);
        }

        // Compute transitive ancestors and descendants
        $ancestorsOf = $this->buildTransitiveClosure($termList, $parentsOf, false);
        $descendantsOf = $this->buildTransitiveClosure($termList, $childrenOf, false);
        // Build direct-subclass lists using minimal parents from CTDL JSON when present
        $canonicalChildrenByParent = $this->buildCanonicalChildrenByParent(
            $parentsOf,
            $ancestorsOf,
            $graphByTerm
        );

        // Fetch description set maps for property domain / range lists
        $yaml = $this->fetchRemote(self::DESCRIPTION_SET_MAPS_URL);
        // Parse YAML blocks into property -> subject types / target types
        $propertyDomainRange = $this->parseDescriptionSetMapsYaml($yaml);
        // Aggregate property names and in_range_of per class term
        $propertyNamesByTerm = [];
        $inRangeByTerm = [];
        foreach ($propertyDomainRange as $row) {
            // Unpack property shorthand and subject/target type lists
            $propertyShorthand = $row['property'];
            foreach ($row['subject_types'] as $subject) {
                $propertyNamesByTerm[$subject] ??= [];
                if (! \in_array($propertyShorthand, $propertyNamesByTerm[$subject], true)) {
                    $propertyNamesByTerm[$subject][] = $propertyShorthand;
                }
            }

            foreach ($row['target_types'] as $target) {
                $inRangeByTerm[$target] ??= [];
                if (! \in_array($propertyShorthand, $inRangeByTerm[$target], true)) {
                    $inRangeByTerm[$target][] = $propertyShorthand;
                }
            }
        }

        foreach ($termList as $term) {
            // Sort property lists when present for stable output
            if (isset($propertyNamesByTerm[$term])) {
                sort($propertyNamesByTerm[$term]);
            }

            if (isset($inRangeByTerm[$term])) {
                sort($inRangeByTerm[$term]);
            }
        }

        // Emit PHP source for the enum
        $php = $this->buildEnumPhp(
            $termList,
            $parentsOf,
            $canonicalChildrenByParent,
            $ancestorsOf,
            $descendantsOf,
            $propertyNamesByTerm,
            $inRangeByTerm,
            $graphByTerm
        );

        // Write enum file
        $path = base_path(self::ENUM_PATH);
        File::ensureDirectoryExists(\dirname($path));
        File::put($path, $php);

        return self::ENUM_PATH;
    }

    /**
     * Recursively record subclass edges from the nested JSON tree.
     *
     * @param  array<string, mixed>  $node
     * @param  array<int, array{0: string, 1: string}>  $edges
     */
    private function collectEdgesFromTree(?string $parent, array $node, array &$edges): void
    {
        foreach ($node as $term => $subtree) {
            // Normalize the array key to a string term name (PHP coerces numeric keys to int)
            $term = (string) $term;

            // Link nested term to its parent when present
            if ($parent !== null) {
                $edges[] = [$parent, $term];
            }

            // Recurse into non-empty nested objects
            if (\is_array($subtree) && $subtree !== []) {
                /** @var array<string, mixed> $subtree */
                $this->collectEdgesFromTree($term, $subtree, $edges);
            }
        }
    }

    /**
     * Collect all term keys that appear in the tree (including leaves).
     *
     * @param  array<string, mixed>  $node
     * @return array<string, bool>
     */
    private function collectAllTermsFromTree(array $node): array
    {
        $out = [];
        foreach ($node as $term => $subtree) {
            $out[$term] = true;
            if (\is_array($subtree) && $subtree !== []) {
                /** @var array<string, mixed> $childTree */
                $childTree = $subtree;
                foreach (array_keys($this->collectAllTermsFromTree($childTree)) as $k) {
                    $out[$k] = true;
                }
            }
        }

        return $out;
    }

    /**
     * Build transitive closure: either ancestors (follow parents) or descendants (follow children).
     *
     * @param  list<string>  $names
     * @param  array<string, list<string>>  $forward
     * @return array<string, list<string>>
     */
    private function buildTransitiveClosure(array $names, array $forward, bool $includeSelf): array
    {
        $result = [];
        foreach ($names as $name) {
            // Initialize reachable set for this start node
            $seen = [];
            $stack = [$name];
            while ($stack !== []) {
                // Pop next node to expand
                $current = array_pop($stack);
                if (isset($seen[$current])) {
                    continue;
                }

                $seen[$current] = true;
                foreach ($forward[$current] ?? [] as $next) {
                    if (! isset($seen[$next])) {
                        $stack[] = $next;
                    }
                }
            }

            unset($seen[$name]);
            // Build sorted list of reachable nodes (excluding self unless requested)
            $reachable = array_keys($seen);
            sort($reachable);
            if ($includeSelf) {
                array_unshift($reachable, $name);
            }

            $result[$name] = $reachable;
        }

        return $result;
    }

    /**
     * Parse description_set_item_maps.yml into rows of property and type lists.
     *
     * @return list<array{property: string, subject_types: list<string>, target_types: list<string>}>
     */
    private function parseDescriptionSetMapsYaml(string $yaml): array
    {
        $rows = [];
        // Split into top-level list items that start with "- :name:"
        $parts = preg_split('/\n(?=- :name:)/', $yaml) ?: [];
        foreach ($parts as $part) {
            // Skip document header only
            if (! str_contains($part, ':property_path:')) {
                continue;
            }

            // Extract property shorthand from property_path line
            if (
                preg_match(
                    '/:property_path:\s*[\'"]?\s*[<>]?\s*(\w+:\w+)/',
                    $part,
                    $m
                ) !== 1
            ) {
                continue;
            }

            $property = $m[1];
            // Extract subject_types list
            $subjectTypes = $this->extractYamlTypeList($part, 'subject_types');
            // Extract target_types list
            $targetTypes = $this->extractYamlTypeList($part, 'target_types');
            $rows[] = [
                'property' => $property,
                'subject_types' => $subjectTypes,
                'target_types' => $targetTypes,
            ];
        }

        return $rows;
    }

    /**
     * Extract a list of prefixed terms after a `:list_name:` key until the next `:key:` line.
     *
     * @return list<string>
     */
    private function extractYamlTypeList(string $block, string $listName): array
    {
        // Allow lists whose last line has no trailing newline (fixtures / heredocs)
        $pattern = '/:'.preg_quote($listName, '/').':\s*\n((?:  - [^\n]+(?:\n|$))+)/';
        if (preg_match($pattern, $block, $m) !== 1) {
            return [];
        }

        $body = $m[1];
        $out = [];
        if (preg_match_all('/^\s+-\s+([a-zA-Z0-9_]+:[a-zA-Z0-9_]+)\s*$/m', $body, $matches)) {
            foreach ($matches[1] as $term) {
                $out[] = $term;
            }
        }

        return $out;
    }

    /**
     * Build the CredentialRegistry enum PHP source.
     *
     * @param  list<string>  $termList
     * @param  array<string, list<string>>  $parentsOf
     * @param  array<string, list<string>>  $canonicalChildrenByParent  Direct subclasses per CTDL minimal parent rule
     * @param  array<string, list<string>>  $ancestorsOf
     * @param  array<string, list<string>>  $descendantsOf
     * @param  array<string, list<string>>  $propertyNamesByTerm
     * @param  array<string, list<string>>  $inRangeByTerm
     * @param  array<string, array<string, mixed>|null>  $graphByTerm  Credreg @graph nodes by shorthand
     */
    private function buildEnumPhp(
        array $termList,
        array $parentsOf,
        array $canonicalChildrenByParent,
        array $ancestorsOf,
        array $descendantsOf,
        array $propertyNamesByTerm,
        array $inRangeByTerm,
        array $graphByTerm
    ): string {
        $date = gmdate('Y-m-d');
        $cases = [];
        $usedCaseNames = [];
        foreach ($termList as $shorthand) {
            // Build a unique PHP case name from the shorthand
            $caseName = $this->shorthandToCaseName($shorthand);
            // Avoid reserved words and collisions
            $caseName = $this->ensureUniqueCaseName($caseName, $usedCaseNames);
            $usedCaseNames[$caseName] = true;

            // Reuse prefetched credreg.net JSON-LD term document when available
            $graphNode = $graphByTerm[$shorthand] ?? null;

            $slug = $this->slugFromShorthand($shorthand);
            $uri = $this->shorthandToUri($shorthand);
            $label = $this->labelFromShorthand($shorthand);
            $definition = '';
            $comment = '';
            $usageNote = '';
            $termType = 'rdfs:Class';
            $status = '';
            $sameAs = '';
            $subclassOf = '';

            // Merge authoritative CTDL fields from JSON when present
            if (\is_array($graphNode)) {
                $label = $this->extractLangString($graphNode['rdfs:label'] ?? null) ?: $label;
                $definition = $this->extractLangString($graphNode['rdfs:comment'] ?? null);
                $comment = $this->extractLangString($graphNode['dct:description'] ?? null);
                $usageNote = $this->extractLangString($graphNode['vann:usageNote'] ?? null);
                $status = $this->normalizeStatusShorthand($graphNode['vs:term_status'] ?? '');
                $sameAs = $this->extractEquivalentClassShorthand($graphNode['owl:equivalentClass'] ?? null);
                $jsonSubclass = $this->normalizeSubclassOfShorthand($graphNode['rdfs:subClassOf'] ?? null);
                if ($jsonSubclass !== '') {
                    $subclassOf = $jsonSubclass;
                }

                $tt = $graphNode['@type'] ?? null;
                if (\is_string($tt) && $tt !== '') {
                    $termType = $tt;
                } elseif (\is_array($tt) && isset($tt[0]) && \is_string($tt[0])) {
                    $termType = $tt[0];
                }
            }

            $directParents = $parentsOf[$shorthand] ?? [];
            // Resolve subclass_of when JSON omitted redundant fixture parents
            if ($subclassOf === '') {
                $subclassOf = $this->subclassOfStringFromFixtureParents(
                    $directParents,
                    $ancestorsOf
                );
            }

            $children = $canonicalChildrenByParent[$shorthand] ?? [];
            $ancestors = $ancestorsOf[$shorthand] ?? [];
            $descendants = $descendantsOf[$shorthand] ?? [];
            $propertyNames = $propertyNamesByTerm[$shorthand] ?? [];
            $inRangeOf = $inRangeByTerm[$shorthand] ?? [];

            $attr = sprintf(
                '#[CredentialRegistryTypeInfo(slug: %s, uri: %s, shorthand_uri: %s, label: %s, definition: %s, comment: %s, usage_note: %s, term_type: %s, status: %s, same_as: %s, subclass_of: %s, children: %s, ancestors: %s, descendants: %s, property_names: %s, in_range_of: %s)]',
                $this->exportPhpStringLiteral($slug),
                $this->exportPhpStringLiteral($uri),
                $this->exportPhpStringLiteral($shorthand),
                $this->exportPhpStringLiteral($label),
                $this->exportPhpStringLiteral($definition),
                $this->exportPhpStringLiteral($comment),
                $this->exportPhpStringLiteral($usageNote),
                $this->exportPhpStringLiteral($termType),
                $this->exportPhpStringLiteral($status),
                $this->exportPhpStringLiteral($sameAs),
                $this->exportPhpStringLiteral($subclassOf),
                $this->exportPhpShortStringArray($children),
                $this->exportPhpShortStringArray($ancestors),
                $this->exportPhpShortStringArray($descendants),
                $this->exportPhpShortStringArray($propertyNames),
                $this->exportPhpShortStringArray($inRangeOf)
            );

            $cases[] = sprintf(
                "    %s\n    case %s = %s;",
                $attr,
                $caseName,
                var_export($shorthand, true)
            );
        }

        $casesStr = implode("\n", $cases);

        return <<<PHP
<?php

namespace App\Enums;

use App\Enums\Attributes\CredentialRegistryTypeInfo;

/**
 * Credential Registry / CTDL class terms from CredentialEngine fixtures.
 *
 * Hierarchy is derived from fixtures/subclasses_map.json. Property domain and
 * range hints come from fixtures/description_set_item_maps.yml. Labels,
 * definitions, and subclass_of are merged from credreg.net CTDL JSON when
 * available. Empty strings mark fields not present in those sources.
 *
 * Generated on {$date} using
 * App\Services\EnumGeneration\CredentialRegistry\CredentialRegistryEnumGenerator.
 *
 * @see https://github.com/CredentialEngine/CredentialRegistry
 */
enum CredentialRegistry: string
{
{$casesStr}

    /**
     * CredentialRegistryTypeInfo attribute for this case (via reflection).
     *
     * @return \App\Enums\Attributes\CredentialRegistryTypeInfo|null
     */
    public function info(): ?CredentialRegistryTypeInfo
    {
        \$ref = (new \ReflectionEnum(self::class))->getCase(\$this->name);
        \$attrs = \$ref->getAttributes(CredentialRegistryTypeInfo::class);

        return isset(\$attrs[0]) ? \$attrs[0]->newInstance() : null;
    }
}

PHP;
    }

    /**
     * Fetch the first @graph node for a term from credreg.net JSON, or null.
     *
     * @return array<string, mixed>|null
     */
    private function fetchTermGraphNode(string $shorthand): ?array
    {
        // Parse prefix and local name for the term API path
        $parts = explode(':', $shorthand, 2);
        if (\count($parts) !== 2) {
            return null;
        }

        [$prefix, $local] = $parts;
        // Resolve credreg JSON URL for this vocabulary
        $url = $this->termMetadataJsonUrl($prefix, $local);
        if ($url === null) {
            return null;
        }

        // Download JSON without failing the whole generator
        $raw = $this->fetchRemoteOptional($url);
        if ($raw === null) {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! \is_array($data)) {
            return null;
        }

        if (! isset($data['@graph']) || ! \is_array($data['@graph']) || $data['@graph'] === []) {
            return null;
        }

        $first = $data['@graph'][0];

        if (! \is_array($first)) {
            return null;
        }

        /** @var array<string, mixed> $first */
        return $first;
    }

    /**
     * Build credreg.net JSON URL for a prefixed term when supported.
     */
    private function termMetadataJsonUrl(string $prefix, string $local): ?string
    {
        $release = self::CTDL_JSON_RELEASE_ID;

        return match ($prefix) {
            'ceterms', 'schema' => 'https://credreg.net/ctdl/terms/'.$local.'/json?releaseID='.$release,
            'ceasn' => 'https://credreg.net/ctdlasn/terms/'.$local.'/json',
            'qdata' => 'https://credreg.net/qdata/terms/'.$local.'/json',
            default => null,
        };
    }

    /**
     * Extract en-US (or first string) from CTDL lang-mapped values.
     */
    private function extractLangString(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (! \is_array($value)) {
            return '';
        }

        if (isset($value['en-US']) && \is_string($value['en-US'])) {
            return $value['en-US'];
        }

        foreach ($value as $item) {
            if (\is_string($item)) {
                return $item;
            }
        }

        return '';
    }

    /**
     * Normalize rdfs:subClassOf from JSON to a single shorthand string.
     */
    private function normalizeSubclassOfShorthand(mixed $raw): string
    {
        if (\is_string($raw) && $raw !== '') {
            return $raw;
        }

        if (\is_array($raw)) {
            $first = $raw[0] ?? null;

            return \is_string($first) ? $first : '';
        }

        return '';
    }

    /**
     * Pick first owl:equivalentClass shorthand (e.g. wd:Q15978610).
     */
    private function extractEquivalentClassShorthand(mixed $raw): string
    {
        if (\is_string($raw) && $raw !== '') {
            return $raw;
        }

        if (\is_array($raw)) {
            $first = $raw[0] ?? null;

            return \is_string($first) ? $first : '';
        }

        return '';
    }

    /**
     * Keep vs:term_status as published (e.g. vs:stable).
     */
    private function normalizeStatusShorthand(mixed $raw): string
    {
        return \is_string($raw) ? $raw : '';
    }

    /**
     * Minimal direct parents: drop broader redundant parents when a narrower
     * parent is also listed (same rule as canonical rdfs:subClassOf chains).
     *
     * @param  list<string>  $directParents
     * @param  array<string, list<string>>  $ancestorsOf
     * @return list<string>
     */
    private function minimalDirectParents(
        array $directParents,
        array $ancestorsOf
    ): array {
        if ($directParents === []) {
            return [];
        }

        // Remove redundant broader parents when a narrower parent is also listed
        $minimal = [];
        foreach ($directParents as $p) {
            $redundant = false;
            foreach ($directParents as $q) {
                if ($p === $q) {
                    continue;
                }

                $ancestorsOfQ = $ancestorsOf[$q] ?? [];
                if (\in_array($p, $ancestorsOfQ, true)) {
                    $redundant = true;
                    break;
                }
            }

            if (! $redundant) {
                $minimal[] = $p;
            }
        }

        sort($minimal);

        return $minimal;
    }

    /**
     * Map each parent term to direct child terms where that parent is minimal.
     *
     * Uses CTDL JSON rdfs:subClassOf when available; otherwise fixture parents.
     *
     * @param  array<string, list<string>>  $parentsOf
     * @param  array<string, list<string>>  $ancestorsOf
     * @param  array<string, array<string, mixed>|null>  $graphByTerm
     * @return array<string, list<string>>
     */
    private function buildCanonicalChildrenByParent(
        array $parentsOf,
        array $ancestorsOf,
        array $graphByTerm
    ): array {
        $byParent = [];
        foreach ($parentsOf as $child => $fixtureParents) {
            // Prefer authoritative subclass list from credreg JSON when present
            $graphNode = $graphByTerm[$child] ?? null;
            $graphForChild = \is_array($graphNode) ? $graphNode : null;
            $minimal = $this->effectiveMinimalParents(
                $graphForChild,
                $fixtureParents,
                $ancestorsOf
            );
            foreach ($minimal as $parent) {
                $byParent[$parent] ??= [];
                if (! \in_array($child, $byParent[$parent], true)) {
                    $byParent[$parent][] = $child;
                }
            }
        }

        foreach (array_keys($byParent) as $p) {
            sort($byParent[$p]);
        }

        return $byParent;
    }

    /**
     * Minimal direct superclasses: JSON rdfs:subClassOf when present, else fixtures.
     *
     * @param  array<string, mixed>|null  $graphNode
     * @param  list<string>  $fixtureParents
     * @param  array<string, list<string>>  $ancestorsOf
     * @return list<string>
     */
    private function effectiveMinimalParents(
        ?array $graphNode,
        array $fixtureParents,
        array $ancestorsOf
    ): array {
        $jsonParents = $this->parseRdfsSubclassOfListFromGraphNode($graphNode);
        if ($jsonParents !== null) {
            return $this->minimalDirectParents($jsonParents, $ancestorsOf);
        }

        return $this->minimalDirectParents($fixtureParents, $ancestorsOf);
    }

    /**
     * Parse rdfs:subClassOf from a CTDL JSON @graph node.
     *
     * @param  array<string, mixed>|null  $graphNode
     * @return list<string>|null Null when absent so callers use fixture parents
     */
    private function parseRdfsSubclassOfListFromGraphNode(?array $graphNode): ?array
    {
        if ($graphNode === null || ! isset($graphNode['rdfs:subClassOf'])) {
            return null;
        }

        $raw = $graphNode['rdfs:subClassOf'];
        if (\is_string($raw) && $raw !== '') {
            return [$raw];
        }

        if (! \is_array($raw)) {
            return null;
        }

        $out = [];
        foreach ($raw as $item) {
            if (\is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * Serialize minimal parents to a single subclass_of string for fixtures.
     *
     * @param  list<string>  $directParents
     * @param  array<string, list<string>>  $ancestorsOf
     */
    private function subclassOfStringFromFixtureParents(
        array $directParents,
        array $ancestorsOf
    ): string {
        $minimal = $this->minimalDirectParents($directParents, $ancestorsOf);
        if ($minimal === []) {
            return '';
        }

        if (\count($minimal) === 1) {
            return $minimal[0];
        }

        return implode(',', $minimal);
    }

    /**
     * Local name segment after the prefix colon (used as slug / URI segment).
     */
    private function slugFromShorthand(string $shorthand): string
    {
        $parts = explode(':', $shorthand, 2);

        return $parts[1] ?? $shorthand;
    }

    /**
     * Emit a single-quoted PHP string literal for attributes.
     */
    private function exportPhpStringLiteral(string $value): string
    {
        return "'".addcslashes($value, "\\'")."'";
    }

    /**
     * Emit a compact single-quoted string array: ['a','b'].
     *
     * @param  list<string>  $strings
     */
    private function exportPhpShortStringArray(array $strings): string
    {
        if ($strings === []) {
            return '[]';
        }

        $parts = [];
        foreach ($strings as $s) {
            $parts[] = $this->exportPhpStringLiteral($s);
        }

        return '['.implode(',', $parts).']';
    }

    /**
     * Map a prefixed shorthand term to a canonical HTTPS URI (path style for CTDL).
     */
    private function shorthandToUri(string $shorthand): string
    {
        $parts = explode(':', $shorthand, 2);
        if (\count($parts) !== 2) {
            return $shorthand;
        }

        [$prefix, $local] = $parts;

        return match ($prefix) {
            'ceterms' => 'https://purl.org/ctdl/terms/'.$local,
            'ceasn' => 'https://purl.org/ctdlasn/terms/'.$local,
            'asn' => 'http://purl.org/ASN/schema/core#'.$local,
            'schema' => 'https://schema.org/'.$local,
            'skos' => 'http://www.w3.org/2004/02/skos/core#'.$local,
            'qdata' => 'https://credreg.net/qdata/terms/'.$local,
            default => 'https://credreg.net/unknown/'.$prefix.'/'.$local,
        };
    }

    /**
     * Fetch remote text without throwing (returns null on failure).
     */
    private function fetchRemoteOptional(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 120,
                'follow_location' => true,
            ],
            'https' => [
                'timeout' => 120,
                'follow_location' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);

        return $raw === false ? null : $raw;
    }

    /**
     * Derive a display label from the local name (camel/Pascal case to words).
     */
    private function labelFromShorthand(string $shorthand): string
    {
        $parts = explode(':', $shorthand, 2);
        $local = $parts[1] ?? $shorthand;
        // Insert spaces before capitals for readability
        $spaced = (string) preg_replace('/(?<!^)(?=[A-Z][a-z])|(?<=[a-z])(?=[A-Z])/', ' ', $local);

        return Str::title(trim($spaced));
    }

    /**
     * Convert shorthand (prefix:LocalName) to a Studly case name for PHP.
     */
    private function shorthandToCaseName(string $shorthand): string
    {
        $normalized = str_replace([':', '-'], '_', $shorthand);

        return Str::studly($normalized);
    }

    /**
     * Ensure the case name is not reserved and unique among cases.
     *
     * @param  array<string, bool>  $usedCaseNames
     */
    private function ensureUniqueCaseName(string $caseName, array &$usedCaseNames): string
    {
        // Check if the case name is reserved
        $candidate = $caseName;
        if (\in_array($candidate, self::RESERVED_CASE_NAMES, true)) {
            $candidate .= 'Term';
        }

        // Resolve collisions by suffixing
        $base = $candidate;
        $i = 2;
        while (isset($usedCaseNames[$candidate])) {
            $candidate = $base.$i;
            $i++;
        }

        return $candidate;
    }

    /**
     * Fetch remote fixture text with timeout.
     */
    private function fetchRemote(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 120,
                'follow_location' => true,
            ],
            'https' => [
                'timeout' => 120,
                'follow_location' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new \RuntimeException('Failed to fetch: '.$url);
        }

        return $raw;
    }
}
