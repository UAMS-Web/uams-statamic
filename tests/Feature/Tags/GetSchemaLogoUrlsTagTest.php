<?php

declare(strict_types=1);

use App\Tags\GetSchemaLogoUrls;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Assets\Asset;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Contracts\Globals\Variables as GlobalVariablesContract;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\GlobalSet;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Feature tests for the GetSchemaLogoUrls tag (JSON logo URL list for schema_object).
 *
 * Mutation: 100% on App\Tags\GetSchemaLogoUrls (63 mutants, --covered-only), with five
 * equivalents annotated in the class. One line is not covered: index()'s skip of an empty
 * path, because flattenLogoPaths() never returns one.
 *
 * @command  php artisan test --compact tests/Feature/Tags/GetSchemaLogoUrlsTagTest.php
 */
it('returns an empty json array when the global is missing', function () {
    GlobalSet::shouldReceive('findByHandle')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn(null);

    $tag = new GetSchemaLogoUrls;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('[]');
});

it('returns empty json when the subject is not a string', function () {
    $vars = Mockery::mock(GlobalVariablesContract::class);
    $vars->shouldReceive('get')
        ->with('subject')
        ->andReturn(null);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('inCurrentSite')
        ->once()
        ->andReturn($vars);

    GlobalSet::shouldReceive('findByHandle')
        ->once()
        ->andReturn($set);

    $tag = new GetSchemaLogoUrls;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('[]');
});

/**
 * Bind the settings global so its current-site variables read from `$values`.
 *
 * @param  array<string, mixed>  $values
 */
function schemaLogoGlobal(array $values): GlobalSetContract
{
    $vars = Mockery::mock(GlobalVariablesContract::class);
    foreach (['subject', 'global_header_logo', 'global_footer_logo'] as $key) {
        $vars->shouldReceive('get')->with($key)->andReturn($values[$key] ?? null);
    }

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('inCurrentSite')->andReturn($vars);
    GlobalSet::shouldReceive('findByHandle')->with('uams_multisite_settings')->andReturn($set);

    return $set;
}

/**
 * Bind Asset::find() so each `logos::<path>` id resolves to the given asset.
 *
 * @param  array<string, mixed>  $assets  path => asset (or null)
 */
function schemaLogoAssets(array $assets): void
{
    AssetFacade::shouldReceive('find')->andReturnUsing(
        fn (string $id): mixed => $assets[substr($id, \strlen('logos::'))] ?? null
    );
}

/**
 * A concrete Asset double with the given absolute and relative URLs.
 */
function schemaLogoAsset(mixed $absoluteUrl, string $url = '/relative.svg'): Asset
{
    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('absoluteUrl')->andReturn($absoluteUrl);
    $asset->shouldReceive('url')->andReturn($url);

    return $asset;
}

/**
 * Run the tag with the given parameters.
 *
 * @param  array<mixed>  $parameters
 */
function schemaLogoUrls(array $parameters = []): string
{
    $tag = new GetSchemaLogoUrls;
    $tag->setContext([]);
    $tag->setParameters($parameters);

    return $tag->index();
}

it('reads the named site, and the current site for an empty or non-string one', function () {
    $vars = Mockery::mock(GlobalVariablesContract::class);
    $vars->shouldReceive('get')->andReturnNull();
    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')->once()->with('health')->andReturn($vars);
    $set->shouldReceive('inCurrentSite')->twice()->andReturn($vars);
    GlobalSet::shouldReceive('findByHandle')->andReturn($set);

    expect(schemaLogoUrls(['site' => 'health']))->toBe('[]')
        ->and(schemaLogoUrls(['site' => '']))->toBe('[]')
        ->and(schemaLogoUrls(['site' => 5]))->toBe('[]');
});

it('returns an empty json array when the site has no variables', function () {
    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')->with('nowhere')->andReturnNull();
    GlobalSet::shouldReceive('findByHandle')->andReturn($set);

    expect(schemaLogoUrls(['site' => 'nowhere']))->toBe('[]');
});

it('collects header and footer logos for the root subject, flattening nested groups', function () {
    schemaLogoGlobal([
        'subject' => 'root',
        'global_header_logo' => ['horizontal' => ['color' => 'header.svg', 'white' => ''], 'stacked' => 5],
        'global_footer_logo' => ['horizontal' => ['color' => 'footer.svg']],
    ]);
    schemaLogoAssets([
        'header.svg' => schemaLogoAsset('https://cdn.test/header.svg'),
        'footer.svg' => schemaLogoAsset('https://cdn.test/footer.svg'),
    ]);

    expect(schemaLogoUrls())->toBe('["https://cdn.test/header.svg","https://cdn.test/footer.svg"]');
});

it('skips a root logo group that is not an array', function (string $missing, string $kept) {
    schemaLogoGlobal([
        'subject' => 'root',
        'global_'.$missing.'_logo' => 'not-a-group',
        'global_'.$kept.'_logo' => ['color' => $kept.'.svg'],
    ]);
    schemaLogoAssets([$kept.'.svg' => schemaLogoAsset('https://cdn.test/'.$kept.'.svg')]);

    expect(schemaLogoUrls())->toBe('["https://cdn.test/'.$kept.'.svg"]');
})->with([
    'header missing' => ['header', 'footer'],
    'footer missing' => ['footer', 'header'],
]);

it('collects only the footer logo for an entity subject', function (string $subject) {
    schemaLogoGlobal([
        'subject' => $subject,
        'global_header_logo' => ['color' => 'header.svg'],
        'global_footer_logo' => ['color' => 'footer.svg'],
    ]);
    schemaLogoAssets([
        'header.svg' => schemaLogoAsset('https://cdn.test/header.svg'),
        'footer.svg' => schemaLogoAsset('https://cdn.test/footer.svg'),
    ]);

    expect(schemaLogoUrls())->toBe('["https://cdn.test/footer.svg"]');
})->with(['major_entity', 'sub_entity']);

it('collects nothing for an entity subject whose footer logo is not a group', function () {
    schemaLogoGlobal(['subject' => 'sub_entity', 'global_footer_logo' => 'footer.svg']);
    AssetFacade::shouldReceive('find')->never();

    expect(schemaLogoUrls())->toBe('[]');
});

it('collects nothing for any other subject', function () {
    schemaLogoGlobal([
        'subject' => 'department',
        'global_header_logo' => ['color' => 'header.svg'],
        'global_footer_logo' => ['color' => 'footer.svg'],
    ]);
    AssetFacade::shouldReceive('find')->never();

    expect(schemaLogoUrls())->toBe('[]');
});

it('looks each path up in the logos container and drops paths with no asset', function () {
    schemaLogoGlobal(['subject' => 'root', 'global_header_logo' => ['a' => 'gone.svg', 'b' => 'here.svg']]);
    AssetFacade::shouldReceive('find')->with('logos::gone.svg')->once()->andReturnNull();
    AssetFacade::shouldReceive('find')->with('logos::here.svg')->once()->andReturn(schemaLogoAsset('https://cdn.test/here.svg'));

    expect(schemaLogoUrls())->toBe('["https://cdn.test/here.svg"]');
});

it('falls back to the relative url when there is no absolute one', function (mixed $asset) {
    schemaLogoGlobal(['subject' => 'root', 'global_header_logo' => ['a' => 'logo.svg']]);
    schemaLogoAssets(['logo.svg' => $asset]);

    expect(schemaLogoUrls())->toBe('["/relative.svg"]');
})->with([
    'empty absolute url' => [fn () => schemaLogoAsset('')],
    'non-string absolute url' => [fn () => schemaLogoAsset(null)],
    'asset contract, not the concrete class' => [function () {
        $asset = Mockery::mock(AssetContract::class);
        $asset->shouldReceive('url')->andReturn('/relative.svg');

        return $asset;
    }],
]);

it('drops an asset whose urls are both empty', function () {
    schemaLogoGlobal(['subject' => 'root', 'global_header_logo' => ['a' => 'blank.svg', 'b' => 'ok.svg']]);
    schemaLogoAssets([
        'blank.svg' => schemaLogoAsset('', ''),
        'ok.svg' => schemaLogoAsset('https://cdn.test/ok.svg'),
    ]);

    expect(schemaLogoUrls())->toBe('["https://cdn.test/ok.svg"]');
});

it('removes duplicate urls and keeps the output a json list', function () {
    // The duplicate sits between two distinct URLs, so dropping it leaves a gap in
    // the keys that would encode as a JSON object without the reindex.
    schemaLogoGlobal([
        'subject' => 'root',
        'global_header_logo' => ['a' => 'one.svg', 'b' => 'one-again.svg'],
        'global_footer_logo' => ['a' => 'two.svg'],
    ]);
    schemaLogoAssets([
        'one.svg' => schemaLogoAsset('https://cdn.test/one.svg'),
        'one-again.svg' => schemaLogoAsset('https://cdn.test/one.svg'),
        'two.svg' => schemaLogoAsset('https://cdn.test/two.svg'),
    ]);

    expect(schemaLogoUrls())->toBe('["https://cdn.test/one.svg","https://cdn.test/two.svg"]');
});

it('keeps non-ASCII characters unescaped in the json', function () {
    schemaLogoGlobal(['subject' => 'root', 'global_header_logo' => ['a' => 'logo.svg']]);
    schemaLogoAssets(['logo.svg' => schemaLogoAsset('https://cdn.test/é.svg')]);

    expect(schemaLogoUrls())->toBe('["https://cdn.test/é.svg"]');
});

it('returns an empty json array when the urls cannot be encoded', function () {
    schemaLogoGlobal(['subject' => 'root', 'global_header_logo' => ['a' => 'logo.svg']]);
    schemaLogoAssets(['logo.svg' => schemaLogoAsset("https://cdn.test/\xB1.svg")]);

    expect(schemaLogoUrls())->toBe('[]');
});
