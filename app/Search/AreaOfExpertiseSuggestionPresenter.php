<?php

declare(strict_types=1);

namespace App\Search;

use Statamic\Contracts\Search\Result;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use UAMSWeb\Aether\Search\Contracts\EntryVisibility;
use UAMSWeb\Aether\Search\Contracts\SuggestionPresenter;
use UAMSWeb\Aether\Search\Support\EntrySuggestionPresenter;

/**
 * Shapes an Area of Expertise section subpage as a type-ahead suggestion.
 *
 * {@see AreaOfExpertiseSectionDocument} is not an entry, so the add-on's presenter
 * would drop it. Every other hit goes to that presenter.
 */
final class AreaOfExpertiseSuggestionPresenter implements SuggestionPresenter
{
    /**
     * @param  EntrySuggestionPresenter  $entries  Shapes every hit that is not a section subpage
     * @param  EntryVisibility  $visibility  Decides whether this reader may see an entry
     */
    public function __construct(
        private readonly EntrySuggestionPresenter $entries,
        private readonly EntryVisibility $visibility,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function present(Result $result): ?array
    {
        $searchable = $result->getSearchable();

        if (! $searchable instanceof AreaOfExpertiseSectionDocument) {
            return $this->entries->present($result);
        }

        // A section subpage is as private as its Area of Expertise; the reference is
        // `clinical_expertise_section::<aoe id>::<section>`.
        $areaOfExpertise = EntryFacade::find(explode('::', $searchable->getSearchReference())[1] ?? '');

        if (! $areaOfExpertise instanceof Entry || ! $this->visibility->canView($areaOfExpertise)) {
            return null;
        }

        $data = $searchable->augmentedArrayData();

        return EntrySuggestionPresenter::suggestion($data['title'], $data['url'], 'Area of Expertise');
    }
}
