<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Base test case for a test that saves entries or terms **and** persists a collection
 * tree, while still reading the real content for everything else
 * (UAMS-Web/uams-statamic#558).
 *
 * {@see EntriesAndTermsIsolatedTestCase} moves the `entries` and `terms` stores onto a
 * per-process copy of the mirror, and says that an entry saved in a structured
 * collection also saves that collection's tree, which it leaves on the shared mirror.
 * {@see CollectionTreeIsolatedTestCase} moves the trees and leaves the entries. A test
 * that authors a tree and saves entries into it needs both, which this base applies in
 * that order: the entries and terms first, so the structured `uri` indexes
 * {@see IsolatesCollectionTrees} keeps are read through the overlay over the copy,
 * then the trees.
 *
 * Every Stache cache write either half makes lands in the per-test overlay, so the
 * trees half's teardown restores write into the overlay too and are discarded with it.
 *
 * **A structured entry's URI comes from the test's own tree here, not the committed
 * one.** The entries half forgets every cached entry, so an entry read in the test
 * loads from the copy's file and computes its `uri()` from the throwaway tree, which
 * is blank until the test authors it; an existing `pages` entry then reads `/`.
 * {@see CollectionTreeIsolatedTestCase} keeps the cached entries, which still carry
 * the committed URI. Author the tree a URI assertion depends on.
 *
 * What neither half isolates is unchanged: globals, navigation, asset containers and
 * form submissions stay on the shared mirror.
 */
abstract class EntriesTermsAndTreesIsolatedTestCase extends EntriesAndTermsIsolatedTestCase
{
    use IsolatesCollectionTrees;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolateCollectionTrees();
    }

    protected function tearDown(): void
    {
        $this->restoreCollectionTrees();

        parent::tearDown();
    }
}
