<?php

declare(strict_types=1);

namespace App\Support;

use Statamic\Contracts\Taxonomies\TermRepository;
use Statamic\Facades\Entry as EntryFacade;

/**
 * Resolves the Statamic entry or term being edited in the CP from the referrer
 * URL, for relationship field query scopes.
 */
final class CpRelFieldEditContext
{
    /**
     * Resolve context using the previous request URL (relationship field CP UI).
     *
     * @return array{type: 'Entry'|'Term', id: string}|array{type: null, id: null}
     *                                                                             Context type and id: an entry id, a `handle::slug` term reference, or
     *                                                                             both null when no edited record could be validated
     */
    public static function resolve(): array
    {
        // Read the prior URL Statamic used when opening the field
        $absoluteUrl = url()->previous();

        // Parse and validate that URL into a type and id pair
        return self::fromAbsoluteUrl($absoluteUrl);
    }

    /**
     * Parse an absolute URL into CP edit context and validate against Statamic.
     *
     * @return array{type: 'Entry'|'Term', id: string}|array{type: null, id: null}
     *                                                                             Context type and id: an entry id, a `handle::slug` term reference, or
     *                                                                             both null when no edited record could be validated
     */
    public static function fromAbsoluteUrl(
        string $absoluteUrl
    ): array {
        // Initialize an empty type and id for unknown or invalid URLs
        $output = [
            'type' => null,
            'id' => null,
        ];

        // Reduce the referrer to its path, dropping any query string and fragment.
        //
        // Without this, a referrer like `…/entries/abc-123?blueprint=x` (or `…#tab`)
        // leaves the `?…` / `#…` attached to the id/slug segment after the split,
        // so the entry/term lookup fails, the context is silently cleared, and the
        // cycle-prevention pickers (ExcludeSelf / ExcludeDescendants) fail open —
        // leaving the edited record and its descendants selectable. (UAMS-Web/uams-statamic-archive#1369)
        $path = parse_url(
            $absoluteUrl,
            PHP_URL_PATH
        );

        // Stop when the URL is malformed and exposes no parseable path
        if (! \is_string($path)) {
            return $output;
        }

        // Locate the `/cp/` segment inside the path
        $cpOffset = strpos(
            $path,
            '/cp/'
        );

        // Stop when the URL is not a control-panel path
        if ($cpOffset === false) {
            return $output;
        }

        // Strip everything before `/cp/` to get the CP-relative path
        $relativeUrl = substr(
            $path,
            $cpOffset
        );

        // Split path segments (drop the leading empty segment before `cp`)
        $urlParts = \array_slice(
            explode(
                '/',
                $relativeUrl
            ),
            1
        );

        // Stop when the path is too short to describe an entry or term edit
        if (\count($urlParts) < 5) {
            return $output;
        }

        // Stop when the URL targets a create screen instead of an existing item
        if ($urlParts[4] === 'create') {
            return $output;
        }

        // ---
        // Classify the resource as an entry or a term from the second segment
        // ---

        // Check if the context is an entry edit URL
        if ($urlParts[1] === 'collections') {
            // Set the type to Entry
            $output['type'] = 'Entry';
        } elseif ($urlParts[1] === 'taxonomies') { // Check if the context is a term edit URL
            // Set the type to Term
            $output['type'] = 'Term';
        } else {
            // Return the output with no type and id
            return $output;
        }

        // Capture the taxonomy handle for term URLs
        $taxonomyHandle = null;

        // ---
        // Require a taxonomy handle when resolving a term edit URL
        // ---

        // Check if the context is a term edit URL
        if ($output['type'] === 'Term') {
            // Read the taxonomy handle from the URL segments
            $taxonomyHandle = $urlParts[2];

            // Check if the taxonomy handle is empty
            if ($taxonomyHandle === '') {
                // Clear the type so a type is never returned without an id
                $output['type'] = null;

                // Return the output with no type and id
                return $output;
            }
        }

        // Read the entry id or term slug segment from the URL
        $output['id'] = $urlParts[4];

        // ---
        // Validate an entry id against the repository when type is Entry
        // ---

        // Check if the context is an entry edit URL
        if ($output['type'] === 'Entry') {
            // Read the entry id from the URL segments
            $entryId = $output['id'];

            // ---
            // Clear the id when the entry cannot be loaded
            // ---

            // Check if the entry id is empty or the entry cannot be loaded
            if (
                $entryId === ''
                || EntryFacade::find($entryId) === null
            ) {
                // Clear the id when the entry cannot be loaded
                $output['id'] = null;
            }
        } elseif ($output['type'] === 'Term') {
            // ---
            // Validate a term slug against the repository when type is Term
            // ---

            // Read the term slug from the URL segments
            $slug = $output['id'];

            // Check if the term slug is empty
            if ($slug === '') {
                // Clear the id when the term slug is empty; the id check below
                // then clears the type too
                $output['id'] = null;
            } else {
                // Build the canonical term reference `handle::slug`
                $termReference = sprintf('%s::%s', $taxonomyHandle, $slug);

                // Resolve the repository from the container for test doubles
                $termRepository = app(TermRepository::class);

                // Keep the canonical reference only when the term exists in the repo
                $output['id'] = $termRepository->find($termReference) !== null
                    ? $termReference
                    : null;
            }
        }

        // Check if the id is missing
        if ($output['id'] === null) {
            // Clear the type when the id failed validation
            $output['type'] = null;
        }

        // Return the output with the type and id
        return $output;
    }
}
