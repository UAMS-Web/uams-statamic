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
 | Browser proof of which Side-by-Side block fields the Control Panel shows for each
 | text layout (UAMS-Web/uams-statamic-archive#2761).
 |
 | `blockquote_with_citation` is a prefix of `blockquote_with_citation_and_link`, so a
 | condition read as a substring test can show or hide a field in one of them by
 | accident. SideBySideBlockTest pins the condition strings and evaluates them both
 | ways in PHP; only the Control Panel's own evaluator, running in a browser, says
 | what an editor sees. This drives it: an entry editor whose blueprint imports the
 | block's fieldset, each layout selected in turn, and each conditional field read as
 | shown or hidden.
 |
 | The fieldset is imported at the top level rather than inside the page builder's
 | replicator: the conditions name `text_layout` and `button_text` within the same
 | fields, which is the same scope a replicator set gives them.
 |
 | Hidden fields stay in the DOM with `display: none`, so visibility is read from the
 | live layout (`offsetParent`), never from presence.
 |
 | The expected sets are written out by hand below, not derived from the YAML, so a
 | wrong condition cannot agree with itself.
 |
 | @command vendor/bin/pest tests/Browser/SideBySideLayoutFieldVisibilityTest.php
 */

const SXS_VISIBILITY_COLLECTION = 'sxs_visibility';

/**
 * Every field in the block whose visibility depends on a condition.
 *
 * @var list<string>
 */
const SXS_CONDITIONAL_FIELDS = [
    'body_body_only',
    'body_with_button',
    'body_link_list',
    'body_simple_list',
    'link_list',
    'include_more',
    'simple_list',
    'quote_text',
    'quote_text_with_link',
    'speaker_name',
    'speaker_title',
    'cta',
    'button_text',
    'button_url',
    'button_target',
    'button_link_description',
];

/**
 * The fields an editor should see for a layout, given whether the button text and the
 * speaker name hold a value.
 *
 * @return list<string>
 */
function sxsExpectedVisible(string $layout, bool $hasButtonText, bool $hasSpeakerName): array
{
    $fields = match ($layout) {
        'body-only' => ['body_body_only'],
        'paragraph_and_button' => ['body_with_button'],
        'link-list' => ['body_link_list', 'link_list', 'include_more'],
        'paragraph_and_simple_list' => ['body_simple_list', 'simple_list'],
        'simple_list_only' => ['simple_list'],
        'blockquote_with_citation' => ['quote_text', 'speaker_name'],
        'blockquote_with_citation_and_link' => ['quote_text_with_link', 'speaker_name'],
        default => throw new InvalidArgumentException("No expected fields for layout {$layout}."),
    };

    // The two layouts without a button are Paragraph only and the plain blockquote
    // (UAMS-Web/uams-statamic-archive#2757, UAMS-Web/uams-statamic-archive#2759). The other three button fields need button text as well.
    $hasButton = ! in_array($layout, ['body-only', 'blockquote_with_citation'], true);

    if ($hasButton) {
        $fields = [...$fields, 'cta', 'button_text'];

        if ($hasButtonText) {
            $fields = [...$fields, 'button_url', 'button_target', 'button_link_description'];
        }
    }

    if (in_array('speaker_name', $fields, true) && $hasSpeakerName) {
        $fields[] = 'speaker_title';
    }

    sort($fields);

    return $fields;
}

/**
 * The conditional fields currently shown, read from the live layout.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from navigate().
 * @return list<string>
 */
function sxsVisibleFields($page): array
{
    $json = $page->script('(() => JSON.stringify('.json_encode(SXS_CONDITIONAL_FIELDS).'.filter((handle) => {'
        .' const label = document.querySelector(\'label[for="field_base_\' + handle + \'"]\');'
        .' if (!label) { return false; }'
        .' const group = label.closest(".form-group");'
        .' return group !== null && group.offsetParent !== null; })))()');

    /** @var list<string> $visible */
    $visible = json_decode(is_string($json) ? $json : '[]', true);
    sort($visible);

    return $visible;
}

/**
 * Every conditional field the editor rendered, shown or not. A field missing here has
 * no label to read, so its visibility could not be checked at all.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from navigate().
 * @return list<string>
 */
function sxsRenderedFields($page): array
{
    $json = $page->script('(() => JSON.stringify('.json_encode(SXS_CONDITIONAL_FIELDS).'.filter((handle) =>'
        .' document.querySelector(\'label[for="field_base_\' + handle + \'"]\') !== null)))()');

    /** @var list<string> $rendered */
    $rendered = json_decode(is_string($json) ? $json : '[]', true);

    return $rendered;
}

/**
 * Wait for the shown fields to settle on the expected set, then assert it. Conditions
 * re-evaluate reactively after a click or a keystroke, so a single read could race
 * the update; a miss still fails after the bound, with the two sets in the message.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from navigate().
 * @param  list<string>  $expected
 */
function sxsAssertVisible($page, array $expected, string $when): void
{
    $visible = [];

    for ($waited = 0.0; $waited <= 8.0; $waited += 0.2) {
        $visible = sxsVisibleFields($page);

        if ($visible === $expected) {
            break;
        }

        $page->wait(0.2);
    }

    expect($visible)->toBe($expected, $when);
}

/**
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from navigate().
 */
function sxsSelectLayout($page, string $layout): void
{
    // The radio button sits under its label's click overlay, so the label is what an
    // editor clicks. It is found by the button's exact value: matching the label text
    // would also catch the with-link layout, whose label starts with the plain one's.
    $id = $page->script('(() => { const button = document.querySelector(\'button[role="radio"][value="'.$layout.'"]\');'
        .' return button ? button.id : ""; })()');

    expect($id)->toBeString()->not->toBe('', "the {$layout} radio button");

    /** @var string $id */
    $page->click('label[for="'.$id.'"]');
}

/**
 * Snooze Statamic's trial-mode licensing alert when it opens. It is a modal over the
 * publish form, so every click on a field behind it times out.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from navigate().
 */
function sxsDismissLicensingAlert($page): void
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

beforeEach(function (): void {
    $this->cpEmail = 'sxs-visibility-'.uniqid().'@example.test';
    $this->cpPassword = 'sxs-visibility-'.uniqid();
    $this->cpUser = seedCpSuperUser($this->cpEmail, $this->cpPassword);

    $collection = Collection::make(SXS_VISIBILITY_COLLECTION);
    $collection->title('Side-by-Side Visibility');
    $collection->save();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::make();
    $blueprint->setContents(['tabs' => ['main' => ['sections' => [[
        'fields' => [['import' => 'uamsweb_block_image_side_by_side']],
    ]]]]]);
    $blueprint->setHandle(SXS_VISIBILITY_COLLECTION);
    $blueprint->setNamespace('collections.'.SXS_VISIBILITY_COLLECTION);
    $blueprint->save();

    Stache::refresh();
});

afterEach(function (): void {
    Collection::find(SXS_VISIBILITY_COLLECTION)?->delete();
    /** @var Statamic\Fields\Blueprint|null $blueprint */
    $blueprint = Blueprint::find('collections.'.SXS_VISIBILITY_COLLECTION.'.'.SXS_VISIBILITY_COLLECTION);
    $blueprint?->delete();
    /** @var User|null $cpUser */
    $cpUser = $this->cpUser;
    $cpUser?->delete();
    Stache::refresh();
});

it('shows each Side-by-Side layout its own fields in the Control Panel, the two blockquote layouts apart (#2761)', function (): void {
    /** @var string $email */
    $email = $this->cpEmail;
    /** @var string $password */
    $password = $this->cpPassword;

    $page = loginToCp($email, $password)
        ->navigate('/cp/collections/'.SXS_VISIBILITY_COLLECTION.'/entries/create/default');

    waitToSee($page, 'Text Configuration', 20.0);
    sxsDismissLicensingAlert($page);

    // Every conditional field must have rendered a label, or its visibility is unread.
    expect(sxsRenderedFields($page))->toBe(SXS_CONDITIONAL_FIELDS);

    // Paragraph only is the default.
    sxsAssertVisible($page, sxsExpectedVisible('body-only', false, false), 'body-only, on load');

    foreach (['link-list', 'paragraph_and_simple_list', 'simple_list_only', 'paragraph_and_button'] as $layout) {
        sxsSelectLayout($page, $layout);
        sxsAssertVisible($page, sxsExpectedVisible($layout, false, false), $layout);
    }

    // Button text shows the button's other three fields.
    $page->fill('#field_base_button_text', 'Learn More');
    sxsAssertVisible($page, sxsExpectedVisible('paragraph_and_button', true, false), 'paragraph_and_button, with button text');

    // The plain blockquote hides all five button fields, though the button text holds
    // a value, and shows its own quote, not the with-link one.
    sxsSelectLayout($page, 'blockquote_with_citation');
    sxsAssertVisible($page, sxsExpectedVisible('blockquote_with_citation', true, false), 'blockquote_with_citation');

    // The speaker title waits for a speaker name.
    $page->fill('#field_base_speaker_name', 'Michael Miller, DC, MS');
    sxsAssertVisible($page, sxsExpectedVisible('blockquote_with_citation', true, true), 'blockquote_with_citation, with a speaker name');

    // The with-link blockquote shows all five button fields and its own quote.
    sxsSelectLayout($page, 'blockquote_with_citation_and_link');
    sxsAssertVisible($page, sxsExpectedVisible('blockquote_with_citation_and_link', true, true), 'blockquote_with_citation_and_link');

    $page->fill('#field_base_speaker_name', '');
    sxsAssertVisible($page, sxsExpectedVisible('blockquote_with_citation_and_link', true, false), 'blockquote_with_citation_and_link, speaker name cleared');

    // Back through the other layouts with button text set: each still shows only its own.
    foreach (['blockquote_with_citation', 'body-only', 'link-list', 'paragraph_and_simple_list', 'simple_list_only'] as $layout) {
        sxsSelectLayout($page, $layout);
        sxsAssertVisible($page, sxsExpectedVisible($layout, true, false), $layout.', with button text');
    }

    assertNoAppJavaScriptErrors($page);
});
