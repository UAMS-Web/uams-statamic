<?php

declare(strict_types=1);

namespace Scripts\SearchUpdateProxy;

use DOMDocument;
use DOMElement;
use FilesystemIterator;
use php_user_filter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use XMLReader;

/**
 * Builds a launch-sized content tree for timing `statamic:search:update`
 * without the migrated content (UAMS-Web/uams-statamic#971, reused by #967).
 *
 * The reader-facing items are the corpus's own: every published, unprotected
 * WXR item whose post type maps to a collection, de-duplicated on collection,
 * site, slug and WordPress post id as #511 counted them, keeping its real title
 * and text. The rows #511 read from the live site, which the corpus does not
 * hold, are filled from that same text, cycled. Each entry carries the fields
 * the search indexes read, in its blueprint's own shapes, and nothing else, so
 * the tree is a proxy for the migrated content and is labelled as one.
 */
final class SearchUpdateProxyGenerator
{
    /**
     * WordPress post type to collection, as `ImportWordPress::getDefaultMappings()`
     * maps them and #511 counted them.
     *
     * @var array<string, string>
     */
    public const array POST_TYPES = [
        'page' => 'pages',
        'post' => 'posts',
        'uamspeople' => 'people',
        'tshowcase' => 'people',
        'kb' => 'knowledge_bases',
        'advert' => 'classifieds',
    ];

    /**
     * The blueprint each generated entry uses, per collection.
     *
     * @var array<string, string>
     */
    public const array BLUEPRINTS = [
        'pages' => 'basic_page',
        'posts' => 'post',
        'people' => 'person',
        'knowledge_bases' => 'article',
        'classifieds' => 'classified',
        'clinical_providers' => 'provider',
        'clinical_resources' => 'article',
        'clinical_locations' => 'location',
        'clinical_expertise' => 'clinical_expertise',
    ];

    /**
     * The rows #511 read from the live `uamshealth.com` REST API, which the
     * corpus does not hold, per collection.
     *
     * @var array<string, int>
     */
    public const array LIVE_SITE_ROWS = [
        'pages' => 293,
        'posts' => 124,
        'clinical_providers' => 1048,
        'clinical_resources' => 516,
        'clinical_locations' => 171,
        'clinical_expertise' => 91,
    ];

    /**
     * The site the live-site rows belong to, for a collection served on many.
     */
    public const string LIVE_SITE = 'health';

    /**
     * @param  array<string, array{sites: list<string>, dated: bool}>  $collections  Each generated collection's sites and whether it is dated
     */
    public function __construct(private readonly array $collections) {}

    /**
     * Read every item of every WXR file in a directory, in file-name order.
     *
     * @param  string  $directory  The directory holding the `*.xml` exports
     * @return iterable<int, array{file: string, base: string, post_type: string, status: string, password: string, id: string, slug: string, date: string, title: string, content: string, excerpt: string}>
     */
    public static function readCorpus(string $directory): iterable
    {
        $files = glob(rtrim($directory, '/').'/*.xml');

        if ($files === false || $files === []) {
            throw new RuntimeException(sprintf('No WXR files under %s', $directory));
        }

        sort($files);

        foreach ($files as $file) {
            yield from self::readFile($file);
        }
    }

    /**
     * Read the items of one WXR file.
     *
     * @param  string  $file  The export file
     * @return iterable<int, array{file: string, base: string, post_type: string, status: string, password: string, id: string, slug: string, date: string, title: string, content: string, excerpt: string}>
     */
    public static function readFile(string $file): iterable
    {
        $reader = new XMLReader;

        // Some exports carry control characters XML forbids (NUL among them); strip them as they stream
        if (! in_array(XmlControlCharacterFilter::NAME, stream_get_filters(), true)) {
            stream_filter_register(XmlControlCharacterFilter::NAME, XmlControlCharacterFilter::class);
        }

        if (! $reader->open('php://filter/read='.XmlControlCharacterFilter::NAME.'/resource='.$file, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new RuntimeException(sprintf('Cannot open %s', $file));
        }

        $base = '';
        $document = new DOMDocument;

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            // The site the items belong to, read once from the channel
            if ($reader->name === 'wp:base_blog_url') {
                $base = trim($reader->readString());

                continue;
            }

            if ($reader->name !== 'item') {
                continue;
            }

            $node = $reader->expand($document);

            if (! $node instanceof DOMElement) {
                continue;
            }

            yield [
                'file' => basename($file),
                'base' => $base,
                'post_type' => self::child($node, 'wp:post_type'),
                'status' => self::child($node, 'wp:status'),
                'password' => self::child($node, 'wp:post_password'),
                'id' => self::child($node, 'wp:post_id'),
                'slug' => self::child($node, 'wp:post_name'),
                'date' => self::child($node, 'wp:post_date'),
                'title' => self::child($node, 'title'),
                'content' => self::child($node, 'content:encoded'),
                'excerpt' => self::child($node, 'excerpt:encoded'),
            ];
        }

        $reader->close();
    }

    /**
     * Keep the published, unprotected items of the mapped post types, once each.
     *
     * @param  iterable<int, array{file: string, base: string, post_type: string, status: string, password: string, id: string, slug: string, date: string, title: string, content: string, excerpt: string}>  $items  The corpus items
     * @return array<string, list<array{file: string, base: string, post_type: string, status: string, password: string, id: string, slug: string, date: string, title: string, content: string, excerpt: string}>> Collection to its items, in corpus order
     */
    public static function selectPublished(iterable $items): array
    {
        $selected = [];
        $seen = [];

        foreach ($items as $item) {
            $collection = self::POST_TYPES[$item['post_type']] ?? null;

            // Skip a post type no collection takes, and anything not public
            if ($collection === null || $item['status'] !== 'publish' || $item['password'] !== '') {
                continue;
            }

            // The same entry, as the importer finds an existing one (#511)
            $key = implode("\0", [$collection, $item['base'], $item['slug'], $item['id']]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $selected[$collection][] = $item;
        }

        return $selected;
    }

    /**
     * Split WordPress post content into plain-text paragraphs.
     *
     * Shortcodes and tags are stripped, as #511's rows were.
     *
     * @param  string  $html  The post content
     * @return list<string>
     */
    public static function paragraphs(string $html): array
    {
        // Drop shortcodes, then break at block boundaries and line breaks
        $text = (string) preg_replace('/\[\/?[A-Za-z][^\]]*\]/', ' ', $html);
        $text = (string) preg_replace('/<\s*\/?(br|p|h[1-6]|li|ul|ol|div|blockquote|tr|td|th|table)\b[^>]*>/i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $paragraphs = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim((string) preg_replace('/\s+/u', ' ', $line));

            if ($line !== '') {
                $paragraphs[] = $line;
            }
        }

        return $paragraphs;
    }

    /**
     * A Bard value of one paragraph node per paragraph.
     *
     * @param  list<string>  $paragraphs  The paragraphs
     * @return list<array{type: string, attrs: array{id: null}, content: list<array{type: string, text: string}>}>
     */
    public static function bard(array $paragraphs): array
    {
        return array_map(
            static fn (string $paragraph): array => [
                'type' => 'paragraph',
                'attrs' => ['id' => null],
                'content' => [['type' => 'text', 'text' => $paragraph]],
            ],
            $paragraphs
        );
    }

    /**
     * A `person_name` value from a display title such as `Jane Q. Doe, MD`.
     *
     * @param  string  $title  The person's title
     * @return array<string, mixed>
     */
    public static function personName(string $title): array
    {
        // The name is what precedes the first comma; credentials follow it
        $parts = preg_split('/\s+/u', trim(explode(',', $title)[0])) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        $first = $parts[0] ?? $title;
        $last = count($parts) > 1 ? end($parts) : '';
        $middle = implode(' ', array_slice($parts, 1, max(0, count($parts) - 2)));

        return [
            'first_name' => ['text' => $first, 'phonetic_text' => null],
            'no_middle_name' => $middle === '',
            'middle_name' => ['text' => $middle, 'phonetic_text' => null],
            'no_nickname' => true,
            'nickname' => ['text' => null, 'phonetic_text' => null],
            'last_name' => ['text' => $last, 'phonetic_text' => null],
            'no_generational_suffix' => true,
            'generational_suffix' => null,
            'display_format' => null,
        ];
    }

    /**
     * Plan every entry: the corpus items, then the live-site rows.
     *
     * @param  array<string, list<array{file: string, base: string, post_type: string, status: string, password: string, id: string, slug: string, date: string, title: string, content: string, excerpt: string}>>  $published  {@see self::selectPublished()}
     * @param  array<string, int>  $liveRows  Live-site rows per collection, {@see self::LIVE_SITE_ROWS}
     * @param  int|null  $limit  At most this many entries per collection, for a small run
     * @return list<array{collection: string, site: string, path: string, data: array<string, mixed>, source: string}>
     */
    public function plan(array $published, array $liveRows = self::LIVE_SITE_ROWS, ?int $limit = null): array
    {
        $entries = [];
        $slugs = [];
        $counts = [];

        foreach ($published as $collection => $items) {
            foreach ($items as $item) {
                if ($limit !== null && ($counts[$collection] ?? 0) >= $limit) {
                    break;
                }

                $site = $this->siteFor($collection, $item['base']);
                $entries[] = $this->entry($collection, $site, $item, 'corpus', $slugs);
                $counts[$collection] = ($counts[$collection] ?? 0) + 1;
            }
        }

        // The live-site rows reuse the corpus text, cycled from the pages
        $pool = $published['pages'] ?? array_merge(...array_values($published));
        $names = $published['people'] ?? $pool;

        if ($pool === []) {
            return $entries;
        }

        $cursor = 0;

        foreach ($liveRows as $collection => $rows) {
            for ($row = 0; $row < $rows; $row++) {
                if ($limit !== null && ($counts[$collection] ?? 0) >= $limit) {
                    break;
                }

                $source = $collection === 'clinical_providers' ? $names : $pool;
                $item = $source[$cursor % count($source)];
                $item['id'] = 'live-'.$collection.'-'.$row;
                $item['slug'] = '';
                $cursor++;

                $site = $this->liveSiteFor($collection);
                $entries[] = $this->entry($collection, $site, $item, 'live-site proxy', $slugs);
                $counts[$collection] = ($counts[$collection] ?? 0) + 1;
            }
        }

        return $entries;
    }

    /**
     * Write planned entries under a content root.
     *
     * @param  string  $root  The content root
     * @param  list<array{collection: string, site: string, path: string, data: array<string, mixed>, source: string}>  $entries  {@see self::plan()}
     * @return array<string, array<string, int>> Collection to source to the count written
     */
    public static function write(string $root, array $entries): array
    {
        $counts = [];

        foreach ($entries as $entry) {
            $path = rtrim($root, '/').'/collections/'.$entry['path'];
            $directory = dirname($path);

            if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
                throw new RuntimeException(sprintf('Cannot create %s', $directory));
            }

            $yaml = Yaml::dump($entry['data'], 99, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

            if (file_put_contents($path, "---\n".$yaml."---\n") === false) {
                throw new RuntimeException(sprintf('Cannot write %s', $path));
            }

            $counts[$entry['collection']][$entry['source']] = ($counts[$entry['collection']][$entry['source']] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * Copy a content tree, leaving out the generated collections' entries and trees.
     *
     * The submodule's `.git` file is left out too, so the copy is not mistaken
     * for a checkout.
     *
     * @param  string  $source  The content root to copy, `content/`
     * @param  string  $target  The scratch content root
     * @param  list<string>  $collections  The collections whose entries and trees are generated instead
     * @return int The number of files copied
     */
    public static function copyContent(string $source, string $target, array $collections): int
    {
        $source = rtrim($source, '/');
        $copied = 0;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $relative = substr($file->getPathname(), strlen($source) + 1);

            if (self::excluded($relative, $collections) || $file->isDir()) {
                continue;
            }

            $destination = rtrim($target, '/').'/'.$relative;
            $directory = dirname($destination);

            if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
                throw new RuntimeException(sprintf('Cannot create %s', $directory));
            }

            if (! copy($file->getPathname(), $destination)) {
                throw new RuntimeException(sprintf('Cannot copy %s', $relative));
            }

            $copied++;
        }

        return $copied;
    }

    /**
     * Whether a path under the content root is left out of the copy.
     *
     * @param  string  $relative  The path, relative to the content root
     * @param  list<string>  $collections  The generated collections
     */
    public static function excluded(string $relative, array $collections): bool
    {
        $segments = explode('/', $relative);

        // The submodule's git link
        if ($segments[0] === '.git') {
            return true;
        }

        // A generated collection's entries: collections/<handle>/...
        if ($segments[0] === 'collections' && count($segments) > 2 && in_array($segments[1], $collections, true)) {
            return true;
        }

        // A generated collection's tree: trees/collections/<site>/<handle>.yaml
        return $segments[0] === 'trees'
            && ($segments[1] ?? '') === 'collections'
            && count($segments) === 4
            && in_array(basename($segments[3], '.yaml'), $collections, true);
    }

    /**
     * One planned entry.
     *
     * @param  string  $collection  The collection
     * @param  string  $site  The site
     * @param  array{file: string, base: string, post_type: string, status: string, password: string, id: string, slug: string, date: string, title: string, content: string, excerpt: string}  $item  The source item
     * @param  string  $source  `corpus` or `live-site proxy`
     * @param  array<string, true>  $slugs  The slugs taken so far, per collection and site
     * @return array{collection: string, site: string, path: string, data: array<string, mixed>, source: string}
     */
    private function entry(string $collection, string $site, array $item, string $source, array &$slugs): array
    {
        $title = trim(html_entity_decode($item['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = $title !== '' ? $title : 'Untitled '.$item['id'];

        $slug = $this->uniqueSlug($collection, $site, $item['slug'] !== '' ? $item['slug'] : self::slugify($title), $slugs);

        $data = [
            'id' => self::uuid($collection."\0".$item['base']."\0".$item['slug']."\0".$item['id']."\0".$source),
            'blueprint' => self::BLUEPRINTS[$collection] ?? 'default',
            'title' => $title,
        ];

        $paragraphs = self::paragraphs($item['content']);
        $excerpt = implode(' ', self::paragraphs($item['excerpt']));

        // The fields each collection's indexes read, in its blueprint's shapes
        $data += match ($collection) {
            'pages' => $paragraphs === [] ? [] : ['content' => self::bard($paragraphs)],
            'posts', 'knowledge_bases' => array_filter([
                'content' => $paragraphs === [] ? null : self::bard($paragraphs),
                'excerpt' => $excerpt === '' ? null : $excerpt,
            ]),
            'clinical_resources' => array_filter([
                'article' => $paragraphs === [] ? null : self::bard($paragraphs),
                'excerpt' => $excerpt === '' ? null : $excerpt,
            ]),
            'people' => ['person_name' => self::personName($title)],
            default => [],
        };

        $file = $slug.'.md';

        // A dated collection carries the date in the file name
        if ($this->collections[$collection]['dated'] ?? false) {
            $date = preg_match('/^\d{4}-\d{2}-\d{2}/', $item['date'], $match) === 1 && $match[0] !== '0000-00-00' ? $match[0] : '2020-01-01';
            $file = $date.'.'.$file;
        }

        return [
            'collection' => $collection,
            'site' => $site,
            'path' => $collection.'/'.$site.'/'.$file,
            'data' => $data,
            'source' => $source,
        ];
    }

    /**
     * The site a corpus item lands on: the same one for every item of a WordPress site.
     *
     * @param  string  $collection  The collection
     * @param  string  $base  The item's `wp:base_blog_url`
     */
    private function siteFor(string $collection, string $base): string
    {
        $sites = $this->sites($collection);

        return $sites[crc32($base) % count($sites)];
    }

    /**
     * The site a live-site row lands on.
     *
     * @param  string  $collection  The collection
     */
    private function liveSiteFor(string $collection): string
    {
        $sites = $this->sites($collection);

        return in_array(self::LIVE_SITE, $sites, true) ? self::LIVE_SITE : $sites[0];
    }

    /**
     * A collection's sites.
     *
     * @param  string  $collection  The collection
     * @return non-empty-list<string>
     */
    private function sites(string $collection): array
    {
        $sites = $this->collections[$collection]['sites'] ?? [];

        if ($sites === []) {
            throw new RuntimeException(sprintf('No sites given for the %s collection', $collection));
        }

        return $sites;
    }

    /**
     * A slug not yet taken in its collection and site.
     *
     * @param  string  $collection  The collection
     * @param  string  $site  The site
     * @param  string  $slug  The wanted slug
     * @param  array<string, true>  $slugs  The slugs taken so far
     */
    private function uniqueSlug(string $collection, string $site, string $slug, array &$slugs): string
    {
        $slug = self::slugify($slug);
        $slug = $slug !== '' ? $slug : 'item';

        $candidate = $slug;

        for ($suffix = 2; isset($slugs[$collection.'/'.$site.'/'.$candidate]); $suffix++) {
            $candidate = $slug.'-'.$suffix;
        }

        $slugs[$collection.'/'.$site.'/'.$candidate] = true;

        return $candidate;
    }

    /**
     * A file-safe slug: lowercase ASCII letters, digits and hyphens.
     *
     * @param  string  $value  The text to slug
     */
    private static function slugify(string $value): string
    {
        $value = strtolower(rawurldecode($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);

        return substr(trim($value, '-'), 0, 120);
    }

    /**
     * A stable UUID-shaped id from a key.
     *
     * @param  string  $key  The key
     */
    private static function uuid(string $key): string
    {
        $hash = md5($key);

        return sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
    }

    /**
     * The text of an item's first child with a tag name, or an empty string.
     *
     * @param  DOMElement  $item  The item
     * @param  string  $name  The qualified tag name
     */
    private static function child(DOMElement $item, string $name): string
    {
        foreach ($item->childNodes as $child) {
            if ($child instanceof DOMElement && $child->nodeName === $name) {
                return $child->textContent;
            }
        }

        return '';
    }
}

/**
 * A read filter that drops the C0 control characters XML 1.0 forbids, keeping
 * tab, line feed and carriage return.
 */
final class XmlControlCharacterFilter extends php_user_filter
{
    // cspell:ignore datalen PSFS

    public const string NAME = 'search-update-proxy.xml-control-characters';

    /**
     * @param  resource  $in  The incoming bucket brigade
     * @param  resource  $out  The outgoing bucket brigade
     * @param  int  $consumed  The bytes consumed
     * @param  bool  $closing  Whether the stream is closing
     */
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $consumed += $bucket->datalen;
            $bucket->data = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $bucket->data);
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
