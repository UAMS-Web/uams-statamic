<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
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
use Statamic\Fields\BlueprintRepository;

/*
 | The `text_phonetic_pair` fieldtype comes from `uams-web/aether-people` since #1000,
 | and #1184 asks whether it works there in a browser: in the Control Panel, from the
 | add-on's own component, and on a public form, from the add-on's own view.
 |
 | The Feature test (tests/Feature/Fieldtypes/TextPhoneticPairFieldtypeTest.php) pins
 | the view path and the component registration. This file loads both: it fills and
 | saves the pair in the entry editor and reads the stored value back, and it renders
 | a form with the field and reads the live inputs.
 |
 | The field's labels are set in its config, so a label on screen shows the add-on's
 | component or view read that config rather than a generic text input appearing.
 |
 | @command vendor/bin/pest tests/Browser/TextPhoneticPairAddOnTest.php
 */
const TEXT_PHONETIC_PAIR_BROWSER_COLLECTION = 'text_phonetic_pair_smoke';

const TEXT_PHONETIC_PAIR_BROWSER_FORM = 'text_phonetic_pair_browser';

/**
 * The field as both halves configure it.
 *
 * @return array<string, mixed>
 */
function textPhoneticPairBrowserField(): array
{
    return [
        'type' => 'text_phonetic_pair',
        'display' => 'Pronounced',
        'text_label' => 'Name as Written',
        'phonetic_label' => 'Name in IPA',
    ];
}

/** The public form fixture's path, fixed so afterEach can delete it. */
function textPhoneticPairBrowserFixturePath(): string
{
    return public_path('__text_phonetic_pair_fixture.html');
}

/**
 * Read the entry's stored `pronounced`, waiting for a save to land.
 *
 * The app is served from this process, so the wait goes through the page, which
 * lets the server handle the save; a sleep here would block the request it waits on.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page that submitted the save
 * @return array<array-key, mixed> The stored pair, or an empty array if none arrived
 */
function textPhoneticPairSavedValue($page, string $entryId): array
{
    $pair = [];

    for ($waited = 0.0; $waited <= 15.0; $waited += 0.5) {
        $page->wait(0.5);
        $entry = Entry::find($entryId);
        $stored = $entry instanceof EntryModel ? $entry->get('pronounced') : null;
        $pair = \is_array($stored) ? $stored : [];

        if ($pair !== []) {
            break;
        }
    }

    return $pair;
}

describe('in the Control Panel', function (): void {
    beforeEach(function (): void {
        $this->cpEmail = 'text-phonetic-pair-'.uniqid().'@example.test';
        $this->cpPassword = 'text-phonetic-pair-password-'.uniqid();
        $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);

        /** @var Statamic\Entries\Collection $collection */
        $collection = Collection::make(TEXT_PHONETIC_PAIR_BROWSER_COLLECTION);
        $collection->title('Text Phonetic Pair Smoke');
        $collection->save();

        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = Blueprint::makeFromFields([
            'title' => ['type' => 'text', 'display' => 'Title'],
            'pronounced' => textPhoneticPairBrowserField(),
        ]);
        $blueprint->setHandle(TEXT_PHONETIC_PAIR_BROWSER_COLLECTION);
        $blueprint->setNamespace('collections.'.TEXT_PHONETIC_PAIR_BROWSER_COLLECTION);
        $blueprint->save();

        /** @var EntryModel $entry */
        $entry = Entry::make();
        $entry->collection(TEXT_PHONETIC_PAIR_BROWSER_COLLECTION);
        $entry->slug('text-phonetic-pair-smoke');
        $entry->data(['title' => 'Text Phonetic Pair Smoke']);
        $entry->save();

        $this->entryId = $entry->id();

        Stache::refresh();
    });

    afterEach(function (): void {
        /** @var string|null $entryId */
        $entryId = $this->entryId ?? null;

        if (\is_string($entryId)) {
            $entry = Entry::find($entryId);

            if ($entry instanceof EntryModel) {
                $entry->delete();
            }
        }

        Collection::find(TEXT_PHONETIC_PAIR_BROWSER_COLLECTION)?->delete();
        Blueprint::find('collections.'.TEXT_PHONETIC_PAIR_BROWSER_COLLECTION.'.'.TEXT_PHONETIC_PAIR_BROWSER_COLLECTION)?->delete();
        /** @var User|null $user */
        $user = $this->cpUser;
        $user?->delete();
        Stache::refresh();
    });

    it("renders the add-on's component in the entry editor and saves the pair", function (): void {
        /** @var string $cpEmail */
        $cpEmail = $this->cpEmail;
        /** @var string $cpPassword */
        $cpPassword = $this->cpPassword;
        /** @var string $entryId */
        $entryId = $this->entryId;

        $page = loginToCp($cpEmail, $cpPassword)
            ->navigate('/cp/collections/'.TEXT_PHONETIC_PAIR_BROWSER_COLLECTION.'/entries/'.$entryId);

        waitToSee($page, 'Name in IPA', 60.0);
        $page->assertSee('Name as Written');

        // The component's root, its two inputs by the ids it builds, and the IPA guidance alert it renders.
        $rendered = $page->script('(() => JSON.stringify({
            root: document.querySelectorAll(".text-phonetic-pair-fieldtype").length,
            text: document.querySelectorAll("[id$=\"pronounced__tp_text\"]").length,
            phonetic: document.querySelectorAll("[id$=\"pronounced__tp_pho\"]").length,
        }))()');
        expect($rendered)->toBe('{"root":1,"text":1,"phonetic":1}');

        $page->type('[id$="pronounced__tp_text"]', 'Test');
        $page->type('[id$="pronounced__tp_pho"]', 'ˈtɛst');
        $page->click('Save & Publish');

        expect(textPhoneticPairSavedValue($page, $entryId))->toMatchArray([
            'text' => 'Test',
            'phonetic_text' => 'ˈtɛst',
        ]);

        assertNoAppJavaScriptErrors($page);
    });
});

describe('on a public form', function (): void {
    beforeEach(function (): void {
        $this->formsDir = storage_path('framework/testing/forms-'.uniqid('', true));
        File::ensureDirectoryExists($this->formsDir);
        Config::set('statamic.forms.forms', $this->formsDir);

        app(BlueprintRepository::class)->setFallback(
            'forms.'.TEXT_PHONETIC_PAIR_BROWSER_FORM,
            fn () => Blueprint::makeFromFields(['pronounced' => textPhoneticPairBrowserField()]),
        );

        $form = makeForm(TEXT_PHONETIC_PAIR_BROWSER_FORM);
        $form->title('Text Phonetic Pair');
        $form->save();

        $this->viewsDir = storage_path('framework/testing/tpp-views-'.uniqid('', true));
        File::ensureDirectoryExists($this->viewsDir);
        View::addNamespace('text_phonetic_pair_browser', $this->viewsDir);
    });

    afterEach(function (): void {
        if (File::exists($fixture = textPhoneticPairBrowserFixturePath())) {
            File::delete($fixture);
        }

        /** @var string $formsDir */
        $formsDir = $this->formsDir;
        /** @var string $viewsDir */
        $viewsDir = $this->viewsDir;

        foreach ([$formsDir, $viewsDir, storage_path('forms/'.TEXT_PHONETIC_PAIR_BROWSER_FORM)] as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }
    });

    it("renders the add-on's view for the field", function (): void {
        /** @var string $viewsDir */
        $viewsDir = $this->viewsDir;

        File::put(
            $viewsDir.'/form.antlers.html',
            '{{ form:create handle="'.TEXT_PHONETIC_PAIR_BROWSER_FORM.'" js="alpine_csp" }}'
            .'{{ partial:vendor/statamic/forms/fields }}'
            .'{{ /form:create }}',
        );

        /** @var array<string, array{file?: string}> $manifest */
        $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
        $siteJs = '/build/'.($manifest['resources/js/site.js']['file'] ?? '');

        File::put(textPhoneticPairBrowserFixturePath(), implode('', [
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">',
            '<title>Text phonetic pair fixture</title>',
            '<script defer src="/vendor/statamic/frontend/js/helpers.js"></script><script defer src="/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js"></script><script type="module" src="'.$siteJs.'"></script>',
            '</head><body><h1>Text phonetic pair fixture</h1>',
            (string) View::make('text_phonetic_pair_browser::form')->render(),
            '</body></html>',
        ]));

        $page = visit('/__text_phonetic_pair_fixture.html');

        $page->assertSee('Name as Written')
            ->assertSee('Name in IPA')
            ->assertSee('The phonetic text uses the International Phonetic Alphabet (IPA).');

        // The add-on's view: its wrapper, both named inputs each with its label, and `lang="ipa"` on the phonetic one.
        $rendered = $page->script('(() => {
            const text = document.querySelector(".text-phonetic-pair input[name=\"pronounced[text]\"]");
            const phonetic = document.querySelector(".text-phonetic-pair input[name=\"pronounced[phonetic_text]\"]");
            const label = (input) => input ? document.querySelector("label[for=\"" + input.id + "\"]")?.textContent.trim() : null;

            return JSON.stringify({
                wrappers: document.querySelectorAll(".text-phonetic-pair").length,
                textLabel: label(text),
                phoneticLabel: label(phonetic),
                lang: phonetic?.getAttribute("lang") ?? null,
            });
        })()');
        expect($rendered)->toBe('{"wrappers":1,"textLabel":"Name as Written","phoneticLabel":"Name in IPA","lang":"ipa"}');

        assertNoAppJavaScriptErrors($page);
    });
});
