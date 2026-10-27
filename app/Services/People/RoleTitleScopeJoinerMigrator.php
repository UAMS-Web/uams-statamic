<?php

declare(strict_types=1);

namespace App\Services\People;

use App\Support\RoleTitleScopeUnit;
use App\Support\RoleTitleValue;
use Illuminate\Support\Collection as SupportCollection;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Stache\Repositories\EntryRepository as StacheEntryRepository;

/**
 * Rewrites stored `role_title` scope joiners from the five combined values to a
 * preposition plus a separate article (UAMS-Web/uams-statamic-archive#2546), without changing a composed title.
 *
 * Before UAMS-Web/uams-statamic-archive#2546 a joiner stored the preposition and the article as one value. It
 * now stores the preposition alone: a linked unit always takes `the`, and a typed
 * scope carries its own `scope_article`. The mapping moves the article into that
 * field for a typed scope, so `for the Northwest Arkansas region` still reads
 * exactly that.
 *
 * One thing can still move a title, and it is REPORTED rather than rewritten.
 * Before, an article-bearing joiner suppressed the object; now only a linked unit
 * does. So a typed scope that had the article AND an object selected would gain
 * its object, and a linked scope without the article would gain `the`. Neither
 * occurs in the content this was written against; the report proves it for
 * whatever content it runs on.
 *
 * Linking typed unit names is a separate, later step (`role-title:link-scope-units`),
 * which reads the `scope_article` this writes.
 */
final readonly class RoleTitleScopeJoinerMigrator
{
    /**
     * Legacy joiner storage key to its preposition and whether it carried `the`.
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    public const array LEGACY_JOINERS = [
        'for_without_the' => ['for', false],
        'for_with_the' => ['for', true],
        'of_without_the' => ['of', false],
        'of_with_the' => ['of', true],
        'in_the' => ['in', true],
    ];

    /**
     * A legacy joiner rewritten with the composed title unchanged.
     */
    public const string COLLAPSED = 'collapsed';

    /**
     * A typed scope that carried the article beside a selected object: the object
     * was suppressed before and now composes.
     */
    public const string OBJECT_WOULD_APPEAR = 'object_would_appear';

    /**
     * A linked scope whose joiner carried no article: the title now gains `the`.
     */
    public const string GAINS_ARTICLE = 'gains_article';

    /**
     * A linked scope beside a selected object. The row can no longer be saved from
     * the Control Panel until one of the two is cleared. Its title is unchanged when
     * the legacy joiner carried the article (the object was suppressed then too),
     * and loses the object and gains `the` when it did not.
     */
    public const string LINKED_WITH_OBJECT = 'linked_with_object';

    /**
     * Create the migrator.
     *
     * @param  StacheEntryRepository  $entries  Statamic Stache entry repository
     */
    public function __construct(
        private StacheEntryRepository $entries
    ) {}

    /**
     * Collapse every legacy joiner in a collection, reporting each one.
     *
     * @param  string  $collection  Collection handle
     * @param  bool  $write  Whether to save the rewritten entries
     * @return list<array{entry: string, path: string, status: string, from: string, to: string, article: string|null}>
     */
    public function scan(
        string $collection,
        bool $write
    ): array {
        if (! Collection::find($collection) instanceof CollectionContract) {
            return [];
        }

        $findings = [];

        foreach ($this->entries->whereCollection($collection) as $entry) {
            if (! $entry instanceof Entry) {
                continue;
            }

            $id = $entry->id();
            $data = $entry->data();
            $result = $this->collapseInData(
                $data instanceof SupportCollection ? $data->all() : [],
                \is_string($id) ? $id : ''
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
     * Collapse every legacy joiner in one entry's data.
     *
     * @param  array<array-key, mixed>  $data  The entry's stored data
     * @param  string  $entryId  Entry id recorded on each finding
     * @return array{data: array<array-key, mixed>, changed: bool, findings: list<array{entry: string, path: string, status: string, from: string, to: string, article: string|null}>}
     */
    public function collapseInData(
        array $data,
        string $entryId
    ): array {
        $changed = false;
        $findings = [];

        $this->walk($data, '', $entryId, $changed, $findings);

        return ['data' => $data, 'changed' => $changed, 'findings' => $findings];
    }

    /**
     * Walk a data tree, collapsing each role_title payload's legacy joiner.
     *
     * @param  array<array-key, mixed>  $node  Data node, updated in place
     * @param  list<array{entry: string, path: string, status: string, from: string, to: string, article: string|null}>  $findings  Findings, appended to
     */
    private function walk(
        array &$node,
        string $path,
        string $entryId,
        bool &$changed,
        array &$findings
    ): void {
        foreach ($node as $key => &$child) {
            if (! \is_array($child)) {
                continue;
            }

            $childPath = $path === '' ? (string) $key : $path.'.'.$key;

            // A role_title payload: collapse its scope joiner
            if (\is_array($child[RoleTitleScopeUnit::GROUP_KEY] ?? null) && \array_key_exists('configuration', $child)) {
                $finding = $this->collapse($child);

                if ($finding !== null) {
                    $findings[] = ['entry' => $entryId, 'path' => $childPath] + $finding;
                    $changed = true;
                }

                continue;
            }

            $this->walk($child, $childPath, $entryId, $changed, $findings);
        }

        unset($child);
    }

    /**
     * Collapse one payload's joiner in place, or return null when it is not legacy.
     *
     * @param  array<array-key, mixed>  $title  role_title payload, updated in place
     * @return array{status: string, from: string, to: string, article: string|null}|null
     */
    private function collapse(
        array &$title
    ): ?array {
        /** @var array<string, mixed> $scope */
        $scope = $title[RoleTitleScopeUnit::GROUP_KEY];
        $from = $scope['scope_joiner'] ?? null;

        if (! \is_string($from) || ! \array_key_exists(\trim($from), self::LEGACY_JOINERS)) {
            return null;
        }

        [$preposition, $hadArticle] = self::LEGACY_JOINERS[\trim($from)];
        $linked = RoleTitleScopeUnit::idFromScope($scope) !== null;

        $typed = \is_string($scope['scope_name'] ?? null) ? \trim($scope['scope_name']) : '';

        // A linked unit takes the article itself, so only a typed name stores it
        $article = ! $linked && $hadArticle && $typed !== '' ? 'the' : null;

        $scope['scope_joiner'] = $preposition;
        $scope['scope_article'] = $article;
        $title[RoleTitleScopeUnit::GROUP_KEY] = $scope;

        return [
            'status' => $this->status($title, $linked, $hadArticle, $typed),
            'from' => \trim($from),
            'to' => $preposition,
            'article' => $article,
        ];
    }

    /**
     * Classify whether collapsing this payload can change its composed title.
     *
     * A scope that composes no phrase -- a blank typed name, or a link that does not
     * resolve to a named unit -- changes nothing, whatever its object, and is
     * `collapsed`. A linked scope beside a composing object is reported first,
     * since that row also fails validation until one of the two is cleared.
     *
     * @param  array<array-key, mixed>  $title  role_title payload
     * @param  bool  $linked  Whether the scope links a unit
     * @param  bool  $hadArticle  Whether the legacy joiner carried `the`
     * @param  string  $typed  The trimmed typed scope name
     * @return string One of the status constants
     */
    private function status(
        array $title,
        bool $linked,
        bool $hadArticle,
        string $typed
    ): string {
        /** @var array<string, mixed> $scope */
        $scope = $title[RoleTitleScopeUnit::GROUP_KEY];

        // A scope that composes no phrase changes no title
        $composes = $linked
            ? RoleTitleScopeUnit::displayName(RoleTitleScopeUnit::idFromScope($scope)) !== null
            : $typed !== '';

        if (! $composes) {
            return self::COLLAPSED;
        }

        $hasObject = RoleTitleValue::objectSelected($title);

        return match (true) {
            $linked && $hasObject => self::LINKED_WITH_OBJECT,
            $linked && ! $hadArticle => self::GAINS_ARTICLE,
            ! $linked && $hadArticle && $hasObject => self::OBJECT_WOULD_APPEAR,
            default => self::COLLAPSED,
        };
    }
}
