<?php

declare(strict_types=1);

use Pest\Browser\Api\ArrayablePendingAwaitablePage;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;
use Statamic\Auth\User;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Fields\Blueprint as StatamicBlueprint;

/*
 | Does the HTTP Response Code radio show the code a redirect stores (#326)?
 |
 | Every writer stores `response` as a string such as `'301'`, and both
 | blueprints default to `'301'`. The radio selects an option only when its key
 | is identical to the value, so integer keys left every option unselected, on
 | an imported entry and on a new one alike.
 |
 | The two fields here carry the `response` configuration read from the
 | `redirect_domain` blueprint and from the `redirects` global's replicator, as
 | Statamic serves them (the global's from the `uams-web/aether-redirects`
 | add-on), so the test follows those blueprints. The global's field is lifted out of its
 | replicator because the radio is the same fieldtype with the same config there.
 |
 | @command vendor/bin/pest tests/Browser/CpRedirectResponseCodeTest.php
 */

$collectionHandle = 'redirect_response_code';

/**
 * Find the `response` field configuration in a blueprint.
 *
 * @param  string  $handle  The blueprint as Statamic finds it, such as `globals.redirects`.
 * @return array<string, mixed> The field's configuration as the blueprint declares it.
 */
function redirectResponseFieldConfig(string $handle): array
{
    $find = function (mixed $node) use (&$find): ?array {
        if (! \is_array($node)) {
            return null;
        }
        if (($node['handle'] ?? null) === 'response' && \is_array($node['field'] ?? null)) {
            return $node['field'];
        }
        foreach ($node as $child) {
            $found = $find($child);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    };

    $blueprint = Blueprint::find($handle);
    expect($blueprint)->toBeInstanceOf(StatamicBlueprint::class);

    /** @var StatamicBlueprint $blueprint */
    $config = $find($blueprint->contents());
    expect($config)->toBeArray();

    /** @var array<string, mixed> $config */
    return $config;
}

/**
 * The selected option of each radio group on the page, in page order.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page showing the publish form.
 * @return list<string|null> The selected option's value per group, or null where none is selected.
 */
function redirectResponseSelectedCodes($page): array
{
    $json = mustBeString($page->script('(() => JSON.stringify([...document.querySelectorAll("[role=radiogroup]")].map((group) => '
        .'group.querySelector("[role=radio][aria-checked=true]")?.getAttribute("value") ?? null)))()'), 'the selected codes');

    /** @var list<string|null> */
    return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
}

beforeEach(function () use ($collectionHandle) {
    $this->cpEmail = 'redirect-response-'.uniqid().'@example.test';
    $this->cpPassword = 'redirect-response-password-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);

    $collection = Collection::make($collectionHandle);
    $collection->title('Redirect Response Code');
    $collection->save();

    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text', 'display' => 'Title'],
        'domain_response' => redirectResponseFieldConfig('collections.redirect_domains.redirect_domain'),
        'global_response' => redirectResponseFieldConfig('globals.redirects'),
    ]);
    $blueprint->setHandle($collectionHandle);
    $blueprint->setNamespace('collections.'.$collectionHandle);
    $blueprint->save();

    Stache::refresh();
});

afterEach(function () use ($collectionHandle) {
    /** @var string|null $entryId */
    $entryId = $this->entryId ?? null;
    if (\is_string($entryId)) {
        $entry = Entry::find($entryId);
        if ($entry instanceof EntryModel) {
            $entry->delete();
        }
    }

    Collection::find($collectionHandle)?->delete();
    Blueprint::find('collections.'.$collectionHandle.'.'.$collectionHandle)?->delete();
    /** @var User|null $user */
    $user = $this->cpUser;
    $user?->delete();
    Stache::refresh();
});

it('opens a new entry with the default 301 selected', function () use ($collectionHandle) {
    /** @var string $email */
    $email = $this->cpEmail;
    /** @var string $password */
    $password = $this->cpPassword;

    $page = loginToCp($email, $password)->navigate('/cp/collections/'.$collectionHandle.'/entries/create/default');
    waitToSee($page, '301 Moved Permanently', 60.0);

    expect(redirectResponseSelectedCodes($page))->toBe(['301', '301']);

    assertNoAppJavaScriptErrors($page);
});

it('shows the stored code and saves it back unchanged', function (string $code) use ($collectionHandle) {
    /** @var string $email */
    $email = $this->cpEmail;
    /** @var string $password */
    $password = $this->cpPassword;

    /** @var EntryModel $entry */
    $entry = Entry::make();
    $entry->collection($collectionHandle);
    $entry->slug('redirect-response-'.$code);
    $entry->data(['title' => 'Redirect response '.$code, 'domain_response' => $code, 'global_response' => $code, 'updated_at' => 1_000_000_000]);
    $entry->save();
    $entryId = mustBeString($entry->id(), 'the entry id');
    $this->entryId = $entryId;
    Stache::refresh();

    $page = loginToCp($email, $password)->navigate('/cp/collections/'.$collectionHandle.'/entries/'.$entryId);
    waitToSee($page, '301 Moved Permanently', 60.0);

    expect(redirectResponseSelectedCodes($page))->toBe([$code, $code]);

    // Save without touching any field. The save shows as a new `updated_at`.
    $page->click('Save & Publish');

    $saved = null;
    for ($waited = 0.0; $waited <= 15.0; $waited += 0.5) {
        $page->wait(0.5);
        $saved = Entry::find($entryId);
        if ($saved instanceof EntryModel && $saved->get('updated_at') !== 1_000_000_000) {
            break;
        }
    }

    expect($saved)->toBeInstanceOf(EntryModel::class);
    /** @var EntryModel $saved */
    expect($saved->get('updated_at'))->not->toBe(1_000_000_000)
        ->and($saved->get('domain_response'))->toBe($code)
        ->and($saved->get('global_response'))->toBe($code);

    assertNoAppJavaScriptErrors($page);
})->with(['301', '307']);
