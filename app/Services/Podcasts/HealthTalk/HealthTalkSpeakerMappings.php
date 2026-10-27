<?php

declare(strict_types=1);

namespace App\Services\Podcasts\HealthTalk;

use App\Support\Collections\PeopleCollection;
use Illuminate\Support\Str;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Resolves UAMS Health Talk speakers to `people` entries (UAMS-Web/uams-statamic-archive#1803).
 *
 * Resolution never guesses by name. A speaker resolves in this order:
 *
 * 1. a mapping row whose speaker string matches exactly, either to a `people`
 *    entry or to "no entry expected";
 * 2. the `uamshealth.com/provider/<slug>/` slug the item page links for that
 *    speaker, when a `people` entry has that slug;
 *
 * and is otherwise unresolved. The rows live in a global set's replicator so
 * editors maintain them without a deploy, and the sync appends a row for each
 * unresolved speaker so the backlog maintains itself.
 */
final class HealthTalkSpeakerMappings
{
    /**
     * A row mapped to a `people` entry.
     */
    public const string PERSON = 'person';

    /**
     * A row deliberately resolving to nobody: the speaker imports uncredited.
     */
    public const string NO_ENTRY = 'none';

    /**
     * A row the sync added and nobody has mapped yet.
     */
    public const string UNMAPPED = 'unmapped';

    /**
     * The replicator field holding the rows.
     */
    public const string FIELD = 'mappings';

    /**
     * The replicator set every row uses.
     */
    public const string SET = 'speaker_mapping';

    /**
     * Rows keyed by exact speaker string, loaded once per instance.
     *
     * @var array<string, array{disposition: string, person: string|null}>|null
     */
    private ?array $rows = null;

    /**
     * People entry ids keyed by slug, filled as they are looked up.
     *
     * @var array<string, string|null>
     */
    private array $peopleBySlug = [];

    /**
     * @param  string  $globalHandle  The global set holding the rows
     * @param  string  $site  The site whose values hold them
     */
    public function __construct(
        private readonly string $globalHandle,
        private readonly string $site
    ) {}

    /**
     * Resolve one speaker.
     *
     * @return array{status: string, person: string|null} `person` with an entry id, `none`, or `unresolved`
     */
    public function resolve(
        HealthTalkSpeaker $speaker
    ): array {
        $row = $this->rows()[$speaker->name] ?? null;

        if ($row !== null && $row['disposition'] === self::NO_ENTRY) {
            return ['status' => self::NO_ENTRY, 'person' => null];
        }

        if ($row !== null && $row['disposition'] === self::PERSON && $row['person'] !== null && $this->personExists($row['person'])) {
            return ['status' => self::PERSON, 'person' => $row['person']];
        }

        if ($speaker->providerSlug !== null) {
            $person = $this->personBySlug($speaker->providerSlug);

            if ($person !== null) {
                return ['status' => self::PERSON, 'person' => $person];
            }
        }

        return ['status' => 'unresolved', 'person' => null];
    }

    /**
     * Append an unmapped row for each speaker string that has no row yet.
     *
     * @param  list<string>  $names  Unresolved speaker strings
     * @return int How many rows were added
     */
    public function recordUnmapped(
        array $names
    ): int {
        $variables = $this->variables();

        if (! $variables instanceof Variables) {
            return 0;
        }

        $stored = $variables->get(self::FIELD);
        $rows = \is_array($stored) ? \array_values($stored) : [];
        $known = \array_map(strval(...), \array_keys($this->rows()));
        $added = 0;

        foreach (\array_values(\array_unique($names)) as $name) {
            if (\in_array($name, $known, true)) {
                continue;
            }

            $rows[] = [
                'id' => Str::random(8),
                'type' => self::SET,
                'enabled' => true,
                'speaker' => $name,
                'disposition' => self::UNMAPPED,
                'person' => null,
            ];
            $known[] = $name;
            $added++;
        }

        if ($added > 0) {
            $variables->set(self::FIELD, $rows);
            $variables->save();
            $this->rows = null;
        }

        return $added;
    }

    /**
     * Whether the global set holding the rows exists for the configured site.
     */
    public function exists(): bool
    {
        return $this->variables() instanceof Variables;
    }

    /**
     * Speaker strings whose row is still unmapped.
     *
     * @return list<string>
     */
    public function unmappedSpeakers(): array
    {
        $names = [];

        foreach ($this->rows() as $name => $row) {
            if ($row['disposition'] === self::UNMAPPED) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * The control-panel URL where the rows are edited, or null when the set is missing.
     */
    public function editUrl(): ?string
    {
        $variables = $this->variables();

        $url = $variables instanceof Variables ? $variables->editUrl() : null;

        return \is_string($url) ? $url : null;
    }

    /**
     * The rows, keyed by exact speaker string.
     *
     * @return array<string, array{disposition: string, person: string|null}>
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $stored = $this->variables()?->get(self::FIELD);
        $rows = [];

        foreach (\is_array($stored) ? $stored : [] as $row) {
            if (! \is_array($row)) {
                continue;
            }

            if (! \is_string($row['speaker'] ?? null)) {
                continue;
            }

            if (\trim($row['speaker']) === '') {
                continue;
            }

            $disposition = $row['disposition'] ?? null;
            $person = $row['person'] ?? null;

            if (\is_array($person)) {
                $person = $person[0] ?? null;
            }

            $rows[\trim($row['speaker'])] = [
                'disposition' => \in_array($disposition, [self::PERSON, self::NO_ENTRY], true) ? $disposition : self::UNMAPPED,
                'person' => \is_string($person) && $person !== '' ? $person : null,
            ];
        }

        return $this->rows = $rows;
    }

    /**
     * The global set's values for the configured site, or null when the set does not exist.
     */
    private function variables(): ?Variables
    {
        $set = GlobalSet::findByHandle($this->globalHandle);

        if (! $set instanceof GlobalSetContract) {
            return null;
        }

        $variables = $set->in($this->site);

        return $variables instanceof Variables ? $variables : null;
    }

    /**
     * Whether a mapped `people` entry still exists.
     */
    private function personExists(
        string $id
    ): bool {
        $entry = EntryFacade::find($id);

        return $entry instanceof Entry && $entry->collectionHandle() === PeopleCollection::handle();
    }

    /**
     * The `people` entry id with this slug, or null.
     */
    private function personBySlug(
        string $slug
    ): ?string {
        if (\array_key_exists($slug, $this->peopleBySlug)) {
            return $this->peopleBySlug[$slug];
        }

        $query = EntryFacade::query();

        if (! $query instanceof EntryQueryBuilder) {
            return $this->peopleBySlug[$slug] = null;
        }

        $query->where('collection', PeopleCollection::handle());
        $query->where('slug', $slug);

        $entry = $query->first();

        $id = $entry instanceof Entry ? $entry->id() : null;

        return $this->peopleBySlug[$slug] = \is_string($id) ? $id : null;
    }
}
