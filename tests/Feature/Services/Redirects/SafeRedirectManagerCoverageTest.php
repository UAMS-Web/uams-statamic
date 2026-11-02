<?php

declare(strict_types=1);

use App\Tags\FindRedirectRule;
use Statamic\Facades\Antlers;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\StacheIsolatedTestCase;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Assets\AssetImporter;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Globals\Creators\RedirectRuleGlobalCreator;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Parsers\XmlParser;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Utilities\ContentProcessor;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Utilities\MetadataMapper;

/**
 * Regression guard that the importer's Safe Redirect Manager handling still lands
 * `redirect_rule` rows into the `redirects` store (UAMS-Web/uams-statamic-archive#1641).
 *
 * The ~5,700 pre-existing WordPress redirects are carried by the importer's
 * {@see RedirectRuleGlobalCreator}, not by the migration generator — so the app
 * pins that behavior here, driving the creator against a synthesized WXR and
 * asserting the row lands in the shape {@see FindRedirectRule} serves.
 * This catches an importer bump that silently regresses SRM landing (the
 * importer's own suite defers the full `createGlobals()` flow to "the host
 * application's integration tests").
 *
 * @command  php artisan test --compact tests/Feature/Services/Redirects/SafeRedirectManagerCoverageTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('redirects');
    $set->title('Redirects');
    $set->save();

    /** @var Statamic\Sites\Site $site */
    $site = Site::default();
    /** @var Variables $vars */
    $vars = $set->makeLocalization($site->handle());
    $vars->data(['redirects' => []]);
    $vars->save();
});

/**
 * Wrap a `redirect_rule` `<item>` in a minimal but valid WXR document.
 */
function wxrWithRedirectRule(string $item): string
{
    $header = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0"
            xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
            xmlns:content="http://purl.org/rss/1.0/modules/content/"
            xmlns:wfw="http://wellformedweb.org/CommentAPI/"
            xmlns:dc="http://purl.org/dc/elements/1.1/"
            xmlns:wp="http://wordpress.org/export/1.2/">
            <channel>
                <title>Redirect Fixture</title>
                <wp:wxr_version>1.2</wp:wxr_version>
                <wp:base_blog_url>https://old.uams.test</wp:base_blog_url>
        XML;

    return $header."\n".$item."\n    </channel>\n</rss>";
}

it('lands a Safe Redirect Manager rule into the redirects store', function (): void {
    /** @var Statamic\Sites\Site $site */
    $site = Site::default();
    $siteHandle = $site->handle();
    $siteHandle = \is_string($siteHandle) ? $siteHandle : 'default';

    // A regex rule with a pattern target: resolveTarget preserves it as a url
    // target verbatim (no entry resolution), the canonical SRM regex case.
    $item = <<<'XML'
        <item>
            <title>Legacy Redirect</title>
            <wp:post_id>101</wp:post_id>
            <wp:post_type><![CDATA[redirect_rule]]></wp:post_type>
            <wp:status><![CDATA[publish]]></wp:status>
            <wp:menu_order>30</wp:menu_order>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_redirect_rule_from]]></wp:meta_key>
                <wp:meta_value><![CDATA[/legacy/(.*)]]></wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_redirect_rule_from_regex]]></wp:meta_key>
                <wp:meta_value><![CDATA[1]]></wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_redirect_rule_to]]></wp:meta_key>
                <wp:meta_value><![CDATA[/new/$1]]></wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_redirect_rule_status_code]]></wp:meta_key>
                <wp:meta_value><![CDATA[301]]></wp:meta_value>
            </wp:postmeta>
        </item>
        XML;

    $xmlPath = tempnam(sys_get_temp_dir(), 'srm').'.xml';
    file_put_contents($xmlPath, wxrWithRedirectRule($item));

    try {
        $parser = new XmlParser;
        $parser->parse($xmlPath);

        (new RedirectRuleGlobalCreator)->createGlobals(
            $siteHandle,
            'redirects',
            $parser,
            new AssetImporter,
            new BufferedOutput,
            new MetadataMapper,
            new ContentProcessor,
        );
    } finally {
        @unlink($xmlPath);
    }

    /** @var Statamic\Globals\GlobalSet $global */
    $global = GlobalSet::findByHandle('redirects');
    /** @var Variables $vars */
    $vars = $global->in($siteHandle);
    /** @var list<array<string, mixed>> $rows */
    $rows = $vars->get('redirects', []);

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray([
            'source' => '/legacy/(.*)',
            'use_regex' => true,
            'target_type' => 'url',
            'target_url' => '/new/$1',
            'response' => '301',
            'type' => 'redirect',
            'enabled' => true,
        ]);
});

it('lands a Safe Redirect Manager wildcard rule and serves it', function (): void {
    /** @var Statamic\Sites\Site $site */
    $site = Site::default();
    $siteHandle = $site->handle();
    $siteHandle = \is_string($siteHandle) ? $siteHandle : 'default';

    // A wildcard rule as Safe Redirect Manager exports one: the `*` is a literal
    // character in `_redirect_rule_from`, and `_redirect_rule_from_regex` is off.
    // 2,532 of the 5,378 rules in the export corpus take exactly this shape, and
    // every one of them was inert before UAMS-Web/uams-statamic-archive#1933 — the importer landed them
    // faithfully, and the engine had no mode that could ever match a literal `*`.
    $item = <<<'XML'
        <item>
            <title>Legacy Section</title>
            <wp:post_id>202</wp:post_id>
            <wp:post_type><![CDATA[redirect_rule]]></wp:post_type>
            <wp:status><![CDATA[publish]]></wp:status>
            <wp:menu_order>50</wp:menu_order>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_redirect_rule_from]]></wp:meta_key>
                <wp:meta_value><![CDATA[/old-section/*]]></wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_redirect_rule_from_regex]]></wp:meta_key>
                <wp:meta_value><![CDATA[0]]></wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_redirect_rule_to]]></wp:meta_key>
                <wp:meta_value><![CDATA[/new-section/*]]></wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_redirect_rule_status_code]]></wp:meta_key>
                <wp:meta_value><![CDATA[301]]></wp:meta_value>
            </wp:postmeta>
        </item>
        XML;

    $xmlPath = tempnam(sys_get_temp_dir(), 'srm').'.xml';
    file_put_contents($xmlPath, wxrWithRedirectRule($item));

    try {
        $parser = new XmlParser;
        $parser->parse($xmlPath);

        (new RedirectRuleGlobalCreator)->createGlobals(
            $siteHandle,
            'redirects',
            $parser,
            new AssetImporter,
            new BufferedOutput,
            new MetadataMapper,
            new ContentProcessor,
        );
    } finally {
        @unlink($xmlPath);
    }

    /** @var Statamic\Globals\GlobalSet $global */
    $global = GlobalSet::findByHandle('redirects');
    /** @var Variables $vars */
    $vars = $global->in($siteHandle);
    /** @var list<array<string, mixed>> $rows */
    $rows = $vars->get('redirects', []);

    // The importer stores the wildcard verbatim, regex off — the shape UAMS-Web/uams-statamic-archive#1933's
    // engine work had to learn, rather than one the importer translates for it.
    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray([
            'source' => '/old-section/*',
            'use_regex' => false,
            'target_type' => 'url',
            'target_url' => '/new-section/*',
            'response' => '301',
            'type' => 'redirect',
            'enabled' => true,
        ]);

    // Serve the landed row through the real tag: the wildcard matches, and the
    // remainder is appended to the `*`-terminated target in its original case.
    $served = (string) Antlers::parse(
        '{{ find_redirect_rule in="'.$siteHandle.'" }}{{ target_url }}|{{ response }}{{ /find_redirect_rule }}',
        ['current_uri' => '/old-section/Deep/Page'],
    );

    expect(trim($served))->toBe('/new-section/Deep/Page|301');
});
