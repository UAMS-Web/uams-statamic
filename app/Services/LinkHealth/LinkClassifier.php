<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

use App\Rules\FirstPartyUrl;

/**
 * Assign exactly one LinkKind to a stored value, without resolving anything.
 *
 * Classification is decided from the shape of the value and from two pieces of local context —
 * which hosts this install serves, and which entry ids exist. No HTTP request is made and no
 * target is looked up; whether a link WORKS is a later slice's question.
 */
final readonly class LinkClassifier
{
    /**
     * @param  list<string>  $servedHosts  Lower-cased hosts this Statamic install serves, across
     *                                     every site. A URL on any of them is reachable in-process
     *                                     and is therefore `internal`.
     * @param  array<string, true>  $knownEntryIds  Entry ids as keys, for whole-string matching.
     *                                              Keyed rather than a list so membership is O(1)
     *                                              over the tens of thousands of values a walk
     *                                              tests.
     */
    public function __construct(
        private array $servedHosts,
        private array $knownEntryIds,
    ) {}

    /**
     * Classify one stored value.
     *
     * @param  string  $value  The value exactly as stored.
     * @param  bool  $fromRelationField  True when the value came from an `entries` fieldtype, which
     *                                   is the only context in which a bare id means a reference.
     * @return LinkKind|null The kind, or null when the value is not a link at all.
     */
    public function classify(string $value, bool $fromRelationField = false): ?LinkKind
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        // A fragment addresses a position in the current document and has no target to check.
        if (str_starts_with($trimmed, '#')) {
            return LinkKind::FragmentOnly;
        }

        if ($reference = $this->classifyStatamicReference($trimmed)) {
            return $reference;
        }

        // A bare id is only a reference where the fieldtype says so. Matching UUID SHAPE instead
        // would classify every unrelated identifier in the tree as a relation.
        if ($fromRelationField && isset($this->knownEntryIds[$trimmed])) {
            return LinkKind::EntryRelation;
        }

        // A root-relative path is served by this install by construction.
        if (str_starts_with($trimmed, '/')) {
            return LinkKind::Internal;
        }

        return $this->classifyAbsoluteUrl($trimmed);
    }

    /**
     * Recognize Statamic's own reference forms, with or without the `statamic://` scheme.
     *
     * @param  string  $value  The trimmed value.
     * @return LinkKind|null The reference kind, or null when the value is not a Statamic reference.
     */
    private function classifyStatamicReference(string $value): ?LinkKind
    {
        $bare = str_starts_with($value, 'statamic://')
            ? substr($value, \strlen('statamic://'))
            : $value;

        return match (true) {
            str_starts_with($bare, 'entry::') => LinkKind::EntryReference,
            str_starts_with($bare, 'asset::') => LinkKind::AssetReference,
            str_starts_with($bare, 'term::') => LinkKind::TermReference,
            default => null,
        };
    }

    /**
     * Classify an absolute URL by its host, or as a non-HTTP scheme.
     *
     * @param  string  $value  The trimmed value.
     * @return LinkKind|null The kind, or null when the value carries no scheme at all.
     */
    private function classifyAbsoluteUrl(string $value): ?LinkKind
    {
        $scheme = parse_url($value, PHP_URL_SCHEME);

        if (! \is_string($scheme)) {
            return null;
        }

        // mailto:, tel:, and anything else that is not fetched over HTTP.
        // The strict flag cannot change the outcome: `strtolower()` returns a string and both
        // needles are string literals, so loose and strict comparison agree on every input.
        // @pest-mutate-ignore: TrueToFalse
        if (! \in_array(strtolower($scheme), ['http', 'https'], true)) {
            return LinkKind::NonHttp;
        }

        $host = parse_url($value, PHP_URL_HOST);

        // `parse_url` returns FALSE when a URL carries no authority — measured on `http:///path`,
        // `https://` and `http://`, none of which yield an empty-string host. Testing for `''` as
        // well would be a branch no input can reach, which mutation testing surfaces as a mutant
        // no test can kill.
        if (! \is_string($host)) {
            return null;
        }

        $host = strtolower($host);

        // Served by this install, so resolvable in-process rather than over the network. The
        // kind table describes this as "the origin site's own host"; a SIBLING site served by
        // the same install is equally resolvable, and grouping it with external links would send
        // an editor chasing a target that is right here. Sibling hosts are therefore internal.
        // Same as the scheme test above: `$host` was just lower-cased so it is a string, and
        // `$servedHosts` is `list<string>` by contract, which larastan enforces at level 10.
        // With strings on both sides the strict flag is unobservable.
        // @pest-mutate-ignore: TrueToFalse
        if (\in_array($host, $this->servedHosts, true)) {
            return LinkKind::Internal;
        }

        // Owned by UAMS but served elsewhere — e.g. a uamshealth.com URL authored on a uams.edu
        // site. Neither internal nor a third-party link, and the distinction is the point.
        if (FirstPartyUrl::isFirstPartyUrl($value)) {
            return LinkKind::FirstPartyOther;
        }

        return LinkKind::External;
    }
}
