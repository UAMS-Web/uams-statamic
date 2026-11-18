<?php

declare(strict_types=1);

use Scripts\SearchUpdateProxy\SearchUpdateProxyGenerator;
use Symfony\Component\Yaml\Yaml;

require_once dirname(__DIR__, 3).'/scripts/search-update-proxy/SearchUpdateProxyGenerator.php';

/**
 * The launch-sized proxy generator for `statamic:search:update`
 * (UAMS-Web/uams-statamic#971): which corpus items it keeps, the shapes it
 * writes, and what it leaves out of the copied content tree. Fixtures live
 * under a per-run `sys_get_temp_dir()` path.
 *
 * @command  php artisan test --compact tests/Unit/Scripts/SearchUpdateProxyGeneratorTest.php
 */

/**
 * A WXR document for one site with the given items.
 *
 * @param  string  $base  The site's `wp:base_blog_url`
 * @param  list<array<string, string>>  $items  Each item's elements, by tag name
 */
function proxyWxr(string $base, array $items): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<rss xmlns:wp="http://wordpress.org/export/1.2/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/">'
        .'<channel><wp:base_blog_url>'.$base.'</wp:base_blog_url>';

    foreach ($items as $item) {
        $xml .= '<item>';

        foreach ($item as $name => $value) {
            $xml .= '<'.$name.'><![CDATA['.$value.']]></'.$name.'>';
        }

        $xml .= '</item>';
    }

    return $xml.'</channel></rss>';
}

/**
 * One corpus item with defaults for every field.
 *
 * @param  array{file?: string, base?: string, post_type?: string, status?: string, password?: string, id?: string, slug?: string, date?: string, title?: string, content?: string, excerpt?: string}  $overrides  Fields to set
 * @return array{file: string, base: string, post_type: string, status: string, password: string, id: string, slug: string, date: string, title: string, content: string, excerpt: string}
 */
function proxyItem(array $overrides = []): array
{
    return array_merge([
        'file' => 'export.xml',
        'base' => 'https://a.test',
        'post_type' => 'page',
        'status' => 'publish',
        'password' => '',
        'id' => '1',
        'slug' => 'about',
        'date' => '2021-03-04 05:06:07',
        'title' => 'About',
        'content' => '<p>First.</p><p>Second.</p>',
        'excerpt' => '',
    ], $overrides);
}

/**
 * A generator over the collections the proxy writes, each on two sites.
 */
function proxyGenerator(): SearchUpdateProxyGenerator
{
    $collections = [];

    foreach (array_keys(SearchUpdateProxyGenerator::BLUEPRINTS) as $handle) {
        $collections[$handle] = [
            'sites' => str_starts_with($handle, 'clinical_') ? ['health'] : ['default', 'health'],
            'dated' => in_array($handle, ['posts', 'knowledge_bases', 'clinical_resources'], true),
        ];
    }

    return new SearchUpdateProxyGenerator($collections);
}

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/search-update-proxy-'.bin2hex(random_bytes(6));
    mkdir($this->directory);
});

afterEach(function (): void {
    exec('rm -rf '.escapeshellarg($this->directory));
});

it('reads every item with its site, stripping the control characters XML forbids', function (): void {
    $xml = proxyWxr('https://a.test', [
        ['wp:post_type' => 'page', 'wp:status' => 'publish', 'wp:post_id' => '7', 'wp:post_name' => 'about', 'title' => "Ab\x00out", 'content:encoded' => '<p>Body</p>'],
        ['wp:post_type' => 'post', 'wp:status' => 'draft', 'wp:post_id' => '8', 'title' => 'Later'],
    ]);
    file_put_contents($this->directory.'/b.xml', $xml);
    file_put_contents($this->directory.'/a.xml', proxyWxr('https://b.test', [['wp:post_type' => 'kb', 'title' => 'First file']]));

    $items = iterator_to_array(SearchUpdateProxyGenerator::readCorpus($this->directory), false);

    expect(array_column($items, 'title'))->toBe(['First file', 'About', 'Later'])
        ->and(array_column($items, 'base'))->toBe(['https://b.test', 'https://a.test', 'https://a.test'])
        ->and($items[1])->toMatchArray(['file' => 'b.xml', 'post_type' => 'page', 'status' => 'publish', 'id' => '7', 'slug' => 'about', 'content' => '<p>Body</p>', 'password' => '']);
});

it('refuses a directory with no exports', function (): void {
    iterator_to_array(SearchUpdateProxyGenerator::readCorpus($this->directory));
})->throws(RuntimeException::class, 'No WXR files');

it('keeps published, unprotected items of the mapped post types, once each', function (): void {
    $selected = SearchUpdateProxyGenerator::selectPublished([
        proxyItem(['id' => '1']),
        proxyItem(['id' => '1']),
        proxyItem(['id' => '1', 'base' => 'https://b.test']),
        proxyItem(['id' => '2', 'status' => 'draft']),
        proxyItem(['id' => '3', 'password' => 'secret']),
        proxyItem(['id' => '4', 'post_type' => 'attachment']),
        proxyItem(['id' => '5', 'post_type' => 'tshowcase']),
        proxyItem(['id' => '6', 'post_type' => 'advert']),
    ]);

    expect(array_map(fn (array $items): array => array_map(fn (array $item): string => $item['base'].'#'.$item['id'], $items), $selected))->toBe([
        'pages' => ['https://a.test#1', 'https://b.test#1'],
        'people' => ['https://a.test#5'],
        'classifieds' => ['https://a.test#6'],
    ]);
});

it('splits content into plain paragraphs without shortcodes, tags or entities', function (): void {
    expect(SearchUpdateProxyGenerator::paragraphs('[caption id="x"]<img src="a.png">[/caption]<h2>Heading</h2><p>One &amp; <b>two</b></p>line<br>break<ul><li>Item</li></ul>'))
        ->toBe(['Heading', 'One & two', 'line', 'break', 'Item'])
        ->and(SearchUpdateProxyGenerator::paragraphs('   '))->toBe([]);
});

it('builds a Bard paragraph node per paragraph', function (): void {
    expect(SearchUpdateProxyGenerator::bard(['One', 'Two']))->toBe([
        ['type' => 'paragraph', 'attrs' => ['id' => null], 'content' => [['type' => 'text', 'text' => 'One']]],
        ['type' => 'paragraph', 'attrs' => ['id' => null], 'content' => [['type' => 'text', 'text' => 'Two']]],
    ]);
});

it('builds a person name from a display title, credentials dropped', function (string $title, string $first, string $middle, string $last): void {
    $name = SearchUpdateProxyGenerator::personName($title);

    expect($name['first_name'])->toBe(['text' => $first, 'phonetic_text' => null])
        ->and($name['middle_name'])->toBe(['text' => $middle, 'phonetic_text' => null])
        ->and($name['last_name'])->toBe(['text' => $last, 'phonetic_text' => null])
        ->and($name['no_middle_name'])->toBe($middle === '');
})->with([
    'credentials' => ['Jane Q. Doe, MD, PhD', 'Jane', 'Q.', 'Doe'],
    'two names' => ['Jane Doe', 'Jane', '', 'Doe'],
    'one name' => ['Cher', 'Cher', '', ''],
    'two middle names' => ['Ann Mary Lee Smith', 'Ann', 'Mary Lee', 'Smith'],
]);

it('plans each corpus item in its blueprint shape, on one site per WordPress site', function (): void {
    $published = [
        'pages' => [proxyItem(['id' => '1']), proxyItem(['id' => '2']), proxyItem(['id' => '3', 'base' => 'https://b.test'])],
        'posts' => [proxyItem(['post_type' => 'post', 'id' => '4', 'slug' => 'news', 'excerpt' => '<p>Short</p>'])],
        'people' => [proxyItem(['post_type' => 'uamspeople', 'id' => '5', 'slug' => '', 'title' => 'Jane Doe, MD', 'content' => ''])],
    ];

    $entries = proxyGenerator()->plan($published, []);

    expect(array_column($entries, 'path'))->toBe([
        'pages/'.$entries[0]['site'].'/about.md',
        'pages/'.$entries[0]['site'].'/about-2.md',
        'pages/'.$entries[2]['site'].'/about'.($entries[2]['site'] === $entries[0]['site'] ? '-3' : '').'.md',
        'posts/'.$entries[3]['site'].'/2021-03-04.news.md',
        'people/'.$entries[4]['site'].'/jane-doe-md.md',
    ])
        ->and($entries[0]['site'])->toBe($entries[1]['site'])
        ->and(array_column($entries, 'source'))->each->toBe('corpus')
        ->and($entries[0]['data'])->toMatchArray(['blueprint' => 'basic_page', 'title' => 'About', 'content' => SearchUpdateProxyGenerator::bard(['First.', 'Second.'])])
        ->and($entries[0]['data']['id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/')
        ->and($entries[0]['data']['id'])->not->toBe($entries[1]['data']['id'])
        ->and($entries[3]['data'])->toMatchArray(['blueprint' => 'post', 'excerpt' => 'Short'])
        ->and($entries[4]['data'])->toBe([
            'id' => $entries[4]['data']['id'],
            'blueprint' => 'person',
            'title' => 'Jane Doe, MD',
            'person_name' => SearchUpdateProxyGenerator::personName('Jane Doe, MD'),
        ]);
});

it('fills the live-site rows from the corpus text, providers from the people names', function (): void {
    $published = [
        'pages' => [proxyItem(['id' => '1', 'title' => 'Page one']), proxyItem(['id' => '2', 'title' => 'Page two'])],
        'people' => [proxyItem(['post_type' => 'uamspeople', 'id' => '3', 'title' => 'Jane Doe'])],
    ];

    $entries = proxyGenerator()->plan($published, ['clinical_resources' => 3, 'clinical_providers' => 1, 'pages' => 1]);
    $live = array_values(array_filter($entries, fn (array $entry): bool => $entry['source'] === 'live-site proxy'));

    $ids = array_filter(array_column(array_column($live, 'data'), 'id'), is_string(...));

    expect(array_map(fn (array $entry): string => $entry['collection'].'@'.$entry['site'], $live))->toBe([
        'clinical_resources@health',
        'clinical_resources@health',
        'clinical_resources@health',
        'clinical_providers@health',
        'pages@health',
    ])
        ->and(array_column(array_column($live, 'data'), 'title'))->toBe(['Page one', 'Page two', 'Page one', 'Jane Doe', 'Page one'])
        ->and($live[0]['data'])->toHaveKey('article')
        ->and($live[0]['data']['blueprint'])->toBe('article')
        ->and(array_unique($ids))->toHaveCount(5)
        ->and(array_column($live, 'path'))->toContain('clinical_resources/health/2021-03-04.page-one-2.md');
});

it('stops each collection at the limit', function (): void {
    $published = ['pages' => [proxyItem(['id' => '1']), proxyItem(['id' => '2']), proxyItem(['id' => '3'])]];

    $entries = proxyGenerator()->plan($published, ['pages' => 5, 'clinical_expertise' => 5], 2);

    expect(array_count_values(array_column($entries, 'collection')))->toBe(['pages' => 2, 'clinical_expertise' => 2]);
});

it('writes each entry as front matter and counts it by source', function (): void {
    $entries = proxyGenerator()->plan(['pages' => [proxyItem()]], ['clinical_locations' => 1]);

    $counts = SearchUpdateProxyGenerator::write($this->directory, $entries);
    $file = (string) file_get_contents($this->directory.'/collections/'.$entries[0]['path']);

    expect($counts)->toBe(['clinical_locations' => ['live-site proxy' => 1], 'pages' => ['corpus' => 1]])
        ->and($file)->toStartWith("---\n")->toEndWith("---\n")
        ->and(Yaml::parse(substr($file, 4, -4)))->toBe($entries[0]['data']);
});

it('copies the content tree without the generated collections entries, trees or git link', function (): void {
    $source = $this->directory.'/content';

    foreach ([
        '.git',
        'collections/pages.yaml',
        'collections/pages/default/about.md',
        'collections/facilities.yaml',
        'collections/facilities/default/main.md',
        'trees/collections/default/pages.yaml',
        'trees/collections/default/facilities.yaml',
        'taxonomies/tags.yaml',
    ] as $path) {
        if (! is_dir(dirname($source.'/'.$path))) {
            mkdir(dirname($source.'/'.$path), 0777, true);
        }

        file_put_contents($source.'/'.$path, $path);
    }

    $copied = SearchUpdateProxyGenerator::copyContent($source, $this->directory.'/copy', ['pages']);

    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory.'/copy', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo) {
            $files[] = substr($file->getPathname(), strlen($this->directory.'/copy/'));
        }
    }

    sort($files);

    expect($copied)->toBe(5)
        ->and($files)->toBe([
            'collections/facilities.yaml',
            'collections/facilities/default/main.md',
            'collections/pages.yaml',
            'taxonomies/tags.yaml',
            'trees/collections/default/facilities.yaml',
        ]);
});
