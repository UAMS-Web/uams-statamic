<?php

declare(strict_types=1);

namespace App\Widgets;

use App\Services\People\PeopleDuplicateDetector;
use App\Support\People\PeopleEditingScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Statamic\Auth\User;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\User as UserFacade;
use Statamic\Widgets\Widget;

/**
 * CP dashboard widget surfacing clusters of likely-duplicate `people` entries (UAMS-Web/uams-statamic-archive#839).
 *
 * It runs {@see PeopleDuplicateDetector} and lists each candidate cluster with its
 * confidence so a reviewer can launch the UAMS-Web/uams-statamic-archive#838 merge tool against it. The widget
 * only suggests — it never merges or mutates — and is shown only to users who hold
 * the `merge people entries` permission, since launching the merge requires it.
 *
 * A user the people editing scope covers (UAMS-Web/uams-statamic-archive#3182) sees only the clusters whose
 * every member is in their scope (UAMS-Web/uams-statamic-archive#3197), since the merge refuses any other.
 */
class DuplicatePeople extends Widget
{
    /**
     * The permission required to see and act on flagged duplicates.
     */
    private const string PERMISSION = 'merge people entries';

    /**
     * Cache key for the (user-agnostic) detected cluster rows. Versioned so rows
     * cached before each row carried its member ids are never read.
     */
    public const string CACHE_KEY = 'duplicate_people_clusters.v2';

    /**
     * Default seconds to cache the detection scan.
     */
    private const int DEFAULT_CACHE_TTL = 300;

    /**
     * Default maximum clusters to display.
     */
    private const int DEFAULT_LIMIT = 25;

    /**
     * Render the widget, or nothing when the viewer cannot merge.
     */
    #[\Override]
    public function html(): string|View
    {
        // Only users who can merge see the tool — launching a merge requires it.
        /** @var User|null $user */
        $user = UserFacade::current();

        if ($user === null || ! $user->can(self::PERMISSION)) {
            // Hidden for users without the merge permission
            return '';
        }

        // Render the cluster listing with a launch link per cluster
        return view('widgets.duplicate_people', [
            'title' => $this->config('title', __('Potential Duplicate People')),
            'clusters' => $this->clusters($user),
        ]);
    }

    /**
     * The detected clusters the viewer may merge, as display rows, up to the limit.
     *
     * @param  User  $user  The viewer
     * @return list<array{confidence: string, signals: string, members: list<array{title: string, edit_url: string}>, member_ids: list<string>, merge_url: string, count: int}>
     */
    private function clusters(User $user): array
    {
        // Resolve cache TTL from config (Widget::config()'s declared return type is
        // inaccurate — it returns the raw config value, numeric here).
        $ttlValue = $this->config('cache_ttl', self::DEFAULT_CACHE_TTL);
        $ttl = \is_scalar($ttlValue) ? (int) $ttlValue : self::DEFAULT_CACHE_TTL;

        // Resolve display limit from config
        $limitValue = $this->config('limit', self::DEFAULT_LIMIT);
        $limit = \is_scalar($limitValue) ? (int) $limitValue : self::DEFAULT_LIMIT;
        $limit = $limit < 1 ? self::DEFAULT_LIMIT : $limit;

        // Cache the user-agnostic rows so the scan does not run on every dashboard load
        /** @var list<array{confidence: string, signals: string, members: list<array{title: string, edit_url: string}>, member_ids: list<string>, merge_url: string, count: int}> $rows */
        $rows = Cache::remember(self::CACHE_KEY, $ttl, fn (): array => $this->buildRows());

        // Keep only the clusters the viewer may merge, then cap the number displayed
        return \array_slice($this->rowsInScope($rows, $user), 0, $limit);
    }

    /**
     * The rows whose every live member a scoped viewer may act on; every row for
     * a viewer the scope does not cover.
     *
     * @param  list<array{confidence: string, signals: string, members: list<array{title: string, edit_url: string}>, member_ids: list<string>, merge_url: string, count: int}>  $rows
     * @param  User  $user  The viewer
     * @return list<array{confidence: string, signals: string, members: list<array{title: string, edit_url: string}>, member_ids: list<string>, merge_url: string, count: int}>
     */
    private function rowsInScope(array $rows, User $user): array
    {
        $scope = app(PeopleEditingScope::class);

        // Administrators see every cluster
        if (! $scope->appliesTo($user)) {
            return $rows;
        }

        $editable = array_flip($scope->editableEntryIds($user));

        return array_values(array_filter(
            $rows,
            static function (array $row) use ($editable): bool {
                foreach ($row['member_ids'] as $id) {
                    if (! isset($editable[$id])) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    /**
     * Detect every cluster and hydrate it into a display row.
     *
     * @return list<array{confidence: string, signals: string, members: list<array{title: string, edit_url: string}>, member_ids: list<string>, merge_url: string, count: int}>
     */
    private function buildRows(): array
    {
        // Run the detector; the display limit is applied per viewer after scoping
        $clusters = (new PeopleDuplicateDetector)->detect();

        $rows = [];

        foreach ($clusters as $cluster) {
            // Hydrate each member to a display row, dropping ids that no longer resolve
            $members = [];
            $memberIds = [];
            foreach ($cluster->entryIds as $id) {
                $entry = EntryFacade::find($id);
                if (! $entry instanceof Entry) {
                    continue;
                }

                $members[] = [
                    'title' => $this->displayTitle($entry, $id),
                    'edit_url' => $this->stringOr($entry->editUrl(), ''),
                ];
                $memberIds[] = $id;
            }

            // A cluster still needs ≥2 live members to be worth merging
            if (\count($members) < 2) {
                continue;
            }

            // Build the launch URL for the UAMS-Web/uams-statamic-archive#838 merge reconciliation screen
            $mergeUrl = cp_route('people-merge.show', ['ids' => implode(',', $cluster->entryIds)]);

            $rows[] = [
                'confidence' => $cluster->confidence,
                'signals' => implode(', ', $cluster->matchedSignals),
                'members' => $members,
                'member_ids' => $memberIds,
                'merge_url' => \is_string($mergeUrl) ? $mergeUrl : '',
                'count' => \count($cluster->entryIds),
            ];
        }

        return $rows;
    }

    /**
     * Resolve a human-readable title for a person entry, falling back to slug then id.
     */
    private function displayTitle(Entry $entry, string $id): string
    {
        // Prefer the (possibly computed) display title
        $title = $entry->value('title');
        if (\is_string($title) && $title !== '') {
            return $title;
        }

        // Fall back to the slug, then the raw id
        $slug = $entry->slug();

        return \is_string($slug) && $slug !== '' ? $slug : $id;
    }

    /**
     * Coerce a scalar to string, using the fallback for non-scalars.
     */
    private function stringOr(mixed $value, string $fallback): string
    {
        return \is_scalar($value) ? (string) $value : $fallback;
    }
}
