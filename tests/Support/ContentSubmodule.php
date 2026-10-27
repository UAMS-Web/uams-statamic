<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\Assert;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Contracts\Entries\Entry as EntryContract;

/**
 * Discriminates an **absent** `content/` submodule from a content lookup that
 * failed **with** the submodule present (UAMS-Web/uams-statamic-archive#1738).
 *
 * The content guards used to treat "the query returned nothing" as proof that
 * `content/` was missing, so any other cause of a `null` lookup — a partially
 * built Stache index, a contended worker, a renamed handle, a fixture the
 * content set never contained — was absorbed as a benign `SKIP` while the band
 * still reported `PASS`. Three `KnowledgeBaseMountResolutionTest` tests self-
 * skipped that way in a fully populated checkout and nothing said a word.
 *
 * The submodule's presence is a cheap, unambiguous filesystem fact, so that is
 * what the guards key on now:
 *
 * - `content/` absent → `markTestSkipped()` exactly as before, preserving the
 *   bare-worktree and `local-ci --skip-submodule` behavior UAMS-Web/uams-statamic-archive#856 and UAMS-Web/uams-statamic-archive#900 were
 *   built for.
 * - `content/` present but the lookup returned `null` → **fail**, naming what
 *   did not resolve.
 *
 * `skipWithoutQualifyingEntry()` is the deliberate exception: it guards a *data*
 * precondition rather than a lookup, so it never probes the filesystem and
 * always skips.
 *
 * Static rather than a trait method because the guards have two callers with
 * incompatible shapes: `Tests\Concerns\RequiresContentSubmodule` mixes into a
 * `TestCase`, while `KnowledgeBaseMountResolutionTest` guards from top-level
 * Pest functions where `$this` is not available. One discriminator, reachable
 * from both.
 */
final class ContentSubmodule
{
    /**
     * Checkout-relative path whose contents prove `content/` is initialized.
     */
    private const string COLLECTIONS_PATH = 'content/collections';

    /**
     * Absolute path to this checkout's `content/collections` directory.
     *
     * Derived from this file's own location rather than `base_path()` so the
     * guards work from a plain PHP context with no booted application.
     *
     * @return string The default collections path
     */
    public static function collectionsPath(): string
    {
        return dirname(__DIR__, 2).'/'.self::COLLECTIONS_PATH;
    }

    /**
     * Is the `content/` submodule initialized in this checkout?
     *
     * Collection *configs* live in `content/collections/*.yaml`, and git leaves
     * the mount point of an uninitialized submodule present but empty — so the
     * directory existing is not enough on its own.
     *
     * @param  string|null  $collectionsPath  Collections directory to probe, defaulting to this checkout's
     * @return bool Whether `content/` is populated
     */
    public static function isInitialized(?string $collectionsPath = null): bool
    {
        $path = $collectionsPath ?? self::collectionsPath();

        // An uninitialized submodule leaves the directory absent or empty.
        if (! is_dir($path)) {
            return false;
        }

        // `glob()` returns false on failure, which is not an empty result.
        $configs = glob($path.'/*.yaml');

        return is_array($configs) && $configs !== [];
    }

    /**
     * Report a content lookup that resolved nothing — skipping only when the
     * submodule is genuinely absent, and failing when it is not.
     *
     * @param  string  $description  What the missing content provides
     * @param  string|null  $collectionsPath  Collections directory to probe, defaulting to this checkout's
     */
    public static function reportMissing(string $description, ?string $collectionsPath = null): never
    {
        // Without the submodule there is nothing to resolve — skip, as before.
        if (! self::isInitialized($collectionsPath)) {
            Assert::markTestSkipped("Requires the content/ submodule ({$description}).");
        }

        // With it present, a null lookup is a defect and must be loud (UAMS-Web/uams-statamic-archive#1738).
        Assert::fail(
            "Content lookup resolved nothing while content/ is initialized: {$description}. "
            .'This is a failure, not an absent submodule — find why it stopped resolving '
            .'(a starved Stache worker, a renamed handle, or a fixture the content set never contained).'
        );
    }

    /**
     * Assert a real committed entry resolved.
     *
     * @param  EntryContract|null  $entry  The resolved entry, or `null`
     * @param  string  $description  What the missing entry provides
     * @param  string|null  $collectionsPath  Collections directory to probe, defaulting to this checkout's
     * @return EntryContract The non-null entry
     */
    public static function requireEntry(?EntryContract $entry, string $description, ?string $collectionsPath = null): EntryContract
    {
        if (! $entry) {
            self::reportMissing($description, $collectionsPath);
        }

        return $entry;
    }

    /**
     * Skip when no committed entry *qualifies* for what a test needs.
     *
     * The counterpart to `requireEntry()`, and deliberately distinct from it.
     * `requireEntry()` guards a lookup that must never legitimately return
     * `null` — a named handle, a collection the app declares — so a `null` there
     * with `content/` present is an infrastructure failure. This one guards a
     * *data* precondition the content set is free not to satisfy ("a published
     * topic that has a published descendant article"), where `null` is an
     * editorial fact rather than a defect. Failing there would red the band on a
     * content edit and send the reader hunting a starved Stache worker that does
     * not exist.
     *
     * @param  EntryContract|null  $entry  The resolved entry, or `null`
     * @param  string  $description  What no committed entry satisfies
     * @return EntryContract The non-null entry
     */
    public static function skipWithoutQualifyingEntry(?EntryContract $entry, string $description): EntryContract
    {
        if (! $entry) {
            Assert::markTestSkipped("No committed content qualifies ({$description}).");
        }

        return $entry;
    }

    /**
     * Skip because no committed content *qualifies*, with no entry in hand.
     *
     * The bare-guard counterpart to `skipWithoutQualifyingEntry()`, for the many
     * call sites that decide the precondition themselves — a filtered query that
     * came back empty, a loop that found no candidate — and so have nothing to
     * hand a guard. Same contract: this is an editorial fact about the content
     * set, never an infrastructure failure, so it always skips and never probes
     * the filesystem.
     *
     * Use `reportMissing()` instead wherever `null` would mean the content is
     * broken rather than merely unopinionated.
     *
     * @param  string  $description  What no committed content satisfies
     */
    public static function reportNoQualifyingContent(string $description): never
    {
        Assert::markTestSkipped("No committed content qualifies ({$description}).");
    }

    /**
     * Assert a collection that lives in the submodule resolved.
     *
     * @param  CollectionContract|null  $collection  The resolved collection, or `null`
     * @param  string  $handle  The collection handle (e.g. `people`, `posts`)
     * @param  string|null  $collectionsPath  Collections directory to probe, defaulting to this checkout's
     * @return CollectionContract The non-null collection
     */
    public static function requireCollection(?CollectionContract $collection, string $handle, ?string $collectionsPath = null): CollectionContract
    {
        if (! $collection) {
            self::reportMissing("{$handle} collection", $collectionsPath);
        }

        return $collection;
    }
}
