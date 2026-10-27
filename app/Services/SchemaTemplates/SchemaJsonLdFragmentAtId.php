<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Services\Encoding\UrlSafeBase64StringCodec;
use InvalidArgumentException;

/**
 * Builds canonical JSON-LD `@id` values whose fragment is URL-safe Base64 of a
 * site path, matching `SchemaThingFromRelationshipResolver` taxonomy term
 * identity (`canonicalIdForTaxonomyTermUrl`).
 */
final class SchemaJsonLdFragmentAtId
{
    /**
     * Build `stem#fragment` where `stem` is `config('app.url')` without a trailing
     * slash and `fragment` is `UrlSafeBase64StringCodec::encode($path)`.
     *
     * @param  string  $path  Root-relative path (must start with `/`)
     * @return string Absolute `@id` URI for JSON-LD
     *
     * @throws InvalidArgumentException When `$path` is empty or not root-relative
     */
    public static function atIdFromRootRelativePath(
        string $path
    ): string {
        // Reject paths that are not root-relative (empty is covered: it does not
        // start with `/`).
        if (
            ! str_starts_with(
                $path,
                '/'
            )
        ) {
            throw new InvalidArgumentException(
                'JSON-LD fragment path must be a non-empty string starting with `/`.'
            );
        }

        // Read the site origin stem (same as taxonomy term canonical @id)
        $appUrl = config('app.url');
        $baseUrl = rtrim(
            is_scalar($appUrl) ? (string) $appUrl : '',
            '/'
        );

        // Encode the path with the shared URL-safe Base64 codec (no padding)
        $fragment = UrlSafeBase64StringCodec::encode($path);

        // Concatenate stem, hash, and fragment identifier
        return sprintf('%s#%s', $baseUrl, $fragment);
    }
}
