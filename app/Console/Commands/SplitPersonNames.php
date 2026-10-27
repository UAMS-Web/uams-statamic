<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Collections\PeopleCollection;
use App\Support\People\PersonNameComposite;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * One-shot move of each `people` entry's name into the fields UAMS-Web/uams-statamic-archive#3186 adds.
 *
 * Before UAMS-Web/uams-statamic-archive#3186 a person's nickname, the pronunciation of each name part and the
 * preferred display format were kept inside `person_name`, beside the legal
 * name parts Workday owns. This moves them into `nickname`,
 * `name_pronunciation` and `name_display_format` and leaves `person_name`
 * holding the legal parts only, so the joined name every consumer reads is the
 * one it read before. An entry that already holds any of the three fields is
 * left alone, so the command can be run again safely. Run with `--dry-run`
 * first to see which entries would change.
 */
#[Description("Move each people entry's nickname, pronunciation and display format out of person_name (UAMS-Web/uams-statamic-archive#3186)")]
#[Signature('content:split-person-names {--dry-run : Report the entries without saving them}')]
class SplitPersonNames extends Command
{
    /**
     * The fields the name is moved into.
     *
     * @var list<string>
     */
    private const array REFINEMENT_FIELDS = [
        PersonNameComposite::NICKNAME,
        PersonNameComposite::PRONUNCIATION,
        PersonNameComposite::DISPLAY_FORMAT,
    ];

    /**
     * Split the name of every `people` entry that still holds it whole.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $split = 0;

        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        /** @var EntryQueryBuilder $query */
        $query = $query->where('collection', PeopleCollection::handle());
        /** @var Collection<int, EntryModel> $entries */
        $entries = $query->get();

        foreach ($entries as $entry) {
            // Skip an entry with no name
            $personName = $entry->get(PersonNameComposite::PERSON_NAME);

            if (! \is_array($personName)) {
                continue;
            }

            // Skip an entry already split
            if ($this->holdsRefinementFields($entry)) {
                continue;
            }

            $parts = PersonNameComposite::split($personName);
            $split++;

            $slug = $entry->slug();
            $this->line(\sprintf('%s %s', $dryRun ? '[dry-run]' : 'Split', \is_string($slug) ? $slug : ''));

            if ($dryRun) {
                continue;
            }

            // Write the legal name back, then each refinement that holds a value
            $entry->set(PersonNameComposite::PERSON_NAME, $parts[PersonNameComposite::PERSON_NAME]);

            foreach (self::REFINEMENT_FIELDS as $handle) {
                if ($parts[$handle] !== null) {
                    $entry->set($handle, $parts[$handle]);
                }
            }

            $entry->saveQuietly();
        }

        $this->info(\sprintf(
            '%s %d %s.',
            $dryRun ? 'Would split' : 'Split',
            $split,
            $split === 1 ? 'entry' : 'entries'
        ));

        return self::SUCCESS;
    }

    /**
     * Whether an entry already holds any of the fields the name is moved into.
     *
     * @param  EntryModel  $entry  A `people` entry
     */
    private function holdsRefinementFields(EntryModel $entry): bool
    {
        return \array_any(
            self::REFINEMENT_FIELDS,
            fn (string $handle): bool => $entry->has($handle) === true
        );
    }
}
