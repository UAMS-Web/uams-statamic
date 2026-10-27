<?php

declare(strict_types=1);

namespace App\Listeners;

use Statamic\Events\TermSaving;
use Statamic\Taxonomies\Term;

/**
 * Keeps a Google Business Profile category term's `gcid` from changing once it is set (UAMS-Web/uams-statamic-archive#2613).
 *
 * UAMS-Web/uams-statamic-archive#2091 keys the `google_business_profile_categories` taxonomy by Google's category id,
 * because Google renames categories and a slug is editable in the Control Panel. The
 * blueprint marks `gcid` read-only, but that only stops the editor from offering the
 * field. `TermsController::update()` still merges whatever the request carries, so a
 * crafted save could rewrite the key every later match depends on.
 *
 * So a term that already has a `gcid` keeps it through any save, and a term without one
 * may be given one. That second case is how a seed or a backfill writes the key, and
 * restoring rather than refusing lets the rest of an editor's save go through.
 */
final class PreserveGoogleBusinessProfileCategoryGcid
{
    /**
     * The taxonomy whose terms carry the key.
     */
    private const string TAXONOMY = 'google_business_profile_categories';

    /**
     * The field holding Google's category id.
     */
    private const string GCID_FIELD = 'gcid';

    /**
     * Write a term's stored `gcid` back over whatever this save carries.
     *
     * @param  TermSaving  $event  The term-saving event
     */
    public function handle(TermSaving $event): void
    {
        $term = $event->term;

        // `TermSaving` always carries a `Term`, so treating the check as always true
        // changes nothing; it is here to give the untyped event property a type.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $term instanceof Term || $term->taxonomyHandle() !== self::TAXONOMY) {
            return;
        }

        // `getOriginal()` holds the data as last read from disk (the store syncs it on
        // load, and the Control Panel syncs it again before applying a request), so an
        // empty original means the term has no key yet and may be given one.
        $stored = $term->getOriginal(self::GCID_FIELD);

        if (! is_string($stored) || $stored === '') {
            return;
        }

        // `set()` writes the default locale's copy, where a non-localizable field is
        // stored. Writing an unchanged value back is a no-op.
        $term->set(self::GCID_FIELD, $stored);
    }
}
