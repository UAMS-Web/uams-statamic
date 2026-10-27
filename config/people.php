<?php

declare(strict_types=1);
use App\Support\Collections\CollectionExtensions;

/**
 * The `people` collection, as site core code reads it (UAMS-Web/uams-statamic-archive#3180).
 *
 * Site core names the collection through this key rather than as a literal, so the people code can
 * move into `uams-web/statamic-people` (#281) without the site naming a collection it no longer owns.
 * Behavior the people code adds to site core (the entry class, the sitemap inclusion rule, the document
 * title and scheduled publishing) is registered through {@see CollectionExtensions}.
 *
 * The handle is not configurable in practice: content, blueprints, templates and
 * `UAMS-Web/wordpress-importer` all name `people`, and no handle is renamed.
 */
return [
    'collection' => 'people',
];
