<?php

declare(strict_types=1);

namespace App\Services\People;

use App\Services\Organizations\OrganizationPublicLabelService;
use App\Support\RoleTitleScopeUnit;
use App\Support\RoleTitleValue;
use Illuminate\Support\Collection as SupportCollection;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Stache\Repositories\EntryRepository as StacheEntryRepository;

/**
 * Finds `role_title` scopes that name an organizational unit as typed text and
 * links the ones it can link without changing the composed title (UAMS-Web/uams-statamic-archive#2146).
 *
 * A scope used to name its unit by typing it, so a row could link one unit and
 * name another, and nothing reported it. For each typed scope that takes the
 * article (`scope_article: the`, UAMS-Web/uams-statamic-archive#2546), the linker looks for the unit it names:
 *
 * - when the row links its own unit (`department` / `service_line`), the typed
 *   name must be that unit's composing name exactly;
 * - otherwise it must be exactly one `organizations` entry's composing name.
 *
 * The composing name is what a link prints: the unit's `formal_name`, else its
 * public name (`RoleTitleScopeUnit::displayName()`). "Exactly" is the whole
 * guarantee: linking a match cannot change the composed title, because the scope
 * already takes the article and its name comes from the same composing name the
 * typed text equals. Every other case is REPORTED and left as it is, since
 * rewriting it would change what the title says without anyone deciding to.
 */
final readonly class RoleTitleScopeUnitLinker
{
    /**
     * A typed name that equals the unit's display name: linkable.
     */
    public const string MATCH = 'match';

    /**
     * A typed name that differs from the row's own linked unit.
     */
    public const string DIFFERS_FROM_ROW = 'differs_from_row';

    /**
     * A typed name matching more than one unit's display name.
     */
    public const string AMBIGUOUS = 'ambiguous';

    /**
     * A typed name matching no unit's display name.
     */
    public const string NO_UNIT = 'no_unit';

    /**
     * An existing link that does not resolve to a named unit.
     */
    public const string BROKEN_LINK = 'broken_link';

    /**
     * An existing link to a different unit than the row's own link.
     */
    public const string LINK_DIFFERS_FROM_ROW = 'link_differs_from_row';

    /**
     * A typed name beside a selected object (UAMS-Web/uams-statamic-archive#2546). A typed scope names no unit,
     * so the object composes today; linking would suppress it and change the title,
     * and the linked row could not be saved until one of the two is cleared.
     */
    public const string OBJECT_SELECTED = 'object_selected';

    /**
     * Create the linker.
     *
     * @param  OrganizationPublicLabelService  $labels  Resolves each unit's display name
     * @param  StacheEntryRepository  $entries  Statamic Stache entry repository (`whereCollection` returns a typed collection)
     */
    public function __construct(
        private OrganizationPublicLabelService $labels,
        private StacheEntryRepository $entries
    ) {}

    /**
     * Scan every entry of a collection and classify each unit-naming scope.
     *
     * With `$write`, each MATCH is linked and its typed name cleared, and the
     * entry is saved quietly. Nothing else is ever written.
     *
     * @param  string  $collection  Collection handle to scan (e.g. `people`)
     * @param  bool  $write  Whether to link the matches
     * @return list<array{entry: string, path: string, status: string, typed: string, unit: string|null, unit_name: string|null}> One finding per unit-naming scope
     */
    public function scan(
        string $collection,
        bool $write
    ): array {
        $rowLinkHandles = $this->rowLinkHandles($collection);
        $index = $this->unitIndex();

        $findings = [];

        foreach ($this->entriesIn($collection) as $entry) {
            $data = $entry->data();

            $result = $this->linkInData(
                // Entry::data() always returns a collection, so the guard always holds
                // @pest-mutate-ignore: InstanceOfToTrue
                $data instanceof SupportCollection ? $data->all() : [],
                $this->entryId($entry),
                $rowLinkHandles,
                $index,
                $write
            );

            \array_push($findings, ...$result['findings']);

            if ($write && $result['changed']) {
                $entry->data($result['data']);
                $entry->saveQuietly();
            }
        }

        return $findings;
    }

    /**
     * Classify, and with `$write` link, every role_title scope in one entry's data.
     *
     * @param  array<array-key, mixed>  $data  The entry's stored data
     * @param  string  $entryId  Entry id recorded on each finding
     * @param  list<string>  $rowLinkHandles  Sibling keys that link a row's unit
     * @param  array<string, list<string>>  $index  Composing name to unit ids
     * @param  bool  $write  Whether to link the matches
     * @return array{data: array<array-key, mixed>, changed: bool, findings: list<array{entry: string, path: string, status: string, typed: string, unit: string|null, unit_name: string|null}>}
     */
    public function linkInData(
        array $data,
        string $entryId,
        array $rowLinkHandles,
        array $index,
        bool $write
    ): array {
        $changed = false;
        $findings = [];

        $this->walk($data, '', $rowLinkHandles, $index, $entryId, $write, $changed, $findings);

        return ['data' => $data, 'changed' => $changed, 'findings' => $findings];
    }

    /**
     * Walk a data tree, classifying each row that carries a role_title scope.
     *
     * A row is any array holding a role_title payload (an array with a `scope`
     * group) beside, possibly, the row's own unit link.
     *
     * @param  array<array-key, mixed>  $node  Data node, updated in place on write
     * @param  list<string>  $rowLinkHandles  Sibling keys that link a row's unit
     * @param  array<string, list<string>>  $index  Composing name to unit ids
     * @param  list<array{entry: string, path: string, status: string, typed: string, unit: string|null, unit_name: string|null}>  $findings  Findings, appended to
     */
    private function walk(
        array &$node,
        string $path,
        array $rowLinkHandles,
        array $index,
        string $entryId,
        bool $write,
        bool &$changed,
        array &$findings
    ): void {
        foreach ($node as $key => &$child) {
            if (! \is_array($child)) {
                continue;
            }

            $childPath = $path === '' ? (string) $key : $path.'.'.$key;

            // A role_title payload: classify its scope against the row it sits in
            if (\is_array($child[RoleTitleScopeUnit::GROUP_KEY] ?? null) && \array_key_exists('configuration', $child)) {
                $finding = $this->classify(
                    $child,
                    $this->rowLinkedId($node, $rowLinkHandles),
                    $index
                );

                if ($finding !== null) {
                    $findings[] = ['entry' => $entryId, 'path' => $childPath] + $finding;

                    if ($write && $finding['status'] === self::MATCH) {
                        $child[RoleTitleScopeUnit::GROUP_KEY][RoleTitleScopeUnit::NESTED_KEY] = $finding['unit'];
                        $child[RoleTitleScopeUnit::GROUP_KEY]['scope_name'] = '';
                        $child[RoleTitleScopeUnit::GROUP_KEY]['scope_article'] = null;
                        $changed = true;
                    }
                }

                continue;
            }

            $this->walk($child, $childPath, $rowLinkHandles, $index, $entryId, $write, $changed, $findings);
        }

        unset($child);
    }

    /**
     * Classify one role_title payload's scope, or null when it names no unit.
     *
     * @param  array<array-key, mixed>  $title  role_title payload
     * @param  string|null  $rowLinked  The row's own linked unit id
     * @param  array<string, list<string>>  $index  Composing name to unit ids
     * @return array{status: string, typed: string, unit: string|null, unit_name: string|null}|null
     */
    private function classify(
        array $title,
        ?string $rowLinked,
        array $index
    ): ?array {
        // `walk()` only classifies a payload whose scope is an array
        /** @var array<string, mixed> $scope */
        $scope = $title[RoleTitleScopeUnit::GROUP_KEY];
        $linked = RoleTitleScopeUnit::idFromScope($scope);
        $typed = \is_string($scope['scope_name'] ?? null) ? \trim($scope['scope_name']) : '';

        // Already linked: only a broken or disagreeing link is worth reporting
        if ($linked !== null) {
            $name = RoleTitleScopeUnit::displayName($linked);

            if ($name === null) {
                return ['status' => self::BROKEN_LINK, 'typed' => $typed, 'unit' => $linked, 'unit_name' => null];
            }

            if ($rowLinked !== null && $rowLinked !== $linked) {
                return ['status' => self::LINK_DIFFERS_FROM_ROW, 'typed' => $typed, 'unit' => $linked, 'unit_name' => $name];
            }

            // An agreeing link reports nothing, even beside a leftover typed name
            // that content written outside the fieldtype can still carry
            return null;
        }

        // No typed name, or one without the article: a discipline, which names no unit
        $article = $scope['scope_article'] ?? null;

        if ($typed === '' || ! \is_string($article) || \trim($article) !== 'the') {
            return null;
        }

        // Linking beside a composing object would drop the object from the title
        if (RoleTitleValue::objectSelected($title)) {
            return ['status' => self::OBJECT_SELECTED, 'typed' => $typed, 'unit' => null, 'unit_name' => null];
        }

        // The row links its unit: the typed name must be that unit's name
        if ($rowLinked !== null) {
            $rowName = RoleTitleScopeUnit::displayName($rowLinked);

            return $rowName === $typed
                ? ['status' => self::MATCH, 'typed' => $typed, 'unit' => $rowLinked, 'unit_name' => $rowName]
                : ['status' => self::DIFFERS_FROM_ROW, 'typed' => $typed, 'unit' => $rowLinked, 'unit_name' => $rowName];
        }

        // Otherwise the typed name must be exactly one unit's name
        $ids = $index[$typed] ?? [];

        return match (\count($ids)) {
            0 => ['status' => self::NO_UNIT, 'typed' => $typed, 'unit' => null, 'unit_name' => null],
            1 => ['status' => self::MATCH, 'typed' => $typed, 'unit' => $ids[0], 'unit_name' => $typed],
            default => ['status' => self::AMBIGUOUS, 'typed' => $typed, 'unit' => null, 'unit_name' => null],
        };
    }

    /**
     * The row's own linked unit, read from the first row-link key present.
     *
     * @param  array<array-key, mixed>  $row  The row the role_title payload sits in
     * @param  list<string>  $rowLinkHandles  Candidate sibling keys
     * @return string|null Row-linked unit id, or null
     */
    private function rowLinkedId(
        array $row,
        array $rowLinkHandles
    ): ?string {
        foreach ($rowLinkHandles as $handle) {
            $id = RoleTitleScopeUnit::normalizeId($row[$handle] ?? null);

            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Every unit's display name mapped to the unit ids carrying it.
     *
     * @return array<string, list<string>> Display name to ids
     */
    private function unitIndex(): array
    {
        $index = [];

        foreach (RoleTitleScopeUnit::collections() as $handle) {
            foreach ($this->entriesIn($handle) as $unit) {
                $name = RoleTitleScopeUnit::formalName($unit) ?? $this->labels->displayName($unit);

                if ($name !== null) {
                    $index[$name][] = $this->entryId($unit);
                }
            }
        }

        return $index;
    }

    /**
     * Every entry of a collection, or none when the collection does not exist.
     *
     * @return list<Entry> Entries in the collection
     */
    private function entriesIn(
        string $handle
    ): array {
        if (! Collection::find($handle) instanceof CollectionContract) {
            return [];
        }

        $entries = [];

        foreach ($this->entries->whereCollection($handle) as $entry) {
            if ($entry instanceof Entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * An entry's id as a string, or an empty string when it has none.
     */
    private function entryId(
        Entry $entry
    ): string {
        $id = $entry->id();

        return \is_string($id) ? $id : '';
    }

    /**
     * The sibling keys that link a row's unit, read from every role_title field's
     * `scope_unit_row_link` config in the collection's blueprints.
     *
     * @return list<string> Row-link handles
     */
    private function rowLinkHandles(
        string $collection
    ): array {
        $handles = [];
        $collectionObject = Collection::find($collection);

        if (! $collectionObject instanceof CollectionContract) {
            return [];
        }

        /** @var SupportCollection<int, Blueprint> $blueprints */
        $blueprints = $collectionObject->entryBlueprints();

        foreach ($blueprints as $blueprint) {
            /** @var SupportCollection<string, Field> $fields */
            $fields = $blueprint->fields()->all();

            foreach ($fields as $field) {
                $this->collectRowLinkHandles($field->config(), $handles);
            }
        }

        return \array_values(\array_unique($handles));
    }

    /**
     * Collect `scope_unit_row_link` values from role_title configs in a tree.
     *
     * @param  array<array-key, mixed>  $config  Field config (walked recursively)
     * @param  list<string>  $handles  Collected handles, appended to
     */
    private function collectRowLinkHandles(
        array $config,
        array &$handles
    ): void {
        if (($config['type'] ?? null) === 'role_title') {
            $link = $config['scope_unit_row_link'] ?? null;

            if (\is_string($link) && \trim($link) !== '') {
                $handles[] = \trim($link);
            }
        }

        foreach ($config as $value) {
            if (\is_array($value)) {
                $this->collectRowLinkHandles($value, $handles);
            }
        }
    }
}
