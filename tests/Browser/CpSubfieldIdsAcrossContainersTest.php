<?php

declare(strict_types=1);

use Pest\Browser\Api\ArrayablePendingAwaitablePage;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;
use Statamic\Auth\User;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Stache;

/*
 * #327, #336: the element ids of first-party fieldtypes carry the publish
 * container, as Statamic's own field ids do. The entry editor is one container
 * and the inline-create stack of an `entries` field is another, so one
 * blueprint renders each fieldtype here twice at once.
 * Every id on the page must then be unique, and every label must point at the
 * control in its own container rather than the first one on the page.
 */

$collectionHandle = 'subfield_ids_smoke';

/**
 * Reports, as JSON, every id on the page that appears more than once, and each
 * address subfield label with the container it sits in and whether its `for`
 * resolves to a control in that same container. The sibling `postal_city`
 * field's own label is left out of the list; its id is still in the duplicate
 * check. The same report covers the controls of the `gps`, `person_name` and
 * `text_phonetic_pair` fields and their labels, under `controls` and `fieldLabels`.
 */
const SUBFIELD_IDS_SCRIPT = <<<'JS'
(() => {
    const container = (el) => el.closest('.stack-content, [data-stack], .stack') ? 'stack' : 'base';
    const ids = [...document.querySelectorAll('[id]')].map((el) => el.id);
    const control = /__(lat|lng|paste|address_search|(?:fn|mn|nick|ln)_(?:text|pho)|gen|df|tp_(?:text|pho))$/;
    const labels = [...document.querySelectorAll('label[for]')]
        .filter((label) => /_(street_address|address_line_2|city|state_province_region|postal_code)$/.test(label.htmlFor))
        .filter((label) => !/_postal_city$/.test(label.htmlFor))
        .map((label) => {
            const target = document.getElementById(label.htmlFor);

            return {
                for: label.htmlFor,
                container: container(label),
                resolves: target !== null,
                sameContainer: target !== null && container(target) === container(label),
            };
        });

    const controls = [...document.querySelectorAll('[id]')]
        .filter((el) => control.test(el.id))
        .map((el) => ({ id: el.id, container: container(el) }));
    const fieldLabels = [...document.querySelectorAll('label[for]')]
        .filter((label) => control.test(label.htmlFor))
        .map((label) => {
            const target = document.getElementById(label.htmlFor);

            return {
                for: label.htmlFor,
                container: container(label),
                resolves: target !== null,
                sameContainer: target !== null && container(target) === container(label),
            };
        });

    return JSON.stringify({
        duplicates: [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))],
        labels,
        controls,
        fieldLabels,
    });
})()
JS;

/**
 * Snooze Statamic's trial-mode licensing alert when it opens. It is a modal
 * over the publish form, so every click behind it times out.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from navigate().
 */
function subfieldIdsDismissLicensingAlert($page): void
{
    for ($waited = 0.0; $waited <= 3.0; $waited += 0.2) {
        $open = $page->script('(() => [...document.querySelectorAll("button")].some((b) => b.textContent.trim() === "Snooze"))()');

        if ($open === true) {
            $page->click('Snooze');

            break;
        }

        $page->wait(0.2);
    }
}

beforeEach(function () use ($collectionHandle) {
    $this->cpEmail = 'subfield-ids-'.uniqid().'@example.test';
    $this->cpPassword = 'subfield-ids-password-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);

    $collection = Collection::make($collectionHandle);
    $collection->title('Subfield Ids Smoke');
    $collection->save();

    $blueprint = Blueprint::makeFromFields([
        'postal' => ['type' => 'address', 'display' => 'Postal Address'],
        // Named after the address's `city` subfield, so a subfield id built as
        // `<field id>_city` would collide with this field's own id.
        'postal_city' => ['type' => 'text', 'display' => 'Postal City'],
        'coordinates' => ['type' => 'gps', 'display' => 'Coordinates', 'enable_address_search' => true],
        'legal_name' => [
            'type' => 'person_name',
            'display' => 'Legal Name',
            'include_display_format' => true,
            'include_nickname' => true,
            'include_phonetic' => true,
        ],
        'pronounced' => ['type' => 'text_phonetic_pair', 'display' => 'Pronounced'],
        'related' => [
            'type' => 'entries',
            'display' => 'Related',
            'collections' => [$collectionHandle],
            'create' => true,
        ],
    ]);
    $blueprint->setHandle($collectionHandle);
    $blueprint->setNamespace('collections.'.$collectionHandle);
    $blueprint->save();

    Stache::refresh();
});

afterEach(function () use ($collectionHandle) {
    Collection::find($collectionHandle)?->delete();
    /** @var Statamic\Fields\Blueprint|null $smokeBlueprint */
    $smokeBlueprint = Blueprint::find('collections.'.$collectionHandle.'.'.$collectionHandle);
    $smokeBlueprint?->delete();
    /** @var User|null $cpUser */
    $cpUser = $this->cpUser;
    $cpUser?->delete();
    Stache::refresh();
});

it('gives each publish container its own field ids, with every label on its own control', function () use ($collectionHandle) {
    /** @var string $email */
    $email = $this->cpEmail;
    /** @var string $password */
    $password = $this->cpPassword;
    $page = loginToCp($email, $password)->navigate('/cp/collections/'.$collectionHandle.'/entries/create/default');

    waitToSee($page, 'Postal Address');
    subfieldIdsDismissLicensingAlert($page);

    // The page heading also reads "Create Entry", so press the field's button directly.
    $page->script('(() => [...document.querySelectorAll("button")].find((b) => b.textContent.trim() === "Create Entry").click())()');

    for ($waited = 0.0; $waited <= 15.0; $waited += 0.5) {
        if ($page->script('document.querySelectorAll("label[for$=\"_street_address\"], [id$=\"__lat\"], [id$=\"__tp_text\"]").length') >= 6) {
            break;
        }

        $page->wait(0.5);
    }

    /** @var array{duplicates: list<string>, labels: list<array{for: string, container: string, resolves: bool, sameContainer: bool}>, controls: list<array{id: string, container: string}>, fieldLabels: list<array{for: string, container: string, resolves: bool, sameContainer: bool}>} $report */
    $report = json_decode(mustBeString($page->script(SUBFIELD_IDS_SCRIPT), 'the field id report'), true, flags: JSON_THROW_ON_ERROR);

    $byContainer = collect($report['labels'])->groupBy('container');

    expect($report['duplicates'])->toBe([]);

    // The five address subfields in each container.
    expect($byContainer->get('base'))->toHaveCount(5)
        ->and($byContainer->get('stack'))->toHaveCount(5);

    // Every control of the three fieldtypes, once in each container.
    $controls = ['base' => [], 'stack' => []];

    foreach ($report['controls'] as $control) {
        $controls[$control['container']][] = (string) str($control['id'])->afterLast('__');
    }

    sort($controls['base']);
    sort($controls['stack']);

    $expectedControls = ['address_search', 'fn_pho', 'fn_text', 'gen', 'lat', 'ln_pho', 'ln_text', 'lng', 'mn_pho', 'mn_text', 'nick_pho', 'nick_text', 'paste', 'tp_pho', 'tp_text'];

    expect($controls['base'])->toBe($expectedControls)
        ->and($controls['stack'])->toBe($expectedControls);

    // Labels carry `for` on each control that has a visible label of its own.
    expect(collect($report['fieldLabels'])->where('container', 'base'))->not->toBeEmpty()
        ->and(collect($report['fieldLabels'])->where('container', 'stack'))->not->toBeEmpty();

    foreach ([...$report['labels'], ...$report['fieldLabels']] as $label) {
        expect($label['resolves'])->toBeTrue("label[for={$label['for']}] resolves to no element")
            ->and($label['sameContainer'])->toBeTrue("label[for={$label['for']}] points at a control in another container");
    }

    // The display-format select renders only for a resolved name: first and last
    // set, and each optional part answered. Answering them hides `gen` and the
    // middle-name and nickname controls, so it gets a second look of its own.
    //
    // One step at a time: each change patches the whole value, so two in one tick
    // build on the same snapshot and only the last survives.
    foreach (['__fn_text', '__ln_text'] as $suffix) {
        $page->script('document.querySelectorAll("[id$=\"'.$suffix.'\"]").forEach((input) => {'
            .' input.value = "Uhura"; input.dispatchEvent(new Event("input", { bubbles: true })); })');
        $page->wait(0.5);
    }

    foreach (['No Middle Name', 'No Nickname', 'No Generational Suffix'] as $switch) {
        $page->script('[...document.querySelectorAll("label")].filter((label) => label.textContent.trim() === "'.$switch.'")'
            .'.forEach((label) => label.parentElement.querySelector("[role=\"switch\"]").click())');
        $page->wait(0.5);
    }

    for ($waited = 0.0; $waited <= 15.0; $waited += 0.5) {
        if ($page->script('document.querySelectorAll("[id$=\"__df\"]").length') >= 2) {
            break;
        }

        $page->wait(0.5);
    }

    /** @var array{duplicates: list<string>, controls: list<array{id: string, container: string}>} $resolved */
    $resolved = json_decode(mustBeString($page->script(SUBFIELD_IDS_SCRIPT), 'the resolved-name report'), true, flags: JSON_THROW_ON_ERROR);
    $displayFormats = collect($resolved['controls'])->filter(fn (array $control): bool => str_ends_with($control['id'], '__df'));

    expect($resolved['duplicates'])->toBe([])
        ->and($displayFormats->pluck('container')->sort()->values()->all())->toBe(['base', 'stack']);

    assertNoAppJavaScriptErrors($page);
});
