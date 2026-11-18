<?php

declare(strict_types=1);

// Hosts built to look like an allowed one, which no dictionary carries
// cspell:ignore uamsxflu xuams

use App\Http\Middleware\TrustHosts;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Facades\User;
use Statamic\Stache\Stores\CollectionsStore;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\StacheIsolatedTestCase;
use UAMSWeb\Aether\Redirects\Http\Middleware\ServeRedirectDomainRequests;
use UAMSWeb\Aether\Redirects\Services\RedirectDomainList;
use UAMSWeb\Aether\Redirects\Services\RedirectDomainRules;

use function Pest\Laravel\actingAs;

// Stache-isolated: each test saves domain records and redirect domain entries.
uses(StacheIsolatedTestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#3014's redirect-domain half: a published redirect domain is an
 * allowed host, read on each request, and matched exactly.
 *
 * Since `uams-web/aether-redirects` 0.4.0 (#668, UAMS-Web/aether-redirects#24) the
 * allowed hosts are the published records of the `redirect_hosts` domain list, and a
 * redirect domain entry's `host` holds a record id. The add-on's `GuardRedirectHosts`
 * refuses a rule whose host is not a published record's id, and refuses a record from
 * a signed-in user without `manage redirect domains`.
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

    foreach (['redirect_hosts' => 'redirect_host', 'redirect_domains' => 'redirect_domain'] as $handle => $blueprintHandle) {
        // Read the real blueprint through the isolated blueprint directory
        $blueprint = 'collections/'.$handle.'/'.$blueprintHandle.'.yaml';
        File::ensureDirectoryExists(dirname($this->fakeBlueprintDirectory.'/'.$blueprint));
        File::copy(resource_path('blueprints/'.$blueprint), $this->fakeBlueprintDirectory.'/'.$blueprint);

        // Build the collection from the config file that ships
        $path = base_path('content/collections/'.$handle.'.yaml');
        $store = Stache::store('collections');
        Assert::assertInstanceOf(CollectionsStore::class, $store);

        $collection = $store->makeItemFromFile($path, File::get($path));
        Assert::assertInstanceOf(StatamicCollection::class, $collection);

        $collection->save();
    }
});

afterEach(function (): void {
    // Clear the allowed list, which Symfony keeps for the whole process
    Request::setTrustedHosts([]);
});

/**
 * Save a domain record in the `redirect_hosts` domain list.
 *
 * @param  string  $host  The domain the record names.
 * @param  bool  $published  Whether the record is published.
 */
function trustedRedirectHostRecord(string $host, bool $published = true): StatamicEntry
{
    $record = Entry::make();
    Assert::assertInstanceOf(StatamicEntry::class, $record);

    $record->collection(RedirectDomainList::COLLECTION);
    $record->locale('default');
    $record->published($published);
    $record->data(['host' => $host]);
    $record->save();

    return $record;
}

/**
 * Save a redirect domain entry with one plain rule.
 *
 * @param  StatamicEntry|string  $host  The domain record the rule names, or a raw
 *                                      stored value to save as the host.
 */
function trustedRedirectDomainEntry(StatamicEntry|string $host): StatamicEntry
{
    $entry = Entry::make();
    Assert::assertInstanceOf(StatamicEntry::class, $entry);

    $entry->collection(RedirectDomainRules::COLLECTION);
    $entry->locale('default');
    $entry->data([
        'host' => $host instanceof StatamicEntry ? $host->id() : $host,
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
 * Persist a user who is not a super user and holds no role.
 */
function trustedRedirectDomainUser(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('redirect-domains-'.Str::uuid().'@example.test');
    $user->save();

    return $user;
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

it('answers a rule that names a published domain record', function (): void {
    trustedRedirectDomainEntry(trustedRedirectHostRecord('uams.flu'));

    $result = trustedRedirectDomainRequest('https://uams.flu/clinic');

    expect($result['reached'])->toBeTrue()
        ->and($result['response'])->toBeInstanceOf(RedirectResponse::class)
        ->and($result['response']?->getStatusCode())->toBe(301);
});

it('allows a domain record published after boot, with no deploy', function (): void {
    // Refused before the domain is in the list
    $before = trustedRedirectDomainRequest('https://uams.flu/clinic');

    expect($before['refused'])->toBeTrue()
        ->and($before['reached'])->toBeFalse();

    // Allowed once it is, in the same process
    trustedRedirectDomainEntry(trustedRedirectHostRecord('uams.flu'));

    $after = trustedRedirectDomainRequest('https://uams.flu/clinic');

    expect($after['reached'])->toBeTrue()
        ->and($after['response']?->getStatusCode())->toBe(301);
});

it('refuses a domain whose record is unpublished', function (): void {
    $record = trustedRedirectHostRecord('uams.flu');
    trustedRedirectDomainEntry($record);

    // Unpublish the record after the rule names it
    $record->published(false);
    $record->save();

    $result = trustedRedirectDomainRequest('https://uams.flu/clinic');

    expect($result['refused'])->toBeTrue()
        ->and($result['reached'])->toBeFalse();
});

it('matches a domain record exactly', function (string $host): void {
    trustedRedirectDomainEntry(trustedRedirectHostRecord('uams.flu'));

    $result = trustedRedirectDomainRequest('https://'.$host.'/clinic');

    expect($result['refused'])->toBeTrue()
        ->and($result['reached'])->toBeFalse();
})->with([
    'a host that contains it' => 'uams.flu.attacker.example',
    'a host that ends with it after another label' => 'attacker.uams.flu',
    'its www form, which the list does not name' => 'www.uams.flu',
    'a host with a letter where its dot is' => 'uamsxflu',
    'a host that ends with it without a dot' => 'xuams.flu',
]);

it('allows a www form the list names as its own record', function (): void {
    trustedRedirectHostRecord('uams.flu');
    trustedRedirectDomainEntry(trustedRedirectHostRecord('www.uams.flu'));

    $result = trustedRedirectDomainRequest('https://www.uams.flu/clinic');

    expect($result['reached'])->toBeTrue()
        ->and($result['response']?->getStatusCode())->toBe(301);
});

it('normalizes a record and reads each published host once', function (): void {
    $record = trustedRedirectHostRecord('UAMS.flu.');
    trustedRedirectHostRecord('uams.news', published: false);

    expect($record->get('host'))->toBe('uams.flu')
        ->and(RedirectDomainRules::publishedHosts())->toBe(['uams.flu']);
});

it('refuses a record that is not a domain or that the list already names', function (string $host): void {
    trustedRedirectHostRecord('uams.flu');

    expect(fn () => trustedRedirectHostRecord($host))->toThrow(ValidationException::class);

    // Control: the list still holds the one record
    expect(RedirectDomainRules::publishedHosts())->toBe(['uams.flu']);
})->with([
    'not a domain' => 'not a host',
    'the same domain written differently' => 'UAMS.flu.',
]);

it('refuses a rule whose host is not a domain record id', function (): void {
    // The record exists, so the string would resolve; the rule must name the record
    trustedRedirectHostRecord('uams.flu');

    expect(fn () => trustedRedirectDomainEntry('uams.flu'))->toThrow(ValidationException::class);

    // Control: the same rule naming the record saves
    expect(trustedRedirectDomainEntry(trustedRedirectHostRecord('uams.news'))->id())->toBeString();
});

it('refuses a domain record from a user without the permission to manage the list', function (): void {
    actingAs(trustedRedirectDomainUser());

    expect(fn () => trustedRedirectHostRecord('uams.flu'))->toThrow(AuthorizationException::class)
        ->and(RedirectDomainRules::publishedHosts())->toBe([]);
});

it('accepts a domain record from a super user', function (): void {
    $user = trustedRedirectDomainUser();
    $user->makeSuper();
    $user->save();
    actingAs($user);

    trustedRedirectHostRecord('uams.flu');

    expect(RedirectDomainRules::publishedHosts())->toBe(['uams.flu']);
});

it('migrates a rule that still holds a host string to a domain record', function (): void {
    // A rule saved before 0.4.0 holds the host as text; save it past the guard as such
    $legacy = Entry::make();
    Assert::assertInstanceOf(StatamicEntry::class, $legacy);
    $legacy->collection(RedirectDomainRules::COLLECTION);
    $legacy->locale('default');
    $legacy->data([
        'host' => 'uams.flu',
        'enabled' => true,
        'whole_domain' => false,
        'use_regex' => false,
        'source' => '/clinic',
        'target_type' => 'url',
        'target_url' => 'https://uamshealth.com/flu-clinic',
        'response' => '301',
        'order' => 30,
    ]);
    $legacy->saveQuietly();

    // Before the migration no record names the domain, so it is refused
    expect(trustedRedirectDomainRequest('https://uams.flu/clinic')['refused'])->toBeTrue();

    // The dry run reports the change and makes none
    $dryRun = $this->artisan('redirects:migrate-domain-hosts', ['--dry-run' => true]);
    Assert::assertInstanceOf(PendingCommand::class, $dryRun);
    $dryRun->expectsOutputToContain('Dry run: 1 domain record(s) created, 1 entry rewritten, 0 skipped.')
        ->assertSuccessful()
        ->run();

    expect(RedirectDomainList::records())->toBe([]);

    // The run creates the record and points the rule at it
    $run = $this->artisan('redirects:migrate-domain-hosts');
    Assert::assertInstanceOf(PendingCommand::class, $run);
    $run->expectsOutputToContain('1 domain record(s) created, 1 entry rewritten, 0 skipped.')
        ->assertSuccessful()
        ->run();

    $records = RedirectDomainList::records();
    $migrated = Entry::find($legacy->id());
    Assert::assertInstanceOf(StatamicEntry::class, $migrated);

    expect(array_values($records))->toBe(['uams.flu'])
        ->and(RedirectDomainList::isReference($migrated->get('host')))->toBeTrue()
        ->and(trustedRedirectDomainRequest('https://uams.flu/clinic')['response']?->getStatusCode())->toBe(301);
});
