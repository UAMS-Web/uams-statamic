<?php

declare(strict_types=1);

namespace App\Support\People;

use App\Support\Collections\PeopleCollection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Fields\Value;
use Statamic\Sites\Site as SiteObject;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Resolves the `?provider=` slug the `clinical_resources` archive filters on
 * (UAMS-Web/uams-statamic-archive#1692) — one lookup shared by the query scope that narrows the listing and the
 * tag that names the provider in the on-page notice.
 *
 * They are deliberately not allowed to resolve it separately. A scope and a
 * template gate that answer "is this filtered, and to whom?" independently drift
 * into disagreement — the archive claiming to be filtered to a provider it did not
 * filter on, or filtering silently with no notice. UAMS-Web/uams-statamic-archive#1695's review caught exactly
 * that class of split between a resolver and the template gate it mirrored, so the
 * two callers here share one answer by construction.
 *
 * Only `people` is searched. `clinical_resources` is a field on that blueprint and
 * on no other — `clinical_providers` carries no such field, so accepting one of its
 * slugs would resolve a real person, name them in the notice, and then filter to an
 * empty id list: an archive that says it is showing a named doctor's resources
 * while showing nothing at all.
 */
final readonly class ClinicalResourceProviderFilter
{
    /**
     * Collection carrying the `clinical_resources` relation.
     */
    private static function providerCollection(): string
    {
        return PeopleCollection::handle();
    }

    /**
     * The publicly-renderable person carrying the given slug on the current site,
     * or null.
     *
     * The gate is the full one, not just publication: `status_and_visibility` and
     * `password_protected` are checked exactly as
     * `PeopleLensController::isPubliclyRenderable()` checks them. A private or
     * password-protected person has a profile the public cannot open, so naming
     * them in the archive notice — and listing the resources they relate — would
     * leak through a query parameter what the lens route deliberately withholds.
     *
     * The lookup is scoped to the current site and filters for an eligible match
     * INSIDE the query rather than testing `first()` afterwards: a slug can exist
     * on more than one site or as an unpublished namesake, and taking the first row
     * then rejecting it would let one ineligible entry silently disable the filter
     * for a perfectly public provider.
     */
    public function find(string $slug): ?Entry
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        /** @var SiteObject $site */
        $site = Site::current();

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::providerCollection());
        $query->where('site', $site->handle());
        $query->where('slug', $slug);

        $matches = $query->get();

        if (! is_iterable($matches)) {
            return null;
        }

        foreach ($matches as $match) {
            if ($match instanceof Entry && $this->isPubliclyRenderable($match)) {
                return $match;
            }
        }

        return null;
    }

    /**
     * The resource ids one provider relates.
     *
     * Publication is NOT filtered here — the archive's own collection query
     * already excludes unpublished entries, so constraining to stored ids and
     * letting the listing resolve them keeps one source of truth for what is live.
     *
     * The order is the stored one, but nothing downstream depends on it: the
     * listing sorts `date:desc`.
     *
     * @return list<string>
     */
    public function resourceIds(Entry $provider): array
    {
        $ids = $provider->get('clinical_resources');

        // A `max_items: 1` relation can store a bare reference rather than a list.
        if (! \is_array($ids)) {
            return \is_string($ids) && $ids !== '' ? [$ids] : [];
        }

        return array_values(array_filter(
            $ids,
            static fn (mixed $id): bool => \is_string($id) && $id !== ''
        ));
    }

    /**
     * The provider's patient-facing display name for the archive's filter notice,
     * falling back to the entry title.
     *
     * `value()`, NOT `get()`. `display_name_full_with_credentials` is a
     * `Collection::computed()` registration and never stored data, and
     * `ContainsData::get()` reads the stored array only — so `get()` returns null
     * for it unconditionally and the fallback fired every time. That is not a
     * cosmetic miss: the `people` entry `title` is documented in the blueprint as
     * control-panel-only and is stored surname-first ("McCoy, Leonard Horatio Sr"),
     * so the archive was publishing a back-office sort key to a patient-facing
     * page.
     */
    public function displayName(Entry $provider): string
    {
        $name = $provider->value('display_name_full_with_credentials');

        if ($name instanceof Value) {
            $name = $name->value();
        }

        // The person-name computeds resolve to a `{text: …}` shape.
        if (\is_array($name) && \is_string($name['text'] ?? null) && trim($name['text']) !== '') {
            return trim($name['text']);
        }

        $title = $provider->get('title');

        return \is_string($title) ? trim($title) : '';
    }

    /**
     * Whether the person may be rendered publicly — published and not private /
     * password-protected. Mirrors `PeopleLensController::isPubliclyRenderable()`.
     */
    private function isPubliclyRenderable(Entry $person): bool
    {
        return $person->status() === 'published'
            && $person->value('status_and_visibility') !== 'private'
            && $person->value('password_protected') !== true;
    }
}
