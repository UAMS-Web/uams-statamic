<?php

declare(strict_types=1);

/**
 * Generate the launch-sized proxy content tree for `statamic:search:update`
 * (UAMS-Web/uams-statamic#971, reused by #967). See README.md beside this file.
 *
 * Usage:
 *   php scripts/search-update-proxy/generate.php --corpus=<WordPress/ dir> --out=<scratch dir> [--limit=N]
 *
 * Reads the application's collections and `content/` read-only; writes only
 * under --out, which must not exist yet. Makes no network request.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Scripts\SearchUpdateProxy\SearchUpdateProxyGenerator;
use Statamic\Facades\Collection;

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';
require __DIR__.'/SearchUpdateProxyGenerator.php';

$options = getopt('', ['corpus:', 'out:', 'limit:']);
$corpus = is_string($options['corpus'] ?? null) ? $options['corpus'] : null;
$out = is_string($options['out'] ?? null) ? rtrim($options['out'], '/') : null;
$limit = is_string($options['limit'] ?? null) ? (int) $options['limit'] : null;

if ($corpus === null || $out === null) {
    fwrite(STDERR, "Usage: php scripts/search-update-proxy/generate.php --corpus=<WordPress/ dir> --out=<scratch dir> [--limit=N]\n");
    exit(2);
}

if (file_exists($out)) {
    fwrite(STDERR, "Refusing to write into {$out}: it already exists. Pass a new directory.\n");
    exit(2);
}

try {
    $app = require $root.'/bootstrap/app.php';

    if (! $app instanceof Application) {
        throw new RuntimeException('bootstrap/app.php did not return an application');
    }

    $app->make(Kernel::class)->bootstrap();

    $started = microtime(true);

    // Each generated collection's sites and dating, read from the application
    $collections = [];

    foreach (array_keys(SearchUpdateProxyGenerator::BLUEPRINTS) as $handle) {
        $collection = Collection::find($handle) ?? throw new RuntimeException("No {$handle} collection");
        $sites = [];
        $siteHandles = $collection->sites();

        foreach (is_iterable($siteHandles) ? $siteHandles : [] as $site) {
            if (is_string($site)) {
                $sites[] = $site;
            }
        }

        $collections[$handle] = [
            'sites' => $sites,
            'dated' => $collection->dated() === true,
        ];
    }

    $copied = SearchUpdateProxyGenerator::copyContent($root.'/content', $out.'/content', array_keys($collections));
    fwrite(STDOUT, sprintf("Copied %d files of content/ (generated collections' entries and trees left out)\n", $copied));

    $published = SearchUpdateProxyGenerator::selectPublished(SearchUpdateProxyGenerator::readCorpus($corpus));
    fwrite(STDOUT, sprintf("Read the corpus in %.1fs\n", microtime(true) - $started));

    $generator = new SearchUpdateProxyGenerator($collections);
    $counts = SearchUpdateProxyGenerator::write($out.'/content', $generator->plan($published, SearchUpdateProxyGenerator::LIVE_SITE_ROWS, $limit));

    $revision = static fn (string $directory): string => trim((string) shell_exec('git -C '.escapeshellarg($directory).' rev-parse HEAD 2>/dev/null'));

    $manifest = [
        'generated_at' => gmdate('c'),
        'seconds' => round(microtime(true) - $started, 1),
        'corpus' => ['path' => $corpus, 'revision' => $revision($corpus)],
        'application_revision' => $revision($root),
        'content_revision' => $revision($root.'/content'),
        'limit' => $limit,
        'entries' => $counts,
        'total' => array_sum(array_map(array_sum(...), $counts)),
    ];

    file_put_contents($out.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    fwrite(STDOUT, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
} catch (Throwable $throwable) {
    fwrite(STDERR, sprintf("%s: %s at %s:%d\n", $throwable::class, $throwable->getMessage(), $throwable->getFile(), $throwable->getLine()));
    exit(1);
}
