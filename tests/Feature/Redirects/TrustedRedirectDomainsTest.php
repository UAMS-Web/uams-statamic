<?php

declare(strict_types=1);

// Hosts built to look like an allowed one, which no dictionary carries
// cspell:ignore uamsxflu xuams

use App\Http\Middleware\ServeRedirectDomainRequests;
use App\Http\Middleware\TrustHosts;
use App\Services\Redirects\RedirectDomainRules;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Stache\Stores\CollectionsStore;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\StacheIsolatedTestCase;

// Stache-isolated: each test saves redirect domain entries.
uses(StacheIsolatedTestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#3014's redirect-domain half: a published redirect domain is an
 * allowed host, read on each request, and matched exactly.
 *
 * The guard is driven directly, with a downstream that records whether it ran,
 * because the isolated Stache base fakes the item filesystem that a full kernel
 * pass discards. Laravel switches the guard off in tests, so each case binds the app's
 * guard with that switch turned on.
 *
 * @command  php artisan test --compact tests/Feature/Redirects/TrustedRedirectDomainsTest.php
 */
beforeEach(function (): void {
    // Turn the guard on, which Laravel's own switch keeps off in tests
    $this->app->bind(TrustHosts::class, fn (Application $app): TrustHosts => new class($app) extends TrustHosts
    {
        protected function shouldSpecifyTrustedHosts(): bool
        {
            return true;
        }
    });

    // Read the real blueprint through the isolated blueprint directory
    $blueprint = 'collections/redirect_domains/redirect_domain.yaml';
    File::ensureDirectoryExists(dirname($this->fakeBlueprintDirectory.'/'.$blueprint));
    File::copy(resource_path('blueprints/'.$blueprint), $this->fakeBlueprintDirectory.'/'.$blueprint);

    // Build the collection from the config file that ships
    $path = base_path('content/collections/redirect_domains.yaml');
    $store = Stache::store('collections');
    Assert::assertInstanceOf(CollectionsStore::class, $store);

    $collection = $store->makeItemFromFile($path, File::get($path));
    Assert::assertInstanceOf(StatamicCollection::class, $collection);

    $collection->save();
});

afterEach(function (): void {
    // Clear the allowed list, which Symfony keeps for the whole process
    Request::setTrustedHosts([]);
});

/**
 * Save a redirect domain entry with one plain rule.
 *
 * @param  string  $host  The entry's host.
 * @param  bool  $published  Whether the entry is published.
 */
function trustedRedirectDomainEntry(string $host, bool $published = true): StatamicEntry
{
    $entry = Entry::make();
    Assert::assertInstanceOf(StatamicEntry::class, $entry);

    $entry->collection(RedirectDomainRules::COLLECTION);
    $entry->locale('default');
    $entry->published($published);
    $entry->data([
        'host' => $host,
        'enabled' => true,
        'whole_domain' => false,
        'use_regex' => false,
        'source' => '/clinic',
        'target_type' => 'url',
        'target_url' => 'https://uamshealth.com/flu-clinic',
        'response' => '301',
        'order' => 30,
    ]);
    $entry->save();

    return $entry;
}

/**
 * Send a request through the guard and then the redirect-domain middleware, as
 * the global stack runs them, and report whether the guard let it through.
 *
 * A refusal is the exception Symfony throws for an untrusted host, which the
 * application renders as a 400; `TrustedHostsTest` covers that rendering through
 * the kernel.
 *
 * @param  string  $url  The absolute request URL.
 * @return array{response: Response|null, reached: bool, refused: bool} The response,
 *                                                                      null when refused;
 *                                                                      whether anything after
 *                                                                      the guard ran; and
 *                                                                      whether the guard refused.
 */
function trustedRedirectDomainRequest(string $url): array
{
    $reached = false;

    $guard = app(TrustHosts::class);
    Assert::assertInstanceOf(TrustHosts::class, $guard);
    $serve = app(ServeRedirectDomainRequests::class);
    Assert::assertInstanceOf(ServeRedirectDomainRequests::class, $serve);

    try {
        $response = $guard->handle(Request::create($url), function (Request $request) use ($serve, &$reached): Response {
            $reached = true;

            return $serve->handle($request, fn (Request $request): Response => response('SITE CONTENT', 200));
        });
    } catch (SuspiciousOperationException) {
        return ['response' => null, 'reached' => $reached, 'refused' => true];
    }

    return ['response' => $response, 'reached' => $reached, 'refused' => false];
}

it('answers a published redirect domain as it does today', function (): void {
    trustedRedirectDomainEntry('uams.flu');

    $result = trustedRedirectDomainRequest('https://uams.flu/clinic');

    expect($result['reached'])->toBeTrue()
        ->and($result['response'])->toBeInstanceOf(RedirectResponse::class)
        ->and($result['response']?->getStatusCode())->toBe(301);
});

it('allows a redirect domain published after boot, with no deploy', function (): void {
    // Refused before the domain is published
    $before = trustedRedirectDomainRequest('https://uams.flu/clinic');

    expect($before['refused'])->toBeTrue()
        ->and($before['reached'])->toBeFalse();

    // Allowed once it is, in the same process
    trustedRedirectDomainEntry('uams.flu');

    $after = trustedRedirectDomainRequest('https://uams.flu/clinic');

    expect($after['reached'])->toBeTrue()
        ->and($after['response']?->getStatusCode())->toBe(301);
});

it('refuses an unpublished redirect domain', function (): void {
    trustedRedirectDomainEntry('uams.flu', published: false);

    $result = trustedRedirectDomainRequest('https://uams.flu/clinic');

    expect($result['refused'])->toBeTrue()
        ->and($result['reached'])->toBeFalse();
});

it('matches a redirect domain exactly', function (string $host): void {
    trustedRedirectDomainEntry('uams.flu');

    $result = trustedRedirectDomainRequest('https://'.$host.'/clinic');

    expect($result['refused'])->toBeTrue()
        ->and($result['reached'])->toBeFalse();
})->with([
    'a host that contains it' => 'uams.flu.attacker.example',
    'a host that ends with it after another label' => 'attacker.uams.flu',
    'its www form, which the collection does not name' => 'www.uams.flu',
    'a host with a letter where its dot is' => 'uamsxflu',
    'a host that ends with it without a dot' => 'xuams.flu',
]);

it('allows a www form the collection names as its own entry', function (): void {
    trustedRedirectDomainEntry('uams.flu');
    trustedRedirectDomainEntry('www.uams.flu');

    $result = trustedRedirectDomainRequest('https://www.uams.flu/clinic');

    expect($result['reached'])->toBeTrue()
        ->and($result['response']?->getStatusCode())->toBe(301);
});

it('reads each published host once and leaves out an invalid one', function (): void {
    trustedRedirectDomainEntry('uams.flu');
    trustedRedirectDomainEntry('UAMS.flu.');
    trustedRedirectDomainEntry('not a host');
    trustedRedirectDomainEntry('uams.news', published: false);

    expect(RedirectDomainRules::publishedHosts())->toBe(['uams.flu']);
});
