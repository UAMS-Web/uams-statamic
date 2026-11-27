<?php

declare(strict_types=1);

use App\Services\Facilities\FacilityInheritedAddressService;
use App\Services\Locations\LocationInheritedAddressService;
use App\Services\Locations\LocationInheritedGalleryService;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Assets\Asset;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Value;
use Statamic\Structures\Page;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for `LocationInheritedGalleryService::resolveGallery` (UAMS-Web/uams-statamic#1578):
 * which location's `gallery_image` a profile shows. Mocked, as the sibling
 * `LocationInheritedAddressServiceTest` is, with the parent resolved through the real
 * address service over a mocked tree.
 *
 * @command  php artisan test --compact tests/Feature/Services/Locations/LocationInheritedGalleryServiceTest.php
 */

/**
 * A mocked location with a gallery, parent page and override toggles.
 *
 * @param  list<Asset>|Asset|null  $gallery  The augmented gallery value.
 */
function makeGalleryLocation(
    string $id,
    ?string $parentId = null,
    array|Asset|null $gallery = null,
    bool $imageOverride = false,
    bool $galleryOverride = false
): Entry&MockInterface {
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn('location');

    $page = null;
    if ($parentId !== null) {
        $page = Mockery::mock(Page::class);
        $page->shouldReceive('id')->andReturn($parentId);
    }

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('parent')->andReturn($page);
    $entry->shouldReceive('get')->with('image_parent_override_query')->andReturn($imageOverride);
    $entry->shouldReceive('get')->with('gallery_image_parent_override_query')->andReturn($galleryOverride);
    $entry->shouldReceive('augmentedValue')->with('gallery_image')->andReturn(
        new Value(is_array($gallery) ? new Collection($gallery) : $gallery)
    );

    return $entry;
}

/**
 * A mocked gallery asset.
 */
function makeGalleryAsset(): Asset&MockInterface
{
    return Mockery::mock(Asset::class);
}

function galleryResolver(): LocationInheritedGalleryService
{
    return new LocationInheritedGalleryService(new LocationInheritedAddressService(new FacilityInheritedAddressService));
}

it('returns a standalone location own gallery, in stored order', function (): void {
    $first = makeGalleryAsset();
    $second = makeGalleryAsset();

    expect(galleryResolver()->resolveGallery(makeGalleryLocation('loc1', null, [$first, $second])))->toBe([$first, $second]);
});

it('returns a single-asset gallery as a list', function (): void {
    $only = makeGalleryAsset();

    expect(galleryResolver()->resolveGallery(makeGalleryLocation('loc1', null, $only)))->toBe([$only]);
});

it('returns an empty list for a location with no gallery', function (): void {
    expect(galleryResolver()->resolveGallery(makeGalleryLocation('loc1')))->toBe([]);
});

it('inherits the parent gallery, walking up the tree', function (): void {
    $photo = makeGalleryAsset();
    EntryFacade::shouldReceive('find')->with('gp')->andReturn(makeGalleryLocation('gp', null, [$photo]));
    EntryFacade::shouldReceive('find')->with('p')->andReturn(makeGalleryLocation('p', 'gp'));

    // The child's own stored gallery is ignored: the blueprint hides the field from it.
    expect(galleryResolver()->resolveGallery(makeGalleryLocation('child', 'p', [makeGalleryAsset()])))->toBe([$photo]);
});

it('shows a child own gallery only when it overrides both the images and the gallery', function (bool $imageOverride, bool $galleryOverride, string $expected): void {
    $parentPhoto = makeGalleryAsset();
    $ownPhoto = makeGalleryAsset();
    EntryFacade::shouldReceive('find')->with('p')->andReturn(makeGalleryLocation('p', null, [$parentPhoto]));

    $resolved = galleryResolver()->resolveGallery(makeGalleryLocation('child', 'p', [$ownPhoto], $imageOverride, $galleryOverride));

    expect($resolved)->toBe($expected === 'own' ? [$ownPhoto] : [$parentPhoto]);
})->with([
    'both overrides' => [true, true, 'own'],
    'image override only' => [true, false, 'parent'],
    'gallery override only' => [false, true, 'parent'],
]);

it('lets an overriding child with no gallery remove the parent gallery', function (): void {
    EntryFacade::shouldReceive('find')->with('p')->andReturn(makeGalleryLocation('p', null, [makeGalleryAsset()]));

    expect(galleryResolver()->resolveGallery(makeGalleryLocation('child', 'p', null, true, true)))->toBe([]);
});

it('returns an empty list rather than looping on a cycle in the tree', function (): void {
    EntryFacade::shouldReceive('find')->with('a')->andReturn(makeGalleryLocation('a', 'b', [makeGalleryAsset()]));
    EntryFacade::shouldReceive('find')->with('b')->andReturn(makeGalleryLocation('b', 'a', [makeGalleryAsset()]));

    expect(galleryResolver()->resolveGallery(makeGalleryLocation('a', 'b')))->toBe([]);
});

it('registers the gallery as the resolved_gallery_image computed on clinical_locations', function (): void {
    expect(Statamic\Facades\Collection::getComputedCallbacks('clinical_locations')->keys()->all())->toContain('resolved_gallery_image');
});
