<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

use Illuminate\Support\Collection;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Term;
use Statamic\Fields\Blueprint;
use Statamic\Globals\GlobalSet as GlobalSetItem;
use Statamic\Globals\Variables;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * Walk all content and emit one record per link occurrence, with its precise location and kind.
 *
 * The walk is modelled on App\Support\References\EntryReferenceRepointer, which already traverses
 * entries, global-set localizations and terms arbitrarily deep. Two things differ deliberately.
 *
 * It is FIELDTYPE-DRIVEN rather than string-driven: the repointer can safely inspect every string
 * because it matches whole entry ids, while a link inventory that scraped every string would emit
 * permanent false positives out of this repository's 34 `code` fields and its schema-template
 * JSON. Extraction therefore happens only where a fieldtype actually carries links.
 *
 * And it records the PATH to each value, not just the top-level field. With 97 replicator fields
 * in this repository, `field: page_builder` is not a location an editor can act on.
 *
 * This slice performs no network I/O and resolves nothing: running it with the network unavailable
 * produces identical output.
 *
 * ## Surviving mutants, and why three classes of them cannot be killed
 *
 * Under the "no unexplained survivors" criterion (UAMS-Web/uams-statamic-archive#2265) every survivor is killed, deleted, or
 * annotated with a reason. The `@pest-mutate-ignore` comments through this file are per-line and
 * per-mutator, and each cites one of the three classes below rather than re-arguing it (UAMS-Web/uams-statamic-archive#2274).
 *
 * TYPE-NARROWING GUARDS THE ANALYSER REQUIRES. `Entry::all()`, `GlobalSet::all()` and the term
 * query are typed loosely enough that larastan cannot resolve the methods called on their members
 * at level 10, so each loop opens with an `instanceof` guard. No fixture reaches the false branch,
 * because the facade cannot hand back the wrong type — the guard exists to satisfy the analyser.
 * `InstanceOfToTrue` therefore survives at every such site. Deleting the guard would red one gate
 * to green another, so this is a tension between two gates rather than a coverage gap.
 *
 * FALLBACKS FOR STATES THE CONTRACT EXCLUDES. The same narrowing produces `is_string($x) ? $x : ''`
 * and `$path[0] ?? ''`. The fallback arm is unreachable — a collection handle, a locale and a
 * taxonomy handle are always strings, and `record()` is never called with an empty path — so
 * `EmptyStringToNotEmpty` cannot be observed. Where such an arm IS reached, its result is dropped
 * downstream: `LinkClassifier::classify()` returns null for every bare word a mutant substitutes,
 * measured for `''`, `'a'`, `'x'` and `'0'`.
 *
 * THE STRICT FLAG ON TWO STRINGS. `in_array($type, self::<TYPES>, true)` compares a string against
 * `list<string>`, so loose and strict comparison agree on every input and `TrueToFalse` is
 * unobservable. Identical to the two sites already annotated in LinkClassifier.
 */
final class LinkExtractor
{
    /**
     * Fieldtypes whose stored value is, in whole, a link or reference.
     *
     * @var list<string>
     */
    private const array LINK_VALUE_TYPES = ['link', 'redirect'];

    /**
     * Fieldtypes holding references to other content, stored as ids or `kind::ref` strings.
     *
     * @var array<string, bool> Fieldtype => whether a bare id in it means an entry relation.
     */
    private const array RELATION_TYPES = [
        'entries' => true,
        'assets' => false,
        'terms' => false,
        'terms_search_index' => false,
        'users' => false,
    ];

    /**
     * Fieldtypes rendering rich text, where links are embedded in markup and must be scraped.
     *
     * @var list<string>
     */
    private const array MARKUP_TYPES = ['html', 'markdown', 'textarea'];

    /**
     * Fieldtypes that never carry a link, and whose contents would produce false positives.
     *
     * `code` holds source, and schema templates hold JSON whose string values are not links.
     *
     * @var list<string>
     */
    private const array EXCLUDED_TYPES = ['code'];

    /** @var list<LinkOccurrence> Occurrences collected during the current walk. */
    private array $occurrences = [];

    /** @var int Entries loaded during the current walk, so scoping can be asserted on work. */
    private int $entriesLoaded = 0;

    /**
     * @param  LinkClassifier  $classifier  Assigns a kind to each extracted value.
     */
    public function __construct(private readonly LinkClassifier $classifier) {}

    /**
     * Walk every entry, global-set localization and term, and return every link found.
     *
     * @return list<LinkOccurrence> One record per link occurrence, in traversal order.
     */
    public function extract(): array
    {
        $this->occurrences = [];
        $this->entriesLoaded = 0;

        $this->walkEntries();
        $this->walkGlobals();
        $this->walkTerms();

        return $this->occurrences;
    }

    /**
     * Walk only the entries a scan is scoped to (UAMS-Web/uams-statamic-archive#1766).
     *
     * Scoping happens in the QUERY, not by walking everything and discarding the rest. With 255
     * sites that difference is the whole cost of a scoped scan: multisite hydration is this
     * repository's dominant boot cost, and a filter applied after loading pays it in full while
     * appearing, from the row count alone, to have narrowed the work.
     *
     * Globals and terms are walked only for an unscoped run, since neither is addressed by a
     * collection and a site-scoped run reads them through the same site anyway.
     *
     * @param  string|null  $site  Site handle to scope to, or null for every site.
     * @param  string|null  $collection  Collection handle to scope to, or null for every one.
     * @return list<LinkOccurrence>
     */
    public function extractScoped(?string $site = null, ?string $collection = null, ?string $entryId = null): array
    {
        if ($site === null && $collection === null && $entryId === null) {
            return $this->extract();
        }

        $this->occurrences = [];
        $this->entriesLoaded = 0;

        $this->walkEntries($site, $collection, $entryId);

        return $this->occurrences;
    }

    /**
     * How many entries this extractor actually loaded.
     *
     * Exposed so a test can assert that `--site` and `--collection` narrowed the WORK rather than
     * the output. Counting rows reported cannot tell those apart: both produce the same list.
     */
    public function entriesLoaded(): int
    {
        return $this->entriesLoaded;
    }

    /**
     * Walk every entry in every collection.
     */
    private function walkEntries(?string $site = null, ?string $collection = null, ?string $entryId = null): void
    {
        if ($site === null && $collection === null && $entryId === null) {
            /** @var Collection<int, EntryItem> $all */
            $all = Entry::all();
        } else {
            /** @var EntryQueryBuilder $query */
            $query = Entry::query();

            if ($collection !== null) {
                /** @var EntryQueryBuilder $query */
                $query = $query->where('collection', $collection);
            }

            if ($site !== null) {
                /** @var EntryQueryBuilder $query */
                $query = $query->where('site', $site);
            }

            if ($entryId !== null) {
                /** @var EntryQueryBuilder $query */
                $query = $query->where('id', $entryId);
            }

            /** @var Collection<int, EntryItem> $all */
            $all = $query->get();
        }

        foreach ($all as $entry) {
            $this->entriesLoaded++;

            $id = $entry->id();
            $blueprint = $entry->blueprint();
            if (! \is_string($id)) {
                continue;
            }

            // Class 1 in this file's docblock: a narrowing guard larastan requires at level 10. The
            // facade cannot hand back the wrong type, so no fixture reaches the false branch.
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $blueprint instanceof Blueprint) {
                continue;
            }

            $collection = $entry->collectionHandle();
            $locale = $entry->locale();

            $this->walkFieldMap(
                $this->dataArray($entry),
                BlueprintFieldMap::fromFields($blueprint->fields()),
                new OccurrenceContext(
                    ContentItemType::Entry,
                    // Class 2 in this file's docblock: the fallback arm is unreachable -- this value is always
                    // a string -- so no input can observe what the `''` is replaced with.
                    // @pest-mutate-ignore: EmptyStringToNotEmpty
                    \is_string($collection) ? $collection : '',
                    $id,
                    // Class 2 in this file's docblock: the fallback arm is unreachable -- this value is always
                    // a string -- so no input can observe what the `''` is replaced with.
                    // @pest-mutate-ignore: EmptyStringToNotEmpty
                    \is_string($locale) ? $locale : '',
                ),
                [],
            );
        }
    }

    /**
     * Walk every localization of every global set.
     */
    private function walkGlobals(): void
    {
        foreach (GlobalSet::all() as $set) {
            // Class 1 in this file's docblock: a narrowing guard larastan requires at level 10. The
            // facade cannot hand back the wrong type, so no fixture reaches the false branch.
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $set instanceof GlobalSetItem) {
                continue;
            }

            $blueprint = $set->blueprint();
            $handle = $set->handle();
            // Class 1 in this file's docblock: a narrowing guard larastan requires at level 10. The
            // facade cannot hand back the wrong type, so no fixture reaches the false branch.
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $blueprint instanceof Blueprint) {
                continue;
            }

            if (! \is_string($handle)) {
                continue;
            }

            $fields = BlueprintFieldMap::fromFields($blueprint->fields());
            $localizations = $set->localizations();

            if (! is_iterable($localizations)) {
                continue;
            }

            foreach ($localizations as $locale => $localization) {
                // Class 1 in this file's docblock: a narrowing guard larastan requires at level 10. The
                // facade cannot hand back the wrong type, so no fixture reaches the false branch.
                // @pest-mutate-ignore: InstanceOfToTrue
                if (! $localization instanceof Variables) {
                    continue;
                }

                $this->walkFieldMap(
                    $this->dataArray($localization),
                    $fields,
                    new OccurrenceContext(
                        ContentItemType::Global,
                        $handle,
                        $handle,
                        // Class 2 in this file's docblock: the fallback arm is unreachable -- this value is always
                        // a string -- so no input can observe what the `''` is replaced with.
                        // @pest-mutate-ignore: EmptyStringToNotEmpty
                        \is_string($locale) ? $locale : '',
                    ),
                    [],
                );
            }
        }
    }

    /**
     * Walk every term in every taxonomy.
     */
    private function walkTerms(): void
    {
        /** @var Collection<int, mixed> $all */
        $all = Term::all();

        foreach ($all as $term) {
            // Class 1 in this file's docblock: a narrowing guard larastan requires at level 10. The
            // facade cannot hand back the wrong type, so no fixture reaches the false branch.
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $term instanceof LocalizedTerm) {
                continue;
            }

            $blueprint = $term->blueprint();
            $id = $term->id();
            // Class 1 in this file's docblock: a narrowing guard larastan requires at level 10. The
            // facade cannot hand back the wrong type, so no fixture reaches the false branch.
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $blueprint instanceof Blueprint) {
                continue;
            }

            if (! \is_string($id)) {
                continue;
            }

            $taxonomy = $term->taxonomyHandle();
            $locale = $term->locale();

            $this->walkFieldMap(
                $this->dataArray($term),
                BlueprintFieldMap::fromFields($blueprint->fields()),
                new OccurrenceContext(
                    ContentItemType::Term,
                    // Class 2 in this file's docblock: the fallback arm is unreachable -- this value is always
                    // a string -- so no input can observe what the `''` is replaced with.
                    // @pest-mutate-ignore: EmptyStringToNotEmpty
                    \is_string($taxonomy) ? $taxonomy : '',
                    $id,
                    // Class 2 in this file's docblock: the fallback arm is unreachable -- this value is always
                    // a string -- so no input can observe what the `''` is replaced with.
                    // @pest-mutate-ignore: EmptyStringToNotEmpty
                    \is_string($locale) ? $locale : '',
                ),
                [],
            );
        }
    }

    /**
     * Walk one level of stored data against the field configs that describe it.
     *
     * @param  array<array-key, mixed>  $data  Stored values, keyed by field handle.
     * @param  array<string, array<string, mixed>>  $fields  Field handle => field config.
     * @param  OccurrenceContext  $context  Which item is being walked.
     * @param  list<string|int>  $path  Path segments already descended, outermost first.
     */
    private function walkFieldMap(array $data, array $fields, OccurrenceContext $context, array $path): void
    {
        foreach ($fields as $handle => $config) {
            if (! \array_key_exists($handle, $data)) {
                continue;
            }

            $this->walkField($handle, $config, $data[$handle], $context, $path);
        }
    }

    /**
     * Dispatch one field's stored value on its fieldtype.
     *
     * @param  string  $handle  The field handle.
     * @param  array<string, mixed>  $config  That field's config.
     * @param  mixed  $value  Its stored value.
     * @param  OccurrenceContext  $context  Which item is being walked.
     * @param  list<string|int>  $path  Path segments already descended.
     */
    private function walkField(string $handle, array $config, mixed $value, OccurrenceContext $context, array $path): void
    {
        $type = \is_string($config['type'] ?? null) ? $config['type'] : 'text';
        $here = [...$path, $handle];

        // Class 3 in this file's docblock: the strict flag on a string against `list<string>`.
        // @pest-mutate-ignore: TrueToFalse
        if (\in_array($type, self::EXCLUDED_TYPES, true)) {
            return;
        }

        match (true) {
            $type === 'replicator' => $this->walkSets($value, BlueprintFieldMap::sets($config['sets'] ?? []), $context, $here),
            $type === 'bard' => $this->walkBard($value, BlueprintFieldMap::sets($config['sets'] ?? []), $context, $here),
            $type === 'grid' => $this->walkGrid($value, BlueprintFieldMap::normalize($config['fields'] ?? []), $context, $here),
            $type === 'group' => $this->walkGroup($value, BlueprintFieldMap::normalize($config['fields'] ?? []), $context, $here),
            isset(self::RELATION_TYPES[$type]) => $this->collectRelation($value, self::RELATION_TYPES[$type], $context, $here),
            // Class 3 in this file's docblock: the strict flag on a string against `list<string>`.
            // @pest-mutate-ignore: TrueToFalse
            \in_array($type, self::LINK_VALUE_TYPES, true) => $this->collectValue($value, $context, $here),
            // Class 3 in this file's docblock: the strict flag on a string against `list<string>`.
            // @pest-mutate-ignore: TrueToFalse
            \in_array($type, self::MARKUP_TYPES, true) => $this->collectMarkup($value, $context, $here),
            $type === 'text' => $this->collectValue($value, $context, $here),
            default => null,
        };
    }

    /**
     * Walk a replicator's stored sets, descending into each set's own fields.
     *
     * @param  mixed  $value  The stored replicator value: a list of set arrays.
     * @param  array<string, array<string, array<string, mixed>>>  $sets  Set handle => field map.
     * @param  OccurrenceContext  $context  Which item is being walked.
     * @param  list<string|int>  $path  Path segments already descended.
     */
    private function walkSets(mixed $value, array $sets, OccurrenceContext $context, array $path): void
    {
        if (! \is_array($value)) {
            return;
        }

        foreach ($value as $index => $set) {
            if (! \is_array($set)) {
                continue;
            }

            $setHandle = \is_string($set['type'] ?? null) ? $set['type'] : null;
            if ($setHandle === null) {
                continue;
            }

            if (! isset($sets[$setHandle])) {
                continue;
            }

            $this->walkFieldMap($set, $sets[$setHandle], $context, [...$path, $index, $setHandle]);
        }
    }

    /**
     * Walk a Bard value: prosemirror nodes carrying link marks, plus any embedded sets.
     *
     * @param  mixed  $value  The stored Bard value: a list of prosemirror nodes, or HTML when the
     *                        field is configured to save as HTML.
     * @param  array<string, array<string, array<string, mixed>>>  $sets  Set handle => field map.
     * @param  OccurrenceContext  $context  Which item is being walked.
     * @param  list<string|int>  $path  Path segments already descended.
     */
    private function walkBard(mixed $value, array $sets, OccurrenceContext $context, array $path): void
    {
        if (\is_string($value)) {
            $this->collectMarkup($value, $context, $path);

            return;
        }

        if (! \is_array($value)) {
            return;
        }

        foreach ($value as $index => $node) {
            if (! \is_array($node)) {
                continue;
            }

            // An embedded set is a node whose values are ordinary field data.
            if (($node['type'] ?? null) === 'set') {
                $attrs = $node['attrs'] ?? null;
                $values = \is_array($attrs) ? ($attrs['values'] ?? null) : null;
                // Equivalent, and the reason is UPSTREAM rather than here. With `||` a non-string
                // `type` is assigned instead of null, and the `isset($sets[...])` on the next line
                // then decides. That lookup can never hit: `BlueprintFieldMap::sets()` drops any set
                // whose handle is not a string, so the map provably holds string keys only. Two
                // earlier readings of this line were wrong -- one claiming Statamic cannot produce a
                // numeric handle, one claiming a fixture could kill it. A blueprint CAN name a set
                // `5` and PHP does canonicalize the key to an int; the guard one layer up is what
                // makes it unreachable, and the accompanying test passes with `&&` and with `||`.
                // @pest-mutate-ignore: BooleanAndToBooleanOr
                $setHandle = \is_array($values) && \is_string($values['type'] ?? null) ? $values['type'] : null;

                if ($setHandle !== null && isset($sets[$setHandle])) {
                    $this->walkFieldMap($values, $sets[$setHandle], $context, [...$path, $index, $setHandle]);
                }

                continue;
            }

            $this->collectBardMarks($node, $context, [...$path, $index]);
        }
    }

    /**
     * Collect `href` attributes from a prosemirror node's link marks, recursing into its content.
     *
     * @param  array<array-key, mixed>  $node  One prosemirror node.
     * @param  OccurrenceContext  $context  Which item is being walked.
     * @param  list<string|int>  $path  Path segments already descended.
     */
    private function collectBardMarks(array $node, OccurrenceContext $context, array $path): void
    {
        $marks = $node['marks'] ?? null;

        if (\is_array($marks)) {
            foreach ($marks as $mark) {
                if (! \is_array($mark)) {
                    continue;
                }

                if (($mark['type'] ?? null) !== 'link') {
                    continue;
                }

                $attrs = $mark['attrs'] ?? null;
                $href = \is_array($attrs) ? ($attrs['href'] ?? null) : null;

                if (\is_string($href)) {
                    $this->collectValue($href, $context, $path);
                }
            }
        }

        $content = $node['content'] ?? null;

        if (! \is_array($content)) {
            return;
        }

        foreach ($content as $index => $child) {
            if (\is_array($child)) {
                $this->collectBardMarks($child, $context, [...$path, $index]);
            }
        }
    }

    /**
     * Walk a grid's rows, descending into each row's fields.
     *
     * @param  mixed  $value  The stored grid value: a list of row arrays.
     * @param  array<string, array<string, mixed>>  $fields  The grid's field map.
     * @param  OccurrenceContext  $context  Which item is being walked.
     * @param  list<string|int>  $path  Path segments already descended.
     */
    private function walkGrid(mixed $value, array $fields, OccurrenceContext $context, array $path): void
    {
        if (! \is_array($value)) {
            return;
        }

        foreach ($value as $index => $row) {
            if (\is_array($row)) {
                $this->walkFieldMap($row, $fields, $context, [...$path, $index]);
            }
        }
    }

    /**
     * Walk a group's nested fields, which share the parent's stored array.
     *
     * @param  mixed  $value  The stored group value: a keyed array of its fields.
     * @param  array<string, array<string, mixed>>  $fields  The group's field map.
     * @param  OccurrenceContext  $context  Which item is being walked.
     * @param  list<string|int>  $path  Path segments already descended.
     */
    private function walkGroup(mixed $value, array $fields, OccurrenceContext $context, array $path): void
    {
        if (\is_array($value)) {
            $this->walkFieldMap($value, $fields, $context, $path);
        }
    }

    /**
     * Collect a relation field's stored references, which may be a single value or a list.
     *
     * @param  mixed  $value  The stored value: one reference or a list of them.
     * @param  bool  $bareIdIsRelation  True for `entries`, where a bare id means a relation.
     * @param  OccurrenceContext  $context  Which item is being walked.
     * @param  list<string|int>  $path  Path segments already descended.
     */
    private function collectRelation(mixed $value, bool $bareIdIsRelation, OccurrenceContext $context, array $path): void
    {
        foreach ($this->scalars($value) as [$index, $item]) {
            $this->record($item, $context, $index === null ? $path : [...$path, $index], $bareIdIsRelation);
        }
    }

    /**
     * Collect a value that is, in whole, a link.
     *
     * @param  mixed  $value  The stored value.
     * @param  OccurrenceContext  $context  Which item is being walked.
     * @param  list<string|int>  $path  Path segments already descended.
     */
    private function collectValue(mixed $value, OccurrenceContext $context, array $path): void
    {
        foreach ($this->scalars($value) as [$index, $item]) {
            $this->record($item, $context, $index === null ? $path : [...$path, $index], false);
        }
    }

    /**
     * Scrape `href` and `src` attributes out of markup.
     *
     * Scoped to fieldtypes that actually render markup — a bare-URL regex over every string is
     * exactly the false-positive source this extractor exists to avoid.
     *
     * @param  mixed  $value  The stored markup.
     * @param  OccurrenceContext  $context  Which item is being walked.
     * @param  list<string|int>  $path  Path segments already descended.
     */
    private function collectMarkup(mixed $value, OccurrenceContext $context, array $path): void
    {
        // Class 2: this arm is an optimization, not a behavior. An empty string carries no `href`
        // and no markdown target, so skipping the scan and running it produce the same output.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if (! \is_string($value) || $value === '') {
            return;
        }

        // `preg_match_all()` returns an int for this pattern and can only return false on a malformed
        // one, so neither this test nor the mutant's inverted form can ever be true.
        // @pest-mutate-ignore: FalseToTrue
        if (preg_match_all('/(?:href|src)\s*=\s*"([^"]*)"|(?:href|src)\s*=\s*\'([^\']*)\'/i', $value, $matches, PREG_SET_ORDER) === false) {
            return;
        }

        foreach ($matches as $match) {
            // Group 2 is ABSENT whenever the double-quoted alternative matched, so this default is
            // genuinely reached -- measured: `href="D"` yields `[full, 'D']` and `href=''` yields
            // `[full, '']`, neither carrying an index 2. Group 1 is present in every match either
            // alternative can produce, so it needs no default and having one was dead code.
            // Split onto its own line so the reachable `!== ''` below stays independently killable.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            $singleQuoted = $match[2] ?? '';
            // Class 2 again, and this one was learned the hard way: measurement says group 1 is
            // present in every match either alternative can produce, so the default is dead --
            // but larastan types the set as `array{0: string, 1?: string, 2?: string}` and reds
            // `offsetAccess.notFound` without it. Unreachable at runtime, required by the gate.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            $doubleQuoted = $match[1] ?? '';
            $href = $doubleQuoted !== '' ? $doubleQuoted : $singleQuoted;

            // Class 2: an empty `href` that reaches `record()` is dropped by the classifier, which
            // returns null for the empty string -- measured, along with 'a', 'x' and '0'. This guard
            // saves a call rather than changing the result.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if ($href !== '') {
                $this->record($href, $context, $path, false);
            }
        }

        // Markdown link and image targets, which carry no href attribute.
        if (
            // `preg_match_all()` returns an int for this pattern and can only return false on a
            // malformed one, so neither this test nor the mutant's inverted form can ever be false.
            // @pest-mutate-ignore: FalseToTrue
            preg_match_all('/!?\[[^\]]*\]\(([^)\s]+)/', $value, $mdMatches) !== false
        ) {
            foreach ($mdMatches[1] as $target) {
                $this->record($target, $context, $path, false);
            }
        }
    }

    /**
     * Normalize a stored value to the scalar strings it contains, each with its list index.
     *
     * Returned as a list of pairs rather than index => string, because PHP casts a null array key
     * to the empty string: keying a scalar under null and testing `$index === null` at the call
     * site silently appends an empty path segment to every occurrence.
     *
     * @param  mixed  $value  One string, or a list of them.
     * @return list<array{0: int|null, 1: string}> Index (null when the value was scalar) and string.
     */
    private function scalars(mixed $value): array
    {
        if (\is_string($value)) {
            return [[null, $value]];
        }

        if (! \is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $index => $item) {
            if (\is_string($item) && \is_int($index)) {
                $out[] = [$index, $item];
            }
        }

        return $out;
    }

    /**
     * Classify one value and record it when it is a link.
     *
     * @param  string  $value  The stored value.
     * @param  OccurrenceContext  $context  Which item is being walked.
     * @param  list<string|int>  $path  The full path to the value, including its field handle.
     * @param  bool  $fromRelationField  Whether a bare id here means an entry relation.
     */
    private function record(string $value, OccurrenceContext $context, array $path, bool $fromRelationField): void
    {
        $kind = $this->classifier->classify($value, $fromRelationField);

        if (! $kind instanceof LinkKind) {
            return;
        }

        // Class 2: `record()` is never called with an empty path -- every caller descends through
        // `walkField`, which prepends the field handle -- so the `??` arm is unreachable.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $field = $path[0] ?? '';

        $this->occurrences[] = new LinkOccurrence(
            $context->itemType,
            $context->container,
            $context->id,
            $context->site,
            // Class 2: `$path` is typed `list<string|int>` so larastan requires the cast, but the first
            // segment is always a blueprint field handle, which is a string. The cast converts nothing.
            // @pest-mutate-ignore: RemoveStringCast
            (string) $field,
            \array_slice($path, 1),
            $value,
            $kind,
        );
    }

    /**
     * Read an item's stored data as an array (the `data()` getter is untyped).
     *
     * @param  EntryItem|Variables|LocalizedTerm  $item  The content item.
     * @return array<string, mixed> Its stored values, keyed by field handle.
     */
    private function dataArray(EntryItem|Variables|LocalizedTerm $item): array
    {
        /** @var Collection<string, mixed> $data */
        $data = $item->data();

        return $data->all();
    }
}
