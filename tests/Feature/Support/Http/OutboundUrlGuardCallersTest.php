<?php

declare(strict_types=1);

use App\Rules\FirstPartyUrl;
use App\Support\Http\OutboundUrlGuard;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The guard's private-network host exception reaches the link checker and nothing else (UAMS-Web/uams-statamic-archive#1768).
 *
 * `OutboundUrlGuard::fetch()`, `pinnedCurlOptions()` and `resolveMapFor()` take an optional list
 * of hosts that may resolve to private space. The exception is safe only while exactly one
 * caller passes that list, so this parses every file under the application's code directories
 * that names the guard, and fails the moment a second caller passes one — positionally, by name,
 * or through a spread — or calls the guard in a shape this check cannot see into.
 *
 * The classifier is proven on synthetic code carrying each evasion before its verdict on the
 * real tree is trusted: an instrument that cannot see a named argument would report the tree
 * clean whether or not one is there.
 *
 * @command  php artisan test --compact tests/Feature/Support/Http/OutboundUrlGuardCallersTest.php
 */
uses(TestCase::class);

/** The directories whose code could call the guard. `tests/` and `vendor/` are not the app. */
const OUTBOUND_GUARD_SCANNED_DIRECTORIES = ['app', 'bootstrap', 'config', 'database', 'routes'];

/**
 * Classify every reference to the guard in one PHP source.
 *
 * - `sites`: static calls to a list-taking method, by name, through the guard's class name.
 * - `passing`: those among them that pass a list — by position, as the named argument
 *   `privateNetworkHosts`, or possibly through a `...` spread.
 * - `opaque`: references this check cannot see into — a dynamic, `self`, `static` or `parent`
 *   class, a dynamic method name, a first-class callable, or `OutboundUrlGuard::class` used as a
 *   value (a callable array, `call_user_func`). Any of these could pass a list unseen.
 *
 * @return array{sites: int, passing: int, opaque: int}
 */
function outboundGuardClassify(string $code): array
{
    $ast = (new ParserFactory)->createForHostVersion()->parse($code) ?? [];
    $ast = (new NodeTraverser(new NameResolver))->traverse($ast);
    $finder = new NodeFinder;

    $listPosition = ['fetch' => 5, 'pinnedCurlOptions' => 2, 'resolveMapFor' => 2];
    $result = ['sites' => 0, 'passing' => 0, 'opaque' => 0];

    foreach ($finder->findInstanceOf($ast, StaticCall::class) as $call) {
        $class = $call->class instanceof Node\Name ? $call->class->toString() : null;

        // A class this check cannot resolve to a name — `$c::fetch()`, `self::`, `static::`,
        // `parent::` — may be the guard, so it cannot be cleared.
        if ($class === null || in_array(strtolower($class), ['self', 'static', 'parent'], true)) {
            $result['opaque']++;

            continue;
        }

        if ($class !== OutboundUrlGuard::class) {
            continue;
        }

        if (! $call->name instanceof Node\Identifier || $call->isFirstClassCallable()) {
            $result['opaque']++;

            continue;
        }

        $method = $call->name->toString();

        if (! array_key_exists($method, $listPosition)) {
            continue;
        }

        $result['sites']++;
        $args = $call->getArgs();

        $passes = count($args) >= $listPosition[$method]
            || array_any($args, static fn (Node\Arg $arg): bool => $arg->unpack
                || ($arg->name instanceof Node\Identifier && $arg->name->toString() === 'privateNetworkHosts'));

        if ($passes) {
            $result['passing']++;
        }
    }

    // `OutboundUrlGuard::class` as a value hands the class to code that can call it dynamically.
    foreach ($finder->findInstanceOf($ast, ClassConstFetch::class) as $fetch) {
        if ($fetch->class instanceof Node\Name
            && $fetch->class->toString() === OutboundUrlGuard::class
            && $fetch->name instanceof Node\Identifier
            && strtolower($fetch->name->toString()) === 'class') {
            $result['opaque']++;
        }
    }

    return $result;
}

/**
 * Classify every file in the application that names the guard, keyed by repository-relative path.
 * The guard's own file is excluded: it passes the list between its own methods by design.
 *
 * @return array<string, array{sites: int, passing: int, opaque: int}>
 */
function outboundGuardTreeReport(): array
{
    /** @var array<string, array{sites: int, passing: int, opaque: int}>|null $cached */
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $report = [];

    $directories = array_map(base_path(...), OUTBOUND_GUARD_SCANNED_DIRECTORIES);

    // Only files that name the guard can call it. Parsing everything would include the generated
    // enums under `app/`, which exhaust the memory limit without being able to contain a call.
    foreach (Finder::create()->files()->in($directories)->name('*.php')->contains('OutboundUrlGuard') as $file) {
        $relative = str_replace('\\', '/', substr((string) $file->getRealPath(), strlen(base_path()) + 1));

        if ($relative === 'app/Support/Http/OutboundUrlGuard.php') {
            continue;
        }

        $report[$relative] = outboundGuardClassify($file->getContents());
    }

    return $cached = $report;
}

it('detects every way of passing the list, and every shape it cannot see into', function (string $call, string $counted) {
    // The negative controls. Each snippet carries one evasion; the classifier must count it.
    $code = sprintf("<?php\nuse App\\Support\\Http\\OutboundUrlGuard;\nfunction probe(\$u, \$args, \$c, \$m) {\n    %s;\n}\n", $call);

    expect(outboundGuardClassify($code)[$counted])->toBe(1);
})->with([
    'the fifth positional argument' => ["OutboundUrlGuard::fetch('GET', \$u, null, 5, ['h'])", 'passing'],
    'a named argument' => ["OutboundUrlGuard::fetch('GET', \$u, privateNetworkHosts: ['h'])", 'passing'],
    'a spread' => ['OutboundUrlGuard::fetch(...$args)', 'passing'],
    'resolveMapFor positionally' => ["OutboundUrlGuard::resolveMapFor(\$u, ['h'])", 'passing'],
    'pinnedCurlOptions by name' => ["OutboundUrlGuard::pinnedCurlOptions(\$u, privateNetworkHosts: ['h'])", 'passing'],
    'a fully qualified name' => ["\\App\\Support\\Http\\OutboundUrlGuard::fetch('GET', \$u, null, 5, ['h'])", 'passing'],
    'a dynamic class' => ["\$c::fetch('GET', \$u, null, 5, ['h'])", 'opaque'],
    'self' => ["self::fetch('GET', \$u, null, 5, ['h'])", 'opaque'],
    'parent' => ["parent::fetch('GET', \$u, null, 5, ['h'])", 'opaque'],
    'a dynamic method name' => ['OutboundUrlGuard::{$m}($u)', 'opaque'],
    'a first-class callable' => ['$f = OutboundUrlGuard::fetch(...)', 'opaque'],
    'the class as a callable array' => ["call_user_func([OutboundUrlGuard::class, 'fetch'], 'GET', \$u)", 'opaque'],
]);

it('does not count a call that passes no list', function () {
    $code = "<?php\nuse App\\Support\\Http\\OutboundUrlGuard;\nOutboundUrlGuard::fetch('GET', \$u, null, 5);\nOutboundUrlGuard::isAllowedUrl(\$u);\n";

    expect(outboundGuardClassify($code))->toBe(['sites' => 1, 'passing' => 0, 'opaque' => 0]);
});

it('finds the guard call sites it is meant to police', function () {
    // The positive control on the real tree: an instrument that found none would pass vacuously.
    $report = outboundGuardTreeReport();

    expect(array_sum(array_column($report, 'sites')))->toBeGreaterThanOrEqual(7)
        ->and($report['app/Services/LinkHealth/ExternalProber.php']['sites'] ?? 0)->toBe(1);
});

it('lets only the link checker pass a private-network host list to the guard', function () {
    $passing = array_keys(array_filter(outboundGuardTreeReport(), static fn (array $file): bool => $file['passing'] > 0));

    expect($passing)->toBe(['app/Services/LinkHealth/ExternalProber.php'])
        ->and(outboundGuardTreeReport()['app/Services/LinkHealth/ExternalProber.php']['passing'])->toBe(1);
});

it('finds no reference to the guard it cannot see into', function () {
    $opaque = array_keys(array_filter(outboundGuardTreeReport(), static fn (array $file): bool => $file['opaque'] > 0));

    expect($opaque)->toBe([]);
});

it('reads the intranet-host config from the link checker alone', function () {
    // Matched on string-literal tokens, so `config()`, `Config::get()` and `config()->get()` all
    // count while a docblock or comment naming the key does not.
    $directories = array_map(base_path(...), OUTBOUND_GUARD_SCANNED_DIRECTORIES);
    $readers = [];

    foreach (Finder::create()->files()->in($directories)->name('*.php')->contains('link_health') as $file) {
        foreach (token_get_all($file->getContents()) as $token) {
            if (is_array($token)
                && $token[0] === T_CONSTANT_ENCAPSED_STRING
                && in_array(trim($token[1], '\'"'), ['link_health', 'link_health.intranet_hosts'], true)) {
                $readers[] = str_replace('\\', '/', substr((string) $file->getRealPath(), strlen(base_path()) + 1));

                break;
            }
        }
    }

    expect($readers)->toBe(['app/Services/LinkHealth/ExternalProber.php']);
});

it('keeps intranet hosts out of the first-party ownership config', function () {
    // Reachability and ownership are separate properties (UAMS-Web/uams-statamic-archive#1768): inside.uams.edu is first-party
    // through uams.edu, and nothing about reaching it lives in config/first_party.php.
    expect(FirstPartyUrl::isFirstPartyUrl('https://inside.uams.edu/page'))->toBeTrue()
        ->and(file_get_contents(config_path('first_party.php')))->not->toContain('intranet');
});
