<?php

declare(strict_types=1);

namespace App\Services\Organizations;

use App\Support\ContentAncestorIds;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;

/**
 * Resolves an `organizations` entry's effective public label and its generated
 * title (UAMS-Web/uams-statamic-archive#2125).
 *
 * Workday's supervisory names are internal — `ICE | CORE PA Clinic Access B` —
 * so an entry keeps its Workday identity in its own fields and carries a
 * separate, editor-authored public label. An entry either supplies its own
 * label or inherits one; inheritance affects **display only** and never
 * identity, so relationships and schema still resolve to the entry itself.
 */
final readonly class OrganizationPublicLabelService
{
    /**
     * The effective public label: this entry's own when it does not inherit,
     * otherwise the nearest ancestor that does not inherit either.
     *
     * Resolution keys on the ancestor's toggle rather than on it merely holding
     * a non-empty label. An ancestor whose own toggle says it inherits is not a
     * source — treating it as one would contradict its own setting.
     */
    public function resolvePublicLabel(Entry $entry): ?string
    {
        // An entry that does not inherit is its own source, empty label or not.
        if (! $this->inherits($entry)) {
            return $this->ownLabel($entry);
        }

        $entryId = $entry->id();

        // Without a usable id there is no ancestor walk to make.
        if (! is_string($entryId) || $entryId === '') {
            return null;
        }

        // Nearest structural ancestor first; the first non-inheriting one wins.
        foreach (ContentAncestorIds::forEntry($entryId) as $ancestorId) {
            $ancestor = EntryFacade::find($ancestorId);

            if (! $ancestor instanceof Entry) {
                continue;
            }

            if (! $this->inherits($ancestor)) {
                return $this->ownLabel($ancestor);
            }
        }

        return null;
    }

    /**
     * The generated title: `Workday name (ID) — public label`.
     *
     * The trailing segment is dropped when the label is absent or identical to
     * the Workday name, which is the common case after a bulk import seeds one
     * from the other. An entry with no Workday name falls back to its public
     * label and then to its authored title, which is what the eleven ID-less
     * UAMS nodes and every external organization rely on.
     */
    public function composeTitle(Entry $entry): string
    {
        $authored = $this->stringValue($entry, 'title') ?? '';
        $workdayName = $this->stringValue($entry, 'workday_supervisory_organization_name');
        $label = $this->resolvePublicLabel($entry);

        // No Workday name: the public label, else whatever was authored.
        if ($workdayName === null) {
            return $label ?? $authored;
        }

        $workdayId = $this->stringValue($entry, 'workday_supervisory_organization_id');
        $head = $workdayId === null ? $workdayName : "{$workdayName} ({$workdayId})";

        // A label that merely repeats the Workday name adds nothing.
        if ($label === null || $label === $workdayName) {
            return $head;
        }

        return "{$head} — {$label}";
    }

    /**
     * The name an entry may be called by in public prose, such as a job title's
     * scope (`chair of the Department of Pediatrics`), or null when it has none.
     *
     * The effective public label when there is one. Without a label, an entry
     * that carries no Workday name falls back to its authored title, exactly as
     * `composeTitle()` does. An entry that does carry a Workday name has no
     * public fallback at all, because that name is internal and its generated
     * title embeds it -- so it reports null rather than leak it into a title.
     */
    public function displayName(Entry $entry): ?string
    {
        // A resolved public label is always the public name.
        $label = $this->resolvePublicLabel($entry);

        if ($label !== null) {
            return $label;
        }

        // A Workday-named entry without a label has nothing safe to show.
        if ($this->stringValue($entry, 'workday_supervisory_organization_name') !== null) {
            return null;
        }

        // No Workday name: the authored title, as `composeTitle()` resolves it.
        // composeTitle() builds from values stringValue() has already trimmed, so its result
        // has no surrounding whitespace.
        // @pest-mutate-ignore: UnwrapTrim
        $title = trim($this->composeTitle($entry));

        return $title === '' ? null : $title;
    }

    /**
     * Whether the entry defers its public label to an ancestor.
     */
    private function inherits(Entry $entry): bool
    {
        return (bool) $entry->value('inherit_public_label_query');
    }

    /**
     * The entry's own public label, or null when it is empty.
     */
    private function ownLabel(Entry $entry): ?string
    {
        return $this->stringValue($entry, 'public_label');
    }

    /**
     * A trimmed string field value, or null when absent or blank.
     */
    private function stringValue(Entry $entry, string $handle): ?string
    {
        $value = $entry->value($handle);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
