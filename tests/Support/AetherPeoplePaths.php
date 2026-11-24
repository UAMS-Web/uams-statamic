<?php

declare(strict_types=1);

use Statamic\Assets\AssetContainer as StatamicAssetContainer;
use Statamic\Facades\AssetContainer;

/**
 * Resolve a file that moved into `uams-web/aether-people` (#696) to the add-on's copy.
 *
 * Takes the path the file had in the site, relative to `resources/` (with or without
 * that prefix), and returns the absolute path of the installed add-on's file. Three
 * directories were renamed in the move; every other path keeps its shape.
 *
 * @param  string  $siteRelative  The file's former site path, such as
 *                                `blueprints/collections/people/person.yaml`.
 * @return string The absolute path of the add-on's copy.
 */
function aetherPeopleResourcePath(string $siteRelative): string
{
    // Drop the leading `resources/` a caller may have kept
    $relative = preg_replace('#^resources/#', '', $siteRelative) ?? $siteRelative;

    // Map the three directories the add-on renamed
    $renamed = [
        'blueprints/collections/' => 'collection-blueprints/',
        'views/vendor/statamic/forms/fields/' => 'field-views/forms/fields/',
        'views/vendor/aether-search/cards/' => 'search-views/cards/',
    ];

    foreach ($renamed as $from => $to) {
        // Check whether the path sits under a renamed directory
        if (str_starts_with($relative, $from)) {
            $relative = $to.substr($relative, strlen($from));

            break;
        }
    }

    // Build the path inside the installed add-on
    $path = base_path('vendor/uams-web/aether-people/resources/'.$relative);

    // Fail loudly rather than let a caller read a missing file as empty
    if (! is_file($path)) {
        throw new RuntimeException(sprintf('No aether-people file at %s (from %s).', $path, $siteRelative));
    }

    return $path;
}

/**
 * Resolve a resource the site serves, whether it lives in the site or in an add-on.
 *
 * A host copy shadows the add-on's, as Statamic resolves views and blueprints, so the
 * site's file wins when it exists and the add-on's is read otherwise: a form-field partial
 * from `uams-web/aether-fieldtypes` (#996 to #999), anything else from
 * `uams-web/aether-people`. Used by tests whose datasets mix files that stayed in the site
 * with files that moved (#696).
 *
 * @param  string  $siteRelative  The path relative to `resources/` (with or without that
 *                                prefix), as it was in the site.
 * @return string The absolute path of the file the site serves.
 */
function servedResourcePath(string $siteRelative): string
{
    // Drop the leading `resources/` a caller may have kept
    $relative = preg_replace('#^resources/#', '', $siteRelative) ?? $siteRelative;

    // Prefer the site's own copy, which shadows the add-on's
    $sitePath = resource_path($relative);

    if (is_file($sitePath)) {
        return $sitePath;
    }

    // A form-field partial that moved into the fieldtype add-on
    $fieldsPrefix = 'views/vendor/statamic/forms/fields/';

    if (str_starts_with($relative, $fieldsPrefix)) {
        $fieldtypesPath = base_path('vendor/uams-web/aether-fieldtypes/resources/views/forms/fields/'.substr($relative, strlen($fieldsPrefix)));

        if (is_file($fieldtypesPath)) {
            return $fieldtypesPath;
        }
    }

    // Fall back to aether-people, which throws when it has no such file either
    return aetherPeopleResourcePath($relative);
}

/**
 * Create the asset containers the add-on's person blueprint names, in an isolated Stache.
 *
 * The person blueprint ships in `uams-web/aether-people` as a namespaced blueprint, which
 * an isolated test still resolves after it re-roots the blueprint directory (#696). Its
 * `portraits` and `portraits_wide` assets fields then need their containers to augment,
 * and the site's content holds both, but an isolated Stache starts empty. A test that
 * renders a `people` entry calls this in its setup.
 */
function makePersonPortraitContainers(): void
{
    // Save each container the person blueprint's portrait fields name, on its own disk
    foreach (['portraits' => 'Portraits', 'portraits_wide' => 'Wide Portraits'] as $handle => $title) {
        $container = mustBe(StatamicAssetContainer::class, AssetContainer::make($handle), "the {$handle} container");
        $container->title($title);
        $container->disk($handle);
        $container->save();
    }
}
