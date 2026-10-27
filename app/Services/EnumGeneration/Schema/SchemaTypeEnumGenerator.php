<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\Schema;

use App\Scopes\EntriesBySchemaType;
use Illuminate\Support\Facades\File;

/**
 * Generates the SchemaType enum from schema.org JSON-LD.
 *
 * Fetches https://schema.org/version/latest/schemaorg-current-https.jsonld,
 * extracts types (rdfs:Class), hierarchy (rdfs:subClassOf), and properties
 * (rdf:Property, schema:domainIncludes). Emits an enum with cases, type/property
 * descriptions, and static maps/methods for properties and hierarchy.
 *
 * SchemaProperty / SchemaPropertyInfo range_includes: declared schema:rangeIncludes plus
 * OWL-implied Text, URL, and Role when not limited to datatype-only primitives (mirrors
 * schema.org software/util/sdoowl.py DEFAULTRANGES; see mergeOwlImpliedRangeIncludes()).
 *
 * @see https://schema.org/docs/developers.html
 * @see https://schema.org/docs/datamodel.html
 * @see https://raw.githubusercontent.com/schemaorg/schemaorg/refs/heads/main/software/util/sdoowl.py
 */
final class SchemaTypeEnumGenerator
{
    private const string ENUM_PATH = 'app/Enums/SchemaType.php';

    private const string SCHEMA_PROPERTY_ENUM_PATH = 'app/Enums/SchemaProperty.php';

    private const string SCHEMA_DATA_TYPE_ENUM_PATH = 'app/Enums/SchemaDataType.php';

    private const string SCHEMA_ENUMERATION_MEMBER_ENUM_PATH = 'app/Enums/SchemaEnumerationMember.php';

    private const string SCHEMA_ORG_JSONLD_URL = 'https://schema.org/version/latest/schemaorg-current-https.jsonld';

    /**
     * Local names matching schema.org OWL export "datatype-only" ranges (sdoowl.py DATATYPES).
     * Properties whose declared rangeIncludes are exclusively from this set do not receive
     * OWL_DEFAULT_EXTRA_RANGE_TYPES.
     *
     * @see https://raw.githubusercontent.com/schemaorg/schemaorg/refs/heads/main/software/util/sdoowl.py
     */
    private const array OWL_DATATYPE_ONLY_RANGE_TYPES = ['Boolean', 'Date', 'DateTime', 'Float', 'Integer', 'Number', 'Time'];

    /**
     * Extra types unioned into SchemaProperty range_includes for eligible properties (sdoowl.py DEFAULTRANGES).
     *
     * @var list<string>
     */
    private const array OWL_DEFAULT_EXTRA_RANGE_TYPES = ['Role', 'Text', 'URL'];

    /**
     * Properties that are idiomatically wrapped using Role/OrganizationRole to qualify the relationship edge
     * (e.g. with startDate/endDate/roleName) while reusing the same relationship predicate.
     *
     * We treat these as usable on Role for authoring/tooling purposes by:
     * - adding Role to the generated schema_domain_includes for each property; and
     * - ensuring SchemaType::Role includes these property names.
     *
     * This is a pragmatic, documented convention (examples on schema.org/Role) rather than something
     * explicitly represented via schema:domainIncludes triples for every such property.
     *
     * @var list<string>
     *
     * @see https://schema.org/Role
     * @see https://schema.org/docs/datamodel.html
     * @see https://github.com/schemaorg/schemaorg/issues/2787
     */
    private const array ROLE_WRAPPABLE_PROPERTIES = [
        'affiliation',
        'alumniOf',
        'attendee',
        'attendees',
        'author',
        'contributor',
        'creditedTo',
        'creator',
        'founder',
        'member',
        'memberOf',
        'members',
        'organizer',
        'participant',
        'performer',
        'performers',
        'producer',
        'publisher',
        'translator',
        'worksFor',
    ];

    /**
     * Generate SchemaType, SchemaProperty, SchemaDataType, and SchemaEnumerationMember enums
     * (shared graph, single fetch).
     *
     * @return array{0: string, 1: string, 2: string, 3: bool, 4: string} [SchemaType path, SchemaProperty path, SchemaDataType path, cacheWarmed,
     *                                                                    SchemaEnumerationMember path]
     */
    public function generate(): array
    {
        $json = $this->fetchJsonLd();
        $graph = $this->extractGraph($json);

        $dataTypeNames = $this->collectDataTypeNamesFromGraph($graph);
        $typeNames = $this->collectTypeNames($graph);
        // Exclude DataType and its subclasses so they only appear in SchemaDataType.
        $typeNames = array_values(array_diff($typeNames, $dataTypeNames));
        sort($typeNames);
        $typeParents = $this->buildTypeParents($graph, $typeNames);
        $typeChildren = $this->buildTypeChildren($typeParents);
        $typeAncestors = $this->buildTransitiveClosure($typeNames, $typeParents, false);
        $typeDescendants = $this->buildTransitiveClosure($typeNames, $typeChildren, false);

        $enumerationTypeNames = $this->collectEnumerationTypeNames($typeNames, $typeAncestors);
        $memberToTypes = $this->collectEnumerationMembersFromGraph($graph, $enumerationTypeNames);
        $typeToMembers = $this->buildTypeToMembers($memberToTypes);

        $typeDescriptions = $this->collectTypeDescriptions($graph, $typeNames);
        $typeSupersededBy = $this->collectTypeSupersededBy($graph, $typeNames);
        $propertyDescriptions = $this->collectPropertyDescriptions($graph);
        $typeProperties = $this->buildTypeProperties($graph, $typeNames, $typeAncestors);
        [$propertyDomains, $propertyRanges] = $this->collectPropertyDomainsAndRanges($graph, array_keys($propertyDescriptions));
        $propertyDomains = $this->mergeRoleWrappablePropertyDomains($propertyDomains);
        $typeProperties = $this->mergeRoleWrappableTypeProperties($typeProperties, $typeAncestors);
        $propertyRanges = $this->mergeOwlImpliedRangeIncludes($propertyRanges);
        $propertyNames = array_keys($propertyDescriptions);
        sort($propertyNames);
        $propertySupersededBy = $this->collectPropertySupersededBy($graph, $propertyNames);
        $dataTypeDescriptions = $this->collectDataTypeDescriptions($graph, $dataTypeNames);

        // Write SchemaProperty first (SchemaType will reference it for property descriptions)
        $schemaPropertyPhp = $this->buildSchemaPropertyEnumPhp(
            $propertyNames,
            $propertyDescriptions,
            $propertyDomains,
            $propertyRanges,
            $propertySupersededBy
        );
        $propertyPath = base_path(self::SCHEMA_PROPERTY_ENUM_PATH);
        File::ensureDirectoryExists(dirname($propertyPath));
        File::put($propertyPath, $schemaPropertyPhp);

        $schemaDataTypePhp = $this->buildSchemaDataTypeEnumPhp($dataTypeNames, $dataTypeDescriptions);
        $dataTypePath = base_path(self::SCHEMA_DATA_TYPE_ENUM_PATH);
        File::ensureDirectoryExists(dirname($dataTypePath));
        File::put($dataTypePath, $schemaDataTypePhp);

        $memberDescriptions = $this->collectEnumerationMemberDescriptions(
            $graph,
            array_keys($memberToTypes)
        );
        $schemaEnumerationMemberPhp = $this->buildSchemaEnumerationMemberEnumPhp(
            $memberToTypes,
            $memberDescriptions
        );
        $enumerationMemberPath = base_path(self::SCHEMA_ENUMERATION_MEMBER_ENUM_PATH);
        File::ensureDirectoryExists(dirname($enumerationMemberPath));
        File::put($enumerationMemberPath, $schemaEnumerationMemberPhp);

        $schemaTypePhp = $this->buildEnumPhp(
            $typeNames,
            $typeDescriptions,
            $typeProperties,
            $typeParents,
            $typeChildren,
            $typeAncestors,
            $typeDescendants,
            $typeSupersededBy,
            $typeToMembers
        );
        $typePath = base_path(self::ENUM_PATH);
        File::ensureDirectoryExists(dirname($typePath));
        File::put($typePath, $schemaTypePhp);

        $cacheWarmed = false;
        if (class_exists(EntriesBySchemaType::class)) {
            try {
                \call_user_func(
                    EntriesBySchemaType::warmAliasesCache(...),
                    $typeNames
                );
                $cacheWarmed = true;
            } catch (\Throwable) {
                // Enum files are already written; do not fail the command.
            }
        }

        return [
            self::ENUM_PATH,
            self::SCHEMA_PROPERTY_ENUM_PATH,
            self::SCHEMA_DATA_TYPE_ENUM_PATH,
            $cacheWarmed,
            self::SCHEMA_ENUMERATION_MEMBER_ENUM_PATH,
        ];
    }

    /**
     * Fetch JSON-LD from schema.org.
     *
     * @return string Raw JSON string
     */
    private function fetchJsonLd(): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 60,
                'follow_location' => true,
            ],
        ]);
        $raw = @file_get_contents(self::SCHEMA_ORG_JSONLD_URL, false, $context);
        if ($raw === false) {
            throw new \RuntimeException('Failed to fetch schema.org JSON-LD from '.self::SCHEMA_ORG_JSONLD_URL);
        }

        return $raw;
    }

    /**
     * Decode JSON and return @graph array.
     *
     * @return list<array<string, mixed>>
     */
    private function extractGraph(string $json): array
    {
        $data = json_decode($json, true);
        if (! \is_array($data) || ! isset($data['@graph']) || ! \is_array($data['@graph'])) {
            throw new \RuntimeException('Invalid schema.org JSON-LD: missing @graph');
        }

        /** @var list<array<string, mixed>> $graph */
        $graph = $data['@graph'];

        return $graph;
    }

    /**
     * Extract schema local name from @id (schema:Person → Person, or full URL → last segment).
     *
     * @param  mixed  $id  @id value (string, expanded object, or other JSON-LD value)
     */
    private function schemaLocalName($id): ?string
    {
        if (\is_array($id) && isset($id['@id'])) {
            $id = $id['@id'];
        }

        if (! \is_string($id)) {
            return null;
        }

        if (str_starts_with($id, 'schema:')) {
            return substr($id, 7);
        }

        if (str_contains($id, 'schema.org/')) {
            return substr($id, strrpos($id, '/') + 1);
        }

        return null;
    }

    /**
     * Normalize @id to schema:LocalName for consistent comparison (schema:DataType or URL → schema:DataType).
     *
     * @param  mixed  $id  @id value (string, object with @id, or other JSON-LD value)
     * @return string|null Normalized id (schema:Name) or null if not a schema.org type
     */
    private function normalizeSchemaId($id): ?string
    {
        $local = $this->schemaLocalName(\is_array($id) && isset($id['@id']) ? $id['@id'] : $id);
        if ($local === null || $local === '') {
            return null;
        }

        return 'schema:'.$local;
    }

    /**
     * Collect DataType names from graph by expanding from schema:DataType via @type and rdfs:subClassOf.
     *
     * Starts with schema:DataType. Repeatedly adds nodes whose @type contains a type already in the
     * set, or whose rdfs:subClassOf targets a type in the set, until no new nodes are added.
     *
     * @param  list<array<string, mixed>>  $graph
     * @return list<string> Sorted list of local names (e.g., Integer, Text)
     */
    private function collectDataTypeNamesFromGraph(array $graph): array
    {
        $dataTypeIds = ['schema:DataType' => true];

        $maxIterations = count($graph) + 1;
        $iterations = 0;
        while (true) {
            $addedThisPass = false;
            foreach ($graph as $node) {
                $nodeId = $this->normalizeSchemaId($node['@id'] ?? null);
                if ($nodeId === null) {
                    continue;
                }

                if (isset($dataTypeIds[$nodeId])) {
                    continue;
                }

                // Add if @type contains or equals a type already in the data type set.
                $typeRaw = $node['@type'] ?? [];
                $types = \is_array($typeRaw) ? $typeRaw : [$typeRaw];
                foreach ($types as $type) {
                    $normalized = $this->normalizeSchemaId($type);
                    if ($normalized !== null && isset($dataTypeIds[$normalized])) {
                        $dataTypeIds[$nodeId] = true;
                        $addedThisPass = true;
                        break;
                    }
                }

                if (isset($dataTypeIds[$nodeId])) {
                    continue;
                }

                // Add if rdfs:subClassOf targets a type already in the set.
                $subClassOf = $node['rdfs:subClassOf'] ?? null;
                $targets = $this->normalizeIdList($subClassOf);
                foreach ($targets as $target) {
                    $targetId = $this->normalizeSchemaId($target);
                    if ($targetId !== null && isset($dataTypeIds[$targetId])) {
                        $dataTypeIds[$nodeId] = true;
                        $addedThisPass = true;
                        break;
                    }
                }
            }

            if (! $addedThisPass) {
                break;
            }

            $iterations++;
            if ($iterations >= $maxIterations) {
                break;
            }
        }

        $localNames = [];
        foreach (array_keys($dataTypeIds) as $id) {
            $local = $this->schemaLocalName($id);
            if ($local !== null && $local !== '') {
                $localNames[] = $local;
            }
        }

        sort($localNames);

        return $localNames;
    }

    /**
     * Check if node is a schema.org class (@type rdfs:Class, @id schema:*).
     *
     * @param  array<string, mixed>  $node
     */
    private function isSchemaClass(array $node): bool
    {
        $id = $node['@id'] ?? null;
        $type = $node['@type'] ?? null;
        if ($this->schemaLocalName($id) === null) {
            return false;
        }

        $types = \is_array($type) ? $type : [$type];

        return \in_array('rdfs:Class', $types, true);
    }

    /**
     * Check if node is a schema.org property (@type rdf:Property, @id schema:*).
     *
     * @param  array<string, mixed>  $node
     */
    private function isSchemaProperty(array $node): bool
    {
        $id = $node['@id'] ?? null;
        $type = $node['@type'] ?? null;
        if ($this->schemaLocalName($id) === null) {
            return false;
        }

        $types = \is_array($type) ? $type : [$type];

        return \in_array('rdf:Property', $types, true);
    }

    /**
     * Collect sorted list of schema.org type names from graph.
     *
     * @param  list<array<string, mixed>>  $graph
     * @return list<string>
     */
    private function collectTypeNames(array $graph): array
    {
        $names = [];
        foreach ($graph as $node) {
            if (! $this->isSchemaClass($node)) {
                continue;
            }

            $name = $this->schemaLocalName($node['@id']);
            if ($name !== null && $name !== '') {
                $names[$name] = true;
            }
        }

        $names = array_keys($names);
        sort($names);

        return $names;
    }

    /**
     * Build type => list of immediate parent type names from rdfs:subClassOf.
     *
     * @param  list<array<string, mixed>>  $graph
     * @param  list<string>  $typeNames  Valid type names (only include parents that are in this set)
     * @return array<string, list<string>>
     */
    private function buildTypeParents(array $graph, array $typeNames): array
    {
        $typeSet = array_fill_keys($typeNames, true);
        $parents = [];
        foreach ($typeNames as $type) {
            $parents[$type] = [];
        }

        foreach ($graph as $node) {
            if (! $this->isSchemaClass($node)) {
                continue;
            }

            $type = $this->schemaLocalName($node['@id']);
            if ($type === null) {
                continue;
            }

            if (! isset($typeSet[$type])) {
                continue;
            }

            $subClassOf = $node['rdfs:subClassOf'] ?? null;
            if ($subClassOf === null) {
                continue;
            }

            $targets = $this->normalizeIdList($subClassOf);
            foreach ($targets as $targetId) {
                $parent = $this->resolveToSchemaLocalName($targetId);
                if ($parent !== null && isset($typeSet[$parent])) {
                    $parents[$type][] = $parent;
                }
            }

            $parents[$type] = array_values(array_unique($parents[$type]));
        }

        return $parents;
    }

    /**
     * Resolve @id (possibly a reference) to schema local name.
     *
     * @param  string|array<string, mixed>  $id
     */
    private function resolveToSchemaLocalName($id): ?string
    {
        if (\is_array($id) && isset($id['@id'])) {
            $id = $id['@id'];
        }

        if (! \is_string($id)) {
            return null;
        }

        return $this->schemaLocalName($id);
    }

    /**
     * Normalize subClassOf or domainIncludes value to list of @id strings/objects.
     *
     * @param  mixed  $value
     * @return list<string|array<string, mixed>>
     */
    private function normalizeIdList($value): array
    {
        if ($value === null) {
            return [];
        }

        if (! \is_array($value)) {
            /** @var list<string|array<string, mixed>> $single */
            $single = [$value];

            return $single;
        }

        if (isset($value['@id'])) {
            /** @var array<string, mixed> $value */
            return [$value];
        }

        if (isset($value[0])) {
            /** @var list<string|array<string, mixed>> $value */
            return $value;
        }

        /** @var array<string, mixed> $value */
        $object = $value;

        return [$object];
    }

    /**
     * Extract schema local name from a single reference (schema:supersededBy, etc.).
     *
     * @param  mixed  $value  Single reference object {"@id": "schema:Name"} or indexed array of one such object
     * @return string|null Local name (e.g. includedInDataCatalog) or null if missing/invalid
     */
    private function extractSingleReferenceLocalName($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $ref = $value;
        if (\is_array($value) && isset($value[0]) && \is_array($value[0])) {
            $ref = $value[0];
        }

        $id = \is_array($ref) && isset($ref['@id']) ? $ref['@id'] : null;
        if (! \is_string($id)) {
            return null;
        }

        return $this->schemaLocalName($id);
    }

    /**
     * Index @graph by @id for lookup.
     *
     * @param  list<array<string, mixed>>  $graph
     * @return array<string, array<string, mixed>>
     */
    private function indexGraphById(array $graph): array
    {
        $out = [];
        foreach ($graph as $node) {
            $id = $node['@id'] ?? null;
            if (\is_string($id)) {
                $out[$id] = $node;
            }
        }

        return $out;
    }

    /**
     * Build type => list of immediate child type names from parents map.
     *
     * @param  array<string, list<string>>  $typeParents
     * @return array<string, list<string>>
     */
    private function buildTypeChildren(array $typeParents): array
    {
        $children = [];
        foreach (array_keys($typeParents) as $type) {
            $children[$type] = [];
        }

        foreach ($typeParents as $type => $parents) {
            foreach ($parents as $parent) {
                if (isset($children[$parent])) {
                    $children[$parent][] = $type;
                }
            }
        }

        foreach ($children as $type => $list) {
            $children[$type] = array_values(array_unique($list));
            sort($children[$type]);
        }

        return $children;
    }

    /**
     * Build transitive closure: for each type, all reachable types following the relation.
     *
     * @param  list<string>  $typeNames
     * @param  array<string, list<string>>  $relation  type => list of direct targets (e.g., parents or children)
     * @param  bool  $sort  Whether to sort each result list
     * @return array<string, list<string>>
     */
    private function buildTransitiveClosure(array $typeNames, array $relation, bool $sort = true): array
    {
        $result = [];
        foreach ($typeNames as $type) {
            $result[$type] = $this->transitiveClosure($type, $relation);
            if ($sort) {
                sort($result[$type]);
            }
        }

        return $result;
    }

    /**
     * Get all types reachable from $start following the relation (BFS).
     *
     * @param  array<string, list<string>>  $relation
     * @return list<string>
     */
    private function transitiveClosure(string $start, array $relation): array
    {
        $seen = [$start => true];
        $queue = [$start];
        $out = [];
        while ($queue !== []) {
            $current = array_shift($queue);
            $neighbors = $relation[$current] ?? [];
            foreach ($neighbors as $next) {
                if (! isset($seen[$next])) {
                    $seen[$next] = true;
                    $out[] = $next;
                    $queue[] = $next;
                }
            }
        }

        sort($out);

        return $out;
    }

    /**
     * Collect type descriptions (rdfs:comment) from graph, as HTML with links.
     *
     * @param  list<array<string, mixed>>  $graph
     * @param  list<string>  $typeNames
     * @return array<string, string|null>
     */
    private function collectTypeDescriptions(array $graph, array $typeNames): array
    {
        $byId = $this->indexGraphById($graph);
        $schemaPrefix = 'https://schema.org/';
        $out = [];
        foreach ($typeNames as $type) {
            $id = $schemaPrefix.$type;
            $node = $byId[$id] ?? $byId['schema:'.$type] ?? null;
            $comment = $this->commentToPlainText($node['rdfs:comment'] ?? null);
            $out[$type] = $comment !== null && $comment !== ''
                ? $this->descriptionTextToHtml($comment)
                : null;
        }

        return $out;
    }

    /**
     * Collect type name => superseding type or property local name (schema:supersededBy).
     *
     * @param  list<array<string, mixed>>  $graph
     * @param  list<string>  $typeNames
     * @return array<string, string|null> Type name => superseded_by local name, or null
     */
    private function collectTypeSupersededBy(array $graph, array $typeNames): array
    {
        $byId = $this->indexGraphById($graph);
        $schemaPrefix = 'https://schema.org/';
        $out = [];
        foreach ($typeNames as $type) {
            $id = $schemaPrefix.$type;
            $node = $byId[$id] ?? $byId['schema:'.$type] ?? null;
            $supersededBy = $node['schema:supersededBy'] ?? null;
            $out[$type] = $this->extractSingleReferenceLocalName($supersededBy);
        }

        return $out;
    }

    /**
     * Collect type names that are enumerations (have Enumeration as an ancestor).
     *
     * @param  list<string>  $typeNames  All schema type names
     * @param  array<string, list<string>>  $typeAncestors  Type => ancestor type names
     * @return list<string> Sorted list of enumeration type names
     */
    private function collectEnumerationTypeNames(array $typeNames, array $typeAncestors): array
    {
        $out = [];
        foreach ($typeNames as $type) {
            $ancestors = $typeAncestors[$type] ?? [];
            if (\in_array('Enumeration', $ancestors, true) || $type === 'Enumeration') {
                $out[] = $type;
            }
        }

        sort($out);

        return $out;
    }

    /**
     * Collect enumeration members from graph: nodes whose @type is an enumeration type
     * (not rdfs:Class). Returns member local name => list of enumeration type names.
     *
     * @param  list<array<string, mixed>>  $graph
     * @param  list<string>  $enumerationTypeNames  Type names that are enumerations
     * @return array<string, list<string>> Member local name => enumeration type name(s)
     */
    private function collectEnumerationMembersFromGraph(array $graph, array $enumerationTypeNames): array
    {
        $enumSet = array_fill_keys($enumerationTypeNames, true);
        $memberToTypes = [];
        foreach ($graph as $node) {
            $id = $node['@id'] ?? null;
            $memberName = $this->schemaLocalName($id);
            if ($memberName === null) {
                continue;
            }

            if ($memberName === '') {
                continue;
            }

            $typeRaw = $node['@type'] ?? null;
            if ($typeRaw === null) {
                continue;
            }

            $types = \is_array($typeRaw) ? $typeRaw : [$typeRaw];
            if (\in_array('rdfs:Class', $types, true)) {
                continue;
            }

            $enumerationTypes = [];
            foreach ($types as $t) {
                $resolved = $this->normalizeSchemaId($t);
                if ($resolved === null) {
                    continue;
                }

                $local = $this->schemaLocalName($resolved);
                if ($local !== null && isset($enumSet[$local])) {
                    $enumerationTypes[] = $local;
                }
            }

            if ($enumerationTypes === []) {
                continue;
            }

            $enumerationTypes = array_values(array_unique($enumerationTypes));
            sort($enumerationTypes);
            if (isset($memberToTypes[$memberName])) {
                $memberToTypes[$memberName] = array_values(
                    array_unique(array_merge($memberToTypes[$memberName], $enumerationTypes))
                );
                sort($memberToTypes[$memberName]);
            } else {
                $memberToTypes[$memberName] = $enumerationTypes;
            }
        }

        return $memberToTypes;
    }

    /**
     * Build enumeration type name => list of member local names from member => types map.
     *
     * @param  array<string, list<string>>  $memberToTypes  Member name => enumeration type name(s)
     * @return array<string, list<string>> Enumeration type => sorted member names
     */
    private function buildTypeToMembers(
        array $memberToTypes
    ): array {
        $typeToMembers = [];
        foreach ($memberToTypes as $member => $types) {
            foreach ($types as $type) {
                $typeToMembers[$type][] = $member;
            }
        }

        foreach ($typeToMembers as $type => $members) {
            $typeToMembers[$type] = array_values(array_unique($members));
            sort($typeToMembers[$type]);
        }

        return $typeToMembers;
    }

    /**
     * Collect rdfs:comment (as HTML) for enumeration member nodes from graph.
     *
     * @param  list<array<string, mixed>>  $graph
     * @param  list<string>  $memberNames  Enumeration member local names
     * @return array<string, string|null> Member name => description HTML, or null
     */
    private function collectEnumerationMemberDescriptions(array $graph, array $memberNames): array
    {
        $byId = $this->indexGraphById($graph);
        $schemaPrefix = 'https://schema.org/';
        $out = [];
        foreach ($memberNames as $name) {
            $node = $byId['schema:'.$name] ?? $byId[$schemaPrefix.$name] ?? null;
            $comment = $this->commentToPlainText($node['rdfs:comment'] ?? null);
            $out[$name] = $comment !== null && $comment !== ''
                ? $this->descriptionTextToHtml($comment)
                : null;
        }

        return $out;
    }

    /**
     * Build the full PHP source for the SchemaEnumerationMember enum.
     *
     * @param  array<string, list<string>>  $memberToTypes  Member local name => enumeration type name(s)
     * @param  array<string, string|null>  $memberDescriptions  Member name => rdfs:comment HTML
     */
    private function buildSchemaEnumerationMemberEnumPhp(
        array $memberToTypes,
        array $memberDescriptions = []
    ): string {
        $memberNames = array_keys($memberToTypes);
        sort($memberNames);
        $casesPhp = $this->buildSchemaEnumerationMemberCasesPhp(
            $memberToTypes,
            $memberNames,
            $memberDescriptions
        );
        $generatedDate = date('Y-m-d');

        return <<<PHP
<?php

namespace App\Enums;

use App\Enums\Attributes\SchemaEnumerationMemberInfo;

/**
 * Schema.org enumeration member values from schema.org JSON-LD vocabulary.
 *
 * Each case is an enumeration member (e.g. AlgorithmicMediaDigitalSource) with
 * metadata via SchemaEnumerationMemberInfo (type, rdfs_comment).
 *
 * Generated from schema.org on {$generatedDate} using
 * app/Services/EnumGeneration/Schema/SchemaTypeEnumGenerator.php.
 *
 * @see https://schema.org/docs/developers.html
 */
enum SchemaEnumerationMember: string
{
{$casesPhp}

    /**
     * Enumeration member string values valid for a Schema.org enumeration type
     * (matches {@see SchemaEnumerationMemberInfo::\$type} on each case).
     *
     * @return list<string>
     */
    public static function memberNamesForEnumerationType(string \$schemaEnumerationTypeName): array
    {
        \$out = [];
        foreach (self::cases() as \$case) {
            if (in_array(\$schemaEnumerationTypeName, \$case->type(), true)) {
                \$out[] = \$case->value;
            }
        }

        sort(\$out);

        return array_values(array_unique(\$out));
    }

    /**
     * SchemaEnumerationMemberInfo attribute for this case (read via reflection).
     */
    public function info(): ?SchemaEnumerationMemberInfo
    {
        \$ref = (new \\ReflectionEnum(self::class))->getCase(\$this->name);
        \$attrs = \$ref->getAttributes(SchemaEnumerationMemberInfo::class);

        return isset(\$attrs[0]) ? \$attrs[0]->newInstance() : null;
    }

    /**
     * Enumeration type name(s) for which this value is a member.
     *
     * @return list<string>
     */
    public function type(): array
    {
        return \$this->info()?->type ?? [];
    }

    /**
     * Member description (rdfs:comment) as HTML with links.
     */
    public function description(): ?string
    {
        return \$this->info()?->rdfs_comment;
    }
}

PHP;
    }

    /**
     * Build SchemaEnumerationMember enum cases with SchemaEnumerationMemberInfo attribute per case.
     *
     * @param  array<string, list<string>>  $memberToTypes  Member name => enumeration type name(s)
     * @param  list<string>  $memberNames  Sorted member names
     * @param  array<string, string|null>  $memberDescriptions  Member name => rdfs:comment HTML
     */
    private function buildSchemaEnumerationMemberCasesPhp(
        array $memberToTypes,
        array $memberNames,
        array $memberDescriptions = []
    ): string {
        $lines = [];
        foreach ($memberNames as $name) {
            $types = $memberToTypes[$name] ?? [];
            $comment = $memberDescriptions[$name] ?? null;
            $attr = $this->formatSchemaEnumerationMemberInfoAttribute($types, $comment);
            $lines[] = '    '.$attr;
            $lines[] = '    case '.$this->enumCaseName($name).' = '.var_export($name, true).';';
        }

        return implode("\n", $lines);
    }

    /**
     * Format a single #[SchemaEnumerationMemberInfo(...)] attribute for one enum case.
     *
     * @param  list<string>  $types  Enumeration type name(s)
     * @param  string|null  $rdfsComment  Member description HTML
     */
    private function formatSchemaEnumerationMemberInfoAttribute(array $types, ?string $rdfsComment): string
    {
        $typePhp = $this->exportArrayInline($types);
        $commentPhp = $rdfsComment !== null && $rdfsComment !== ''
            ? var_export($this->decodeHtmlEntitiesForPhpExport($rdfsComment), true)
            : 'null';

        return '#[SchemaEnumerationMemberInfo(type: '.$typePhp.', rdfs_comment: '.$commentPhp.')]';
    }

    /**
     * Collect property name => rdfs:comment (as HTML) for schema properties.
     *
     * @param  list<array<string, mixed>>  $graph
     * @return array<string, string>
     */
    private function collectPropertyDescriptions(array $graph): array
    {
        $out = [];
        foreach ($graph as $node) {
            if (! $this->isSchemaProperty($node)) {
                continue;
            }

            $name = $this->schemaLocalName($node['@id']);
            if ($name === null) {
                continue;
            }

            $comment = $this->commentToPlainText($node['rdfs:comment'] ?? null);
            $out[$name] = $comment !== null && $comment !== '' ? $this->descriptionTextToHtml($comment) : '';
        }

        return $out;
    }

    /**
     * Collect property name => superseding property local name (schema:supersededBy).
     *
     * @param  list<array<string, mixed>>  $graph
     * @param  list<string>  $propertyNames
     * @return array<string, string|null> Property name => superseded_by local name, or null
     */
    private function collectPropertySupersededBy(array $graph, array $propertyNames): array
    {
        $propertySet = array_fill_keys($propertyNames, null);
        foreach ($graph as $node) {
            if (! $this->isSchemaProperty($node)) {
                continue;
            }

            $name = $this->schemaLocalName($node['@id'] ?? null);
            if ($name === null) {
                continue;
            }

            if (! array_key_exists($name, $propertySet)) {
                continue;
            }

            $supersededBy = $node['schema:supersededBy'] ?? null;
            $propertySet[$name] = $this->extractSingleReferenceLocalName($supersededBy);
        }

        return $propertySet;
    }

    /**
     * Build type => list of property names (including inherited) from domainIncludes.
     *
     * @param  list<array<string, mixed>>  $graph
     * @param  list<string>  $typeNames
     * @param  array<string, list<string>>  $typeAncestors
     * @return array<string, list<string>>
     */
    private function buildTypeProperties(
        array $graph,
        array $typeNames,
        array $typeAncestors
    ): array {
        $typeSet = array_fill_keys($typeNames, true);
        $propertyToTypes = [];
        foreach ($graph as $node) {
            if (! $this->isSchemaProperty($node)) {
                continue;
            }

            $propName = $this->schemaLocalName($node['@id']);
            if ($propName === null) {
                continue;
            }

            $domainIncludes = $node['schema:domainIncludes'] ?? null;
            $targets = $this->normalizeIdList($domainIncludes);
            foreach ($targets as $target) {
                $type = $this->schemaLocalName(\is_array($target) ? ($target['@id'] ?? '') : $target);
                if ($type !== null && isset($typeSet[$type])) {
                    $propertyToTypes[$propName][] = $type;
                }
            }
        }

        $typeProperties = [];
        foreach ($typeNames as $type) {
            $ancestors = array_merge([$type], $typeAncestors[$type] ?? []);
            $props = [];
            foreach ($propertyToTypes as $propName => $applicableTypes) {
                if (array_intersect($ancestors, $applicableTypes) !== []) {
                    $props[] = $propName;
                }
            }

            $props = array_values(array_unique($props));
            sort($props);
            $typeProperties[$type] = $props;
        }

        return $typeProperties;
    }

    /**
     * Collect property => domain type names and property => range type names from graph.
     *
     * @param  list<array<string, mixed>>  $graph
     * @param  list<string>  $propertyNames  Known schema property names (from collectPropertyDescriptions)
     * @return array{0: array<string, list<string>>, 1: array<string, list<string>>} [propertyDomains, propertyRanges]
     */
    private function collectPropertyDomainsAndRanges(array $graph, array $propertyNames): array
    {
        $propertyDomains = [];
        $propertyRanges = [];
        foreach ($propertyNames as $name) {
            $propertyDomains[$name] = [];
            $propertyRanges[$name] = [];
        }

        foreach ($graph as $node) {
            if (! $this->isSchemaProperty($node)) {
                continue;
            }

            $propName = $this->schemaLocalName($node['@id']);
            if ($propName === null) {
                continue;
            }

            if (! isset($propertyDomains[$propName])) {
                continue;
            }

            $domainIncludes = $node['schema:domainIncludes'] ?? null;
            $targets = $this->normalizeIdList($domainIncludes);
            foreach ($targets as $target) {
                $type = $this->schemaLocalName(\is_array($target) ? ($target['@id'] ?? '') : $target);
                if ($type !== null && $type !== '') {
                    $propertyDomains[$propName][] = $type;
                }
            }

            $rangeIncludes = $node['schema:rangeIncludes'] ?? null;
            $rangeTargets = $this->normalizeIdList($rangeIncludes);
            foreach ($rangeTargets as $target) {
                $type = $this->schemaLocalName(\is_array($target) ? ($target['@id'] ?? '') : $target);
                if ($type !== null && $type !== '') {
                    $propertyRanges[$propName][] = $type;
                }
            }

            $propertyDomains[$propName] = array_values(array_unique($propertyDomains[$propName]));
            $propertyRanges[$propName] = array_values(array_unique($propertyRanges[$propName]));
            sort($propertyDomains[$propName]);
            sort($propertyRanges[$propName]);
        }

        return [$propertyDomains, $propertyRanges];
    }

    /**
     * Merge Schema.org OWL-implied range types into property ranges (aligns with sdoowl.py
     * DEFAULTRANGES for non-datatype-only properties).
     *
     * @param  array<string, list<string>>  $propertyRanges
     * @return array<string, list<string>>
     */
    private function mergeOwlImpliedRangeIncludes(array $propertyRanges): array
    {
        $datatypeOnlyLookup = array_flip(self::OWL_DATATYPE_ONLY_RANGE_TYPES);
        $out = [];
        foreach ($propertyRanges as $propName => $ranges) {
            if ($ranges === []) {
                $out[$propName] = $ranges;

                continue;
            }

            $onlyDatatypePrimitives = true;
            foreach ($ranges as $type) {
                if (! isset($datatypeOnlyLookup[$type])) {
                    $onlyDatatypePrimitives = false;
                    break;
                }
            }

            if ($onlyDatatypePrimitives) {
                $out[$propName] = $ranges;

                continue;
            }

            $merged = $ranges;
            foreach (self::OWL_DEFAULT_EXTRA_RANGE_TYPES as $extra) {
                if (! in_array($extra, $merged, true)) {
                    $merged[] = $extra;
                }
            }

            $merged = array_values(array_unique($merged));
            sort($merged);
            $out[$propName] = $merged;
        }

        return $out;
    }

    /**
     * Add Role to schema_domain_includes for known Role-wrappable relationship properties.
     *
     * @param  array<string, list<string>>  $propertyDomains
     * @return array<string, list<string>>
     */
    private function mergeRoleWrappablePropertyDomains(array $propertyDomains): array
    {
        foreach (self::ROLE_WRAPPABLE_PROPERTIES as $propName) {
            if (! array_key_exists($propName, $propertyDomains)) {
                continue;
            }

            $domains = $propertyDomains[$propName];
            if (! in_array('Role', $domains, true)) {
                $domains[] = 'Role';
            }

            $domains = array_values(array_unique($domains));
            sort($domains);
            $propertyDomains[$propName] = $domains;
        }

        return $propertyDomains;
    }

    /**
     * Ensure SchemaType::Role lists known Role-wrappable relationship properties for authoring/tooling.
     *
     * @param  array<string, list<string>>  $typeProperties
     * @param  array<string, list<string>>  $typeAncestors  Type => ancestor type names
     * @return array<string, list<string>>
     */
    private function mergeRoleWrappableTypeProperties(array $typeProperties, array $typeAncestors): array
    {
        foreach ($typeProperties as $typeName => $props) {
            $ancestors = $typeAncestors[$typeName] ?? [];
            $isRoleOrDescendant = $typeName === 'Role' || in_array('Role', $ancestors, true);
            if (! $isRoleOrDescendant) {
                continue;
            }

            foreach (self::ROLE_WRAPPABLE_PROPERTIES as $propName) {
                if (! in_array($propName, $props, true)) {
                    $props[] = $propName;
                }
            }

            $props = array_values(array_unique($props));
            sort($props);
            $typeProperties[$typeName] = $props;
        }

        return $typeProperties;
    }

    /**
     * Collect DataType descriptions (rdfs:comment) from graph for the fixed DataType names.
     *
     * @param  list<array<string, mixed>>  $graph
     * @param  list<string>  $dataTypeNames
     * @return array<string, string|null>
     */
    private function collectDataTypeDescriptions(array $graph, array $dataTypeNames): array
    {
        $byId = $this->indexGraphById($graph);
        $schemaPrefix = 'https://schema.org/';
        $out = [];
        foreach ($dataTypeNames as $name) {
            $id = $schemaPrefix.$name;
            $node = $byId[$id] ?? $byId['schema:'.$name] ?? null;
            $comment = $this->commentToPlainText($node['rdfs:comment'] ?? null);
            $out[$name] = $comment !== null && $comment !== ''
                ? $this->descriptionTextToHtml($comment)
                : null;
        }

        return $out;
    }

    /**
     * Decode all HTML entities so var_export produces readable PHP (e.g., \' not &apos;, — not &#x2014;).
     *
     * Loops until stable so double-encoded entities (e.g., &amp;#x2014; from htmlspecialchars)
     * are fully decoded.
     *
     * @param  string  $html  String that may contain &apos;, &quot;, &amp;, &#x2014;, etc.
     * @return string Same string with entities replaced by actual characters
     */
    private function decodeHtmlEntitiesForPhpExport(string $html): string
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        while ($decoded !== $html) {
            $html = $decoded;
            $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $decoded;
    }

    /**
     * Normalize rdfs:comment to plain string (handles language-tagged array ["en", "text"]).
     *
     * @param  mixed  $value  rdfs:comment value (string or [lang, string])
     * @return string|null Plain text, or null if missing/empty
     */
    private function commentToPlainText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (\is_array($value) && isset($value[1]) && \is_string($value[1])) {
            $value = trim($value[1]);

            return $value === '' ? null : $value;
        }

        if (\is_array($value)) {
            foreach ($value as $v) {
                if (\is_string($v) && trim($v) !== '') {
                    return trim($v);
                }
            }
        }

        return null;
    }

    /**
     * Convert description text to HTML: [text](url) and [[Name]] become links.
     *
     * @param  string  $text  Plain description (may contain markdown-style links)
     * @return string HTML with links, rest escaped
     */
    private function descriptionTextToHtml(string $text): string
    {
        if (str_contains($text, '<a href=')) {
            return $text;
        }

        $schemaOrgBase = 'https://schema.org/';
        $pattern = '/\[([^\]]*)\]\(([^)]+)\)|\[\[([^\]]+)\]\]/';
        $offset = 0;
        $out = '';
        while (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $pos = $m[0][1];
            $len = strlen($m[0][0]);
            $out .= htmlspecialchars(substr($text, $offset, $pos - $offset), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($m[2][0] !== '') {
                $url = trim($m[2][0]);
                if (! preg_match('#^https?://#i', $url)) {
                    $url = $schemaOrgBase.ltrim($url, '/');
                }

                if (preg_match('#^https?://#i', $url)) {
                    $out .= '<a href="'.htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8').'" target="_blank" rel="noopener noreferrer">'.htmlspecialchars($m[1][0], ENT_QUOTES | ENT_HTML5, 'UTF-8').'</a>';
                } else {
                    $out .= htmlspecialchars($m[0][0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            } else {
                $name = trim($m[3][0]);
                $url = $schemaOrgBase.rawurlencode($name);
                $out .= '<a href="'.htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8').'" target="_blank" rel="noopener noreferrer">'.htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</a>';
            }

            $offset = $pos + $len;
        }

        return $out.htmlspecialchars(substr($text, $offset), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Build the full PHP source for the SchemaType enum.
     *
     * Property descriptions are type-agnostic and live on SchemaProperty; this enum references it.
     *
     * @param  list<string>  $typeNames
     * @param  array<string, string|null>  $descriptions
     * @param  array<string, list<string>>  $typeProperties
     * @param  array<string, list<string>>  $typeParents
     * @param  array<string, list<string>>  $typeChildren
     * @param  array<string, list<string>>  $typeAncestors
     * @param  array<string, list<string>>  $typeDescendants
     * @param  array<string, string|null>  $typeSupersededBy  Type name => superseded_by local name
     * @param  array<string, list<string>>  $typeToMembers  Enumeration type => member local names (for SchemaTypeInfo members)
     */
    private function buildEnumPhp(
        array $typeNames,
        array $descriptions,
        array $typeProperties,
        array $typeParents,
        array $typeChildren,
        array $typeAncestors,
        array $typeDescendants,
        array $typeSupersededBy,
        array $typeToMembers = []
    ): string {
        $casesPhp = $this->buildAttributeCasesPhp(
            $typeNames,
            $descriptions,
            $typeParents,
            $typeChildren,
            $typeAncestors,
            $typeDescendants,
            $typeProperties,
            $typeSupersededBy,
            $typeToMembers
        );
        $generatedDate = date('Y-m-d');

        return <<<PHP
<?php

namespace App\Enums;

use App\Enums\Attributes\SchemaTypeInfo;

/**
 * Schema.org types from schema.org JSON-LD vocabulary.
 *
 * Each case carries metadata via the SchemaTypeInfo attribute (rdfs_comment,
 * rdfs_sub_class_of, hierarchy, property_names). Property descriptions are
 * type-agnostic and live on SchemaProperty. Normalization follows schema-jsonld-normalization.md.
 *
 * Generated from schema.org on {$generatedDate} using
 * app/Services/EnumGeneration/Schema/SchemaTypeEnumGenerator.php.
 *
 * @see https://schema.org/docs/developers.html
 */
enum SchemaType: string
{
{$casesPhp}

    /**
     * Type description for a type name (defensive: null/empty returns null).
     *
     * @param  string|null  \$typeName  Schema.org type name
     * @return string|null Description HTML, or null if type name empty or not in enum
     */
    public static function typeDescriptionFor(?string \$typeName): ?string
    {
        if (\$typeName === null || \$typeName === '') {
            return null;
        }

        return self::tryFrom(\$typeName)?->typeDescription();
    }

    /**
     * Property description for a type and property name (defensive: null/empty returns null).
     *
     * @param  string|null  \$typeName  Schema.org type name
     * @param  string|null  \$propertyName  Schema.org property name
     * @return string|null Property description HTML, or null
     */
    public static function propertyDescriptionFor(?string \$typeName, ?string \$propertyName): ?string
    {
        if (\$typeName === null || \$typeName === '' || \$propertyName === null || \$propertyName === '') {
            return null;
        }
        if (self::tryFrom(\$typeName) === null) {
            return null;
        }

        return SchemaProperty::tryFrom(\$propertyName)?->description();
    }

    /**
     * Property names for a type (from schema.org domainIncludes + inheritance).
     * Use when Spatie class is missing for this type.
     *
     * @param  string  \$typeName  Schema.org type name
     * @return list<string>
     */
    public static function propertyNamesFor(string \$typeName): array
    {
        return self::tryFrom(\$typeName)?->propertyNames() ?? [];
    }

    /**
     * Immediate parent type names (rdfs:subClassOf targets).
     *
     * @param  string  \$typeName  Schema.org type name
     * @return list<string>
     */
    public static function parentsOf(string \$typeName): array
    {
        return self::tryFrom(\$typeName)?->info()?->rdfs_sub_class_of ?? [];
    }

    /**
     * Immediate child type names (types that have this type as rdfs:subClassOf).
     *
     * @param  string  \$typeName  Schema.org type name
     * @return list<string>
     */
    public static function childrenOf(string \$typeName): array
    {
        return self::tryFrom(\$typeName)?->info()?->children ?? [];
    }

    /**
     * All ancestor type names (transitive closure of parents).
     *
     * @param  string  \$typeName  Schema.org type name
     * @return list<string>
     */
    public static function ancestorsOf(string \$typeName): array
    {
        return self::tryFrom(\$typeName)?->info()?->ancestors ?? [];
    }

    /**
     * All descendant type names (transitive closure of children).
     *
     * @param  string  \$typeName  Schema.org type name
     * @return list<string>
     */
    public static function descendantsOf(string \$typeName): array
    {
        return self::tryFrom(\$typeName)?->info()?->descendants ?? [];
    }

    /**
     * Local name of the type or property that supersedes this type (schema:supersededBy), or null.
     *
     * @param  string  \$typeName  Schema.org type name
     */
    public static function supersededByFor(string \$typeName): ?string
    {
        return self::tryFrom(\$typeName)?->supersededBy();
    }

    /**
     * SchemaTypeInfo attribute for this case (read via reflection; no property cache — enums cannot have properties).
     */
    public function info(): ?SchemaTypeInfo
    {
        \$ref = (new \ReflectionEnum(self::class))->getCase(\$this->name);
        \$attrs = \$ref->getAttributes(SchemaTypeInfo::class);

        return isset(\$attrs[0]) ? \$attrs[0]->newInstance() : null;
    }

    /**
     * Type description (rdfs:comment) as HTML with links.
     *
     * @return string|null HTML description, or null if none
     */
    public function typeDescription(): ?string
    {
        return \$this->info()?->rdfs_comment;
    }

    /**
     * Local name of the type or property that supersedes this type (schema:supersededBy), or null.
     */
    public function supersededBy(): ?string
    {
        return \$this->info()?->superseded_by;
    }

    /**
     * Properties available for this type (property name => description HTML).
     *
     * @return array<string, string>
     */
    public function properties(): array
    {
        \$names = \$this->info()?->property_names ?? [];
        \$out = [];
        foreach (\$names as \$name) {
            \$desc = SchemaProperty::tryFrom(\$name)?->description();
            \$out[\$name] = \$desc ?? '';
        }

        return \$out;
    }

    /**
     * List of property names valid for this type.
     *
     * @return list<string>
     */
    public function propertyNames(): array
    {
        return array_keys(\$this->properties());
    }

    /**
     * Whether this type has a property with the given name.
     *
     * @param  string  \$propertyName  Schema.org property name
     */
    public function hasProperty(string \$propertyName): bool
    {
        return \in_array(\$propertyName, \$this->propertyNames(), true);
    }

    /**
     * For enumeration types: member local names. Empty for non-enumeration types.
     *
     * @return list<string>
     */
    public function members(): array
    {
        return \$this->info()?->members ?? [];
    }

    /**
     * For enumeration types: member local names. Empty if not an enumeration type.
     *
     * @param  string  \$typeName  Schema.org type name
     * @return list<string>
     */
    public static function membersFor(string \$typeName): array
    {
        return self::tryFrom(\$typeName)?->members() ?? [];
    }

    /**
     * Description for a single property.
     *
     * @param  string  \$propertyName  Schema.org property name
     * @return string|null Property description HTML, or null
     */
    public function propertyDescription(string \$propertyName): ?string
    {
        return SchemaProperty::tryFrom(\$propertyName)?->description();
    }

    /**
     * Human-readable label for this type (the type name).
     */
    public function label(): string
    {
        return \$this->value;
    }

    /**
     * Canonical Schema.org URL for this type (e.g., https://schema.org/Thing).
     */
    public function toSchemaOrgUrl(): string
    {
        return 'https://schema.org/'.\$this->value;
    }
}

PHP;
    }

    /**
     * Build the full PHP source for the SchemaProperty enum (type-agnostic property descriptions and domain/range).
     *
     * @param  list<string>  $propertyNames
     * @param  array<string, string>  $descriptions  property name => HTML description
     * @param  array<string, list<string>>  $domains  property => type names (schema_domain_includes)
     * @param  array<string, list<string>>  $ranges  property => type names (range_includes)
     * @param  array<string, string|null>  $supersededBy  property name => superseded_by local name
     */
    private function buildSchemaPropertyEnumPhp(
        array $propertyNames,
        array $descriptions,
        array $domains,
        array $ranges,
        array $supersededBy
    ): string {
        $casesPhp = $this->buildSchemaPropertyCasesPhp(
            $propertyNames,
            $descriptions,
            $domains,
            $ranges,
            $supersededBy
        );
        $generatedDate = date('Y-m-d');

        return <<<PHP
<?php

namespace App\Enums;

use App\Enums\Attributes\SchemaPropertyInfo;

/**
 * Schema.org properties from schema.org JSON-LD vocabulary.
 *
 * Each case carries metadata via the SchemaPropertyInfo attribute (rdfs_comment,
 * schema_domain_includes, range_includes). Descriptions are type-agnostic.
 *
 * range_includes: declared schema:rangeIncludes plus OWL-implied Role, Text, and URL when the
 * property is not limited to datatype-only primitives (Boolean, Date, DateTime, Number, Float,
 * Integer, Time)—see app/Services/EnumGeneration/Schema/schema-jsonld-normalization.md and
 * schema.org software/util/sdoowl.py DEFAULTRANGES.
 *
 * Generated from schema.org on {$generatedDate} using
 * app/Services/EnumGeneration/Schema/SchemaTypeEnumGenerator.php.
 *
 * @see https://schema.org/docs/developers.html
 * @see https://schema.org/docs/datamodel.html
 */
enum SchemaProperty: string
{
{$casesPhp}

    /**
     * SchemaPropertyInfo attribute for this case (read via reflection).
     */
    public function info(): ?SchemaPropertyInfo
    {
        \$ref = (new \ReflectionEnum(self::class))->getCase(\$this->name);
        \$attrs = \$ref->getAttributes(SchemaPropertyInfo::class);

        return isset(\$attrs[0]) ? \$attrs[0]->newInstance() : null;
    }

    /**
     * Property description (rdfs:comment) as HTML with links.
     */
    public function description(): ?string
    {
        return \$this->info()?->rdfs_comment;
    }

    /**
     * Type names this property applies to (schema:domainIncludes).
     *
     * @return list<string>
     */
    public function domainTypes(): array
    {
        return \$this->info()?->schema_domain_includes ?? [];
    }

    /**
     * Type names for values: declared schema:rangeIncludes plus Schema.org OWL-implied types
     * (Role, Text, URL) when applicable—see generator and schema-jsonld-normalization.md.
     *
     * @return list<string>
     */
    public function rangeTypes(): array
    {
        return \$this->info()?->range_includes ?? [];
    }

    /**
     * Local name of the property that supersedes this property (schema:supersededBy), or null.
     */
    public function supersededBy(): ?string
    {
        return \$this->info()?->superseded_by;
    }

    /**
     * Human-readable label (the property name).
     */
    public function label(): string
    {
        return \$this->value;
    }
}
PHP."\n";
    }

    /**
     * Build SchemaProperty enum cases with SchemaPropertyInfo attribute per case.
     *
     * @param  list<string>  $propertyNames
     * @param  array<string, string>  $descriptions
     * @param  array<string, list<string>>  $domains
     * @param  array<string, list<string>>  $ranges
     * @param  array<string, string|null>  $supersededBy
     */
    private function buildSchemaPropertyCasesPhp(
        array $propertyNames,
        array $descriptions,
        array $domains,
        array $ranges,
        array $supersededBy
    ): string {
        $lines = [];
        foreach ($propertyNames as $name) {
            $attr = $this->formatSchemaPropertyInfoAttribute(
                $descriptions[$name] ?? '',
                $domains[$name] ?? [],
                $ranges[$name] ?? [],
                $supersededBy[$name] ?? null
            );
            $lines[] = '    '.$attr;
            $lines[] = '    case '.$this->propertyCaseName($name).' = '.var_export($name, true).';';
        }

        return implode("\n", $lines);
    }

    /**
     * Format a single #[SchemaPropertyInfo(...)] attribute for one enum case.
     *
     * @param  list<string>  $domainIncludes
     * @param  list<string>  $rangeIncludes
     * @param  string|null  $schemaSupersededBy  Local name of the property that supersedes this property
     */
    private function formatSchemaPropertyInfoAttribute(
        string $rdfsComment,
        array $domainIncludes,
        array $rangeIncludes,
        ?string $schemaSupersededBy = null
    ): string {
        $commentPhp = $rdfsComment !== '' ? var_export($this->decodeHtmlEntitiesForPhpExport($rdfsComment), true) : 'null';
        $domainPhp = $this->exportArrayInline($domainIncludes);
        $rangePhp = $this->exportArrayInline($rangeIncludes);
        $supersededByPhp = $schemaSupersededBy !== null ? var_export($schemaSupersededBy, true) : 'null';

        return '#[SchemaPropertyInfo(rdfs_comment: '.$commentPhp.', schema_domain_includes: '.$domainPhp.', range_includes: '.$rangePhp.', superseded_by: '.$supersededByPhp.')]';
    }

    /**
     * Enum case identifier for a property name (valid PHP identifier; reserved/leading digit get "Property" prefix).
     *
     * @param  string  $propertyName  Schema.org property name (e.g., name, class)
     */
    private function propertyCaseName(string $propertyName): string
    {
        if ($propertyName === '') {
            return $propertyName;
        }

        if (preg_match('/^\d/', $propertyName) === 1) {
            return 'Property'.$propertyName;
        }

        $reserved = ['class', 'default', 'case', 'enum', 'and', 'or', 'try', 'catch', 'finally', 'switch', 'match', 'return', 'extends', 'implements', 'interface', 'trait', 'new', 'clone', 'instanceof', 'insteadof', 'as', 'goto', 'const', 'static', 'abstract', 'final', 'private', 'protected', 'public', 'readonly', 'fn', 'function', 'if', 'else', 'elseif', 'endif', 'for', 'endfor', 'foreach', 'endforeach', 'while', 'endwhile', 'do', 'break', 'continue', 'declare', 'enddeclare', 'print', 'echo', 'require', 'require_once', 'include', 'include_once', 'list', 'unset', 'isset', 'empty', 'die', 'exit', 'eval', 'include', 'yield', 'from', 'var', 'null', 'true', 'false'];
        if (in_array(strtolower($propertyName), $reserved, true)) {
            return 'Property'.$propertyName;
        }

        return $propertyName;
    }

    /**
     * Build the full PHP source for the SchemaDataType enum (fixed list of DataType names).
     *
     * @param  list<string>  $dataTypeNames
     * @param  array<string, string|null>  $descriptions
     */
    private function buildSchemaDataTypeEnumPhp(array $dataTypeNames, array $descriptions): string
    {
        $casesPhp = $this->buildSchemaDataTypeCasesPhp($dataTypeNames, $descriptions);
        $generatedDate = date('Y-m-d');

        return <<<PHP
<?php

namespace App\Enums;

use App\Enums\Attributes\SchemaDataTypeInfo;

/**
 * Schema.org DataType values from schema.org JSON-LD vocabulary.
 *
 * Each case carries optional metadata via the SchemaDataTypeInfo attribute
 * (rdfs_comment). Cases are a fixed list per schema.org.
 *
 * Generated from schema.org on {$generatedDate} using
 * app/Services/EnumGeneration/Schema/SchemaTypeEnumGenerator.php.
 *
 * @see https://schema.org/docs/developers.html
 */
enum SchemaDataType: string
{
{$casesPhp}

    /**
     * SchemaDataTypeInfo attribute for this case (read via reflection).
     */
    public function info(): ?SchemaDataTypeInfo
    {
        \$ref = (new \ReflectionEnum(self::class))->getCase(\$this->name);
        \$attrs = \$ref->getAttributes(SchemaDataTypeInfo::class);

        return isset(\$attrs[0]) ? \$attrs[0]->newInstance() : null;
    }

    /**
     * DataType description (rdfs:comment) as HTML with links.
     */
    public function description(): ?string
    {
        return \$this->info()?->rdfs_comment;
    }

    /**
     * Human-readable label (the DataType name).
     */
    public function label(): string
    {
        return \$this->value;
    }
}

PHP;
    }

    /**
     * Build SchemaDataType enum cases with SchemaDataTypeInfo attribute per case.
     *
     * @param  list<string>  $dataTypeNames
     * @param  array<string, string|null>  $descriptions
     */
    private function buildSchemaDataTypeCasesPhp(array $dataTypeNames, array $descriptions): string
    {
        $lines = [];
        foreach ($dataTypeNames as $name) {
            $attr = $this->formatSchemaDataTypeInfoAttribute($descriptions[$name] ?? null);
            $lines[] = '    '.$attr;
            $lines[] = '    case '.$this->dataTypeCaseName($name).' = '.var_export($name, true).';';
        }

        return implode("\n", $lines);
    }

    /**
     * Format a single #[SchemaDataTypeInfo(...)] attribute for one enum case.
     */
    private function formatSchemaDataTypeInfoAttribute(?string $rdfsComment): string
    {
        $commentPhp = $rdfsComment !== null && $rdfsComment !== ''
            ? var_export($this->decodeHtmlEntitiesForPhpExport($rdfsComment), true)
            : 'null';

        return '#[SchemaDataTypeInfo(rdfs_comment: '.$commentPhp.')]';
    }

    /**
     * Enum case identifier for a DataType name (valid PHP identifier; True/False and reserved get "DataType" prefix).
     *
     * @param  string  $dataTypeName  Schema.org DataType name (e.g., Boolean, True, False)
     */
    private function dataTypeCaseName(string $dataTypeName): string
    {
        if ($dataTypeName === '') {
            return $dataTypeName;
        }

        if (preg_match('/^\d/', $dataTypeName) === 1) {
            return 'DataType'.$dataTypeName;
        }

        $reserved = ['class', 'default', 'case', 'enum', 'and', 'or', 'try', 'catch', 'finally', 'switch', 'match', 'return', 'extends', 'implements', 'interface', 'trait', 'new', 'clone', 'instanceof', 'insteadof', 'as', 'goto', 'const', 'static', 'abstract', 'final', 'private', 'protected', 'public', 'readonly', 'fn', 'function', 'if', 'else', 'elseif', 'endif', 'for', 'endfor', 'foreach', 'endforeach', 'while', 'endwhile', 'do', 'break', 'continue', 'declare', 'enddeclare', 'print', 'echo', 'require', 'require_once', 'include', 'include_once', 'list', 'unset', 'isset', 'empty', 'die', 'exit', 'eval', 'include', 'yield', 'from', 'var', 'null', 'true', 'false'];
        if (in_array(strtolower($dataTypeName), $reserved, true)) {
            return 'DataType'.$dataTypeName;
        }

        return $dataTypeName;
    }

    /**
     * Build the enum cases block with SchemaTypeInfo attribute per case.
     *
     * @param  list<string>  $typeNames
     * @param  array<string, string|null>  $descriptions
     * @param  array<string, list<string>>  $typeParents
     * @param  array<string, list<string>>  $typeChildren
     * @param  array<string, list<string>>  $typeAncestors
     * @param  array<string, list<string>>  $typeDescendants
     * @param  array<string, list<string>>  $typeProperties
     * @param  array<string, string|null>  $typeSupersededBy
     * @param  array<string, list<string>>  $typeToMembers  Enumeration type => member local names
     */
    private function buildAttributeCasesPhp(
        array $typeNames,
        array $descriptions,
        array $typeParents,
        array $typeChildren,
        array $typeAncestors,
        array $typeDescendants,
        array $typeProperties,
        array $typeSupersededBy,
        array $typeToMembers = []
    ): string {
        $lines = [];
        foreach ($typeNames as $name) {
            $attr = $this->formatSchemaTypeInfoAttribute(
                $descriptions[$name] ?? null,
                $typeParents[$name] ?? [],
                $typeChildren[$name] ?? [],
                $typeAncestors[$name] ?? [],
                $typeDescendants[$name] ?? [],
                $typeProperties[$name] ?? [],
                $this->slugForTypeName($name),
                $typeSupersededBy[$name] ?? null,
                $typeToMembers[$name] ?? []
            );
            $lines[] = '    '.$attr;
            $lines[] = '    case '.$this->enumCaseName($name).' = '.var_export($name, true).';';
        }

        return implode("\n", $lines);
    }

    /**
     * Format a single #[SchemaTypeInfo(...)] attribute for one enum case.
     *
     * @param  list<string>  $parents
     * @param  list<string>  $children
     * @param  list<string>  $ancestors
     * @param  list<string>  $descendants
     * @param  list<string>  $propertyNames
     * @param  string  $slug  Sluggified type name for scope handles, URLs
     * @param  string|null  $supersededBy  Local name of the type or property that supersedes this type
     * @param  list<string>  $members  For enumeration types: member local names
     */
    private function formatSchemaTypeInfoAttribute(
        ?string $rdfsComment,
        array $parents,
        array $children,
        array $ancestors,
        array $descendants,
        array $propertyNames,
        string $slug,
        ?string $supersededBy = null,
        array $members = []
    ): string {
        $commentPhp = $rdfsComment !== null && $rdfsComment !== ''
            ? var_export($this->decodeHtmlEntitiesForPhpExport($rdfsComment), true)
            : 'null';
        $parentsPhp = $this->exportArrayInline($parents);
        $childrenPhp = $this->exportArrayInline($children);
        $ancestorsPhp = $this->exportArrayInline($ancestors);
        $descendantsPhp = $this->exportArrayInline($descendants);
        $propsPhp = $this->exportArrayInline($propertyNames);
        $slugPhp = var_export($slug, true);
        $supersededByPhp = $supersededBy !== null ? var_export($supersededBy, true) : 'null';
        $membersPhp = $this->exportArrayInline($members);

        return '#[SchemaTypeInfo(rdfs_comment: '.$commentPhp.', rdfs_sub_class_of: '.$parentsPhp.', children: '.$childrenPhp.', ancestors: '.$ancestorsPhp.', descendants: '.$descendantsPhp.', property_names: '.$propsPhp.', slug: '.$slugPhp.', superseded_by: '.$supersededByPhp.', members: '.$membersPhp.')]';
    }

    /**
     * Sluggify a Schema.org type name (lowercase, spaces and hyphens → underscore).
     *
     * @param  string  $typeName  e.g. Thing, 3DModel, Some-Type
     * @return string e.g. thing, 3dmodel, some_type
     */
    private function slugForTypeName(string $typeName): string
    {
        return str_replace([' ', '-'], '_', strtolower($typeName));
    }

    /**
     * Export list of strings to a single-line PHP array (for attribute args).
     *
     * @param  list<string>  $list
     */
    private function exportArrayInline(array $list): string
    {
        if ($list === []) {
            return '[]';
        }

        $parts = [];
        foreach ($list as $s) {
            $parts[] = var_export($s, true);
        }

        return '['.implode(', ', $parts).']';
    }

    /**
     * Enum case identifier from type name (must be valid PHP identifier).
     * Names starting with a digit or that are PHP reserved get "Type" prefix.
     *
     * @param  string  $typeName  Schema.org type name (e.g., Thing, 3DModel, Class)
     */
    private function enumCaseName(string $typeName): string
    {
        if ($typeName === '') {
            return $typeName;
        }

        if (preg_match('/^\d/', $typeName) === 1) {
            return 'Type'.$typeName;
        }

        $reserved = ['class', 'default', 'case', 'enum', 'and', 'or', 'try', 'catch', 'finally', 'switch', 'match', 'return', 'extends', 'implements', 'interface', 'trait', 'new', 'clone', 'instanceof', 'insteadof', 'as', 'goto', 'const', 'static', 'abstract', 'final', 'private', 'protected', 'public', 'readonly', 'fn', 'function', 'if', 'else', 'elseif', 'endif', 'for', 'endfor', 'foreach', 'endforeach', 'while', 'endwhile', 'do', 'break', 'continue', 'declare', 'enddeclare', 'print', 'echo', 'require', 'require_once', 'include', 'include_once', 'list', 'unset', 'isset', 'empty', 'die', 'exit', 'eval', 'include', 'yield', 'from', 'var', 'null', 'true', 'false'];
        if (in_array(strtolower($typeName), $reserved, true)) {
            return 'Type'.$typeName;
        }

        return $typeName;
    }
}
