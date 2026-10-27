<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Disabled collections
    |--------------------------------------------------------------------------
    |
    | Universal orphaned-ancestor handling (UAMS-Web/uams-statamic-archive#1429) applies to every routable +
    | hierarchical collection by default. List a collection handle here to opt it
    | out of BOTH the front-end redirect and the Control Panel banner — for a
    | collection that intentionally allows published entries under an unpublished
    | ancestor, or that keeps a bespoke mechanism.
    |
    | `knowledge_bases` was folded onto the universal mechanism in UAMS-Web/uams-statamic-archive#1455 (retiring
    | its bespoke UAMS-Web/uams-statamic-archive#217 redirect + UAMS-Web/uams-statamic-archive#1077 CP banner), so it is no longer excluded.
    | Because it carries no collection-level `mount`, the universal resolver's
    | nearest-live-ancestor policy reproduces KB's outcomes exactly (an unpublished
    | knowledge_base root has no live ancestor and no mount root, so it falls
    | through to the site root — identical to the old KB policy).
    |
    */

    'disabled_collections' => [
        //
    ],

];
