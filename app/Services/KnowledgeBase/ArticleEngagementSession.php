<?php

declare(strict_types=1);

namespace App\Services\KnowledgeBase;

use Illuminate\Support\Facades\Session;

/**
 * Per-session record of a visitor's knowledge base engagement, backing repeat-vote
 * and repeat-view prevention (UAMS-Web/uams-statamic-archive#286, UAMS-Web/uams-statamic-archive#287).
 *
 * State is scoped to the visitor's session (established by the AJAX request itself,
 * so it works regardless of page static-caching): which articles they have already
 * viewed, and their current like / dislike reaction per article. This is a UX guard
 * against accidental double-counting and drives the "reflect the current vote state"
 * requirement — not a security control (a client can drop its session cookie).
 */
class ArticleEngagementSession
{
    /**
     * Session key holding the list of article ids viewed this session.
     */
    private const string VIEWED_KEY = 'kb_engagement.viewed';

    /**
     * Session key holding the map of article id => current reaction handle.
     */
    private const string REACTIONS_KEY = 'kb_engagement.reactions';

    /**
     * Whether this session has already counted a view for the article.
     *
     * @param  string  $entryId  The article entry id
     * @return bool True when a view was already recorded this session
     */
    public function hasViewed(string $entryId): bool
    {
        return in_array($entryId, $this->viewed(), true);
    }

    /**
     * Record that this session has now counted a view for the article.
     *
     * @param  string  $entryId  The article entry id
     */
    public function markViewed(string $entryId): void
    {
        // Skip when already recorded to keep the list free of duplicates
        if ($this->hasViewed($entryId)) {
            return;
        }

        // Append the id and persist the updated list
        $viewed = $this->viewed();
        $viewed[] = $entryId;
        Session::put(self::VIEWED_KEY, $viewed);
    }

    /**
     * The visitor's current reaction for the article, or null when they have not
     * voted (or toggled their vote off) this session.
     *
     * @param  string  $entryId  The article entry id
     * @return string|null The stored reaction (`like` / `dislike`) or null
     */
    public function reactionFor(string $entryId): ?string
    {
        // Read the stored reaction for this id, if any
        $reaction = $this->reactions()[$entryId] ?? null;

        // Only surface a recognized reaction handle
        return is_string($reaction) ? $reaction : null;
    }

    /**
     * Persist the visitor's reaction for the article, or clear it when null (an
     * un-vote / toggled-off reaction leaves no entry behind).
     *
     * @param  string  $entryId  The article entry id
     * @param  string|null  $reaction  The reaction to store, or null to clear
     */
    public function setReaction(string $entryId, ?string $reaction): void
    {
        // Read the current reaction map to update it
        $reactions = $this->reactions();

        // A null reaction clears the entry; otherwise store the handle
        if ($reaction === null) {
            unset($reactions[$entryId]);
        } else {
            $reactions[$entryId] = $reaction;
        }

        // Persist the updated map
        Session::put(self::REACTIONS_KEY, $reactions);
    }

    /**
     * The list of article ids viewed this session.
     *
     * @return list<string> The viewed article ids
     */
    private function viewed(): array
    {
        // Read the stored list, coercing anything unexpected to an empty list
        $viewed = Session::get(self::VIEWED_KEY, []);

        // Keep only string ids so a corrupted session cannot break callers
        return is_array($viewed)
            ? array_values(array_filter($viewed, is_string(...)))
            : [];
    }

    /**
     * The map of article id => current reaction handle. Session values are untyped,
     * so keys are narrowed to strings and values left mixed; `reactionFor` filters
     * each value down to a recognized handle.
     *
     * @return array<string, mixed> The reaction map keyed by article id
     */
    private function reactions(): array
    {
        // Read the stored map, coercing anything unexpected to an empty map
        $reactions = Session::get(self::REACTIONS_KEY, []);

        // Guard against a non-array session value
        if (! is_array($reactions)) {
            return [];
        }

        // Keep only string-keyed entries so a corrupted session cannot break callers
        $map = [];
        foreach ($reactions as $key => $value) {
            if (is_string($key)) {
                $map[$key] = $value;
            }
        }

        return $map;
    }
}
