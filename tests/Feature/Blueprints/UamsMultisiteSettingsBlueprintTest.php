<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Validator;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Locks the editor-facing fields wired into the `uams_multisite_settings`
 * global blueprint:
 *   - `UAMS-Web/uams-statamic-archive#51` — `global_header_site_name` controls whether the site name shows
 *     in the header sub-brand (`_brand.antlers.html`).
 *   - `UAMS-Web/uams-statamic-archive#187` — `discourage_indexing` emits a site-wide robots noindex/nofollow
 *     in the layout head when enabled.
 *   - `UAMS-Web/uams-statamic-archive#1351` — the footer information fields (`tax_id`, `display_tax_id`,
 *     `footer_action_link`), all gated by the footer-address `subject`
 *     condition so they only appear on entity-level sites.
 *
 * The blueprint lives in `resources/blueprints/` (not `content/`), so it
 * resolves without the submodule.
 *
 * @command  php artisan test --compact tests/Feature/Blueprints/UamsMultisiteSettingsBlueprintTest.php
 */
beforeEach(function () {
    $bp = Blueprint::find('globals.uams_multisite_settings');
    expect($bp)->not->toBeNull();

    /** @var Statamic\Fields\Blueprint $bp */
    $this->bp = $bp;
    /** @var Collection<string, Field> $fields */
    $fields = $bp->fields()->all();
    $this->fields = $fields;
    $this->config = fn (string $handle): array => $fields->get($handle)?->config() ?? [];
});

it('exposes a default-on, localizable header site-name toggle (#51)', function () {
    /** @var Collection<string, Field> $fields */
    $fields = $this->fields;
    /** @var Closure(string): array<string, mixed> $config */
    $config = $this->config;

    /** @var Field $field */
    $field = $fields->get('global_header_site_name');

    expect($field)->not->toBeNull();
    expect($field->type())->toBe('true_false');
    // Default on so existing sites keep showing the site name; the template
    // guard (`!= false`) only suppresses an explicit opt-out.
    expect($config('global_header_site_name')['default'])->toBeTrue();
    expect($config('global_header_site_name')['localizable'])->toBeTrue();
});

it('exposes a default-off, localizable discourage-indexing toggle (#187)', function () {
    /** @var Collection<string, Field> $fields */
    $fields = $this->fields;
    /** @var Closure(string): array<string, mixed> $config */
    $config = $this->config;

    /** @var Field $field */
    $field = $fields->get('discourage_indexing');

    expect($field)->not->toBeNull();
    expect($field->type())->toBe('true_false');
    // Default off so public production sites emit no robots meta; the head only
    // emits noindex/nofollow when an editor explicitly opts the site out.
    expect($config('discourage_indexing')['default'])->toBeFalse();
    expect($config('discourage_indexing')['localizable'])->toBeTrue();
});

it('exposes a localizable tax ID defaulting to the university EIN (#1351)', function () {
    /** @var Collection<string, Field> $fields */
    $fields = $this->fields;
    /** @var Closure(string): array<string, mixed> $config */
    $config = $this->config;

    /** @var Field $field */
    $field = $fields->get('tax_id');

    expect($field)->not->toBeNull();
    expect($field->type())->toBe('text');
    // Localizable so a site whose footer names a different legal entity (the
    // giving site names the Foundation that receives the gifts) can override it.
    expect($config('tax_id')['localizable'])->toBeTrue();
    expect($config('tax_id')['default'])->toBe('71-6046242');
});

it('exposes a default-off, localizable tax-ID display toggle (#1351)', function () {
    /** @var Collection<string, Field> $fields */
    $fields = $this->fields;
    /** @var Closure(string): array<string, mixed> $config */
    $config = $this->config;

    /** @var Field $field */
    $field = $fields->get('display_tax_id');

    expect($field)->not->toBeNull();
    expect($field->type())->toBe('true_false');
    // Default off: the value is inherited everywhere, so each site opts into
    // *displaying* it rather than the value being duplicated per site.
    expect($config('display_tax_id')['default'])->toBeFalse();
    expect($config('display_tax_id')['localizable'])->toBeTrue();
});

it('exposes a footer action link reusing the shared text link group (#1351)', function () {
    /** @var Collection<string, Field> $fields */
    $fields = $this->fields;

    /** @var Field $field */
    $field = $fields->get('footer_action_link');

    expect($field)->not->toBeNull();
    // Reuses `uamsweb_common_link.text_link_group` (text + url + new window),
    // the same group the page-builder button/CTA blocks import.
    expect($field->type())->toBe('group');

    $config = $field->config();
    $groupFields = is_array($config['fields'] ?? null) ? $config['fields'] : [];

    $handles = [];

    foreach ($groupFields as $groupField) {
        if (is_array($groupField) && is_string($groupField['handle'] ?? null)) {
            $handles[] = $groupField['handle'];
        }
    }

    expect($handles)->toContain('text', 'url', 'new_window');
});

it('keeps the global savable when the optional footer action link is left empty (#1351)', function () {
    /** @var Statamic\Fields\Blueprint $bp */
    $bp = $this->bp;

    // Regression guard. The CP save path (`GlobalVariablesController::update`)
    // validates the whole blueprint, and Statamic does not strip rules for
    // fields hidden by an `if:` condition — so a single unconditionally
    // `required` subfield anywhere in this global makes the *entire* global
    // unsavable on every site. The shared `text_link_group` marks all three of
    // its subfields required, which is only safe inside a replicator set (the
    // set exists only once an editor adds it); `footer_action_link` is the
    // first standalone use, so it imports `optional_text_link_group` instead.
    $validator = $bp->fields()->validator();
    $rules = $validator instanceof Validator ? $validator->rules() : [];
    $rules = is_array($rules) ? $rules : [];

    $checkedRules = 0;

    foreach ($rules as $handle => $fieldRules) {
        if (! is_string($handle) || ! str_starts_with($handle, 'footer_action_link')) {
            continue;
        }

        $checkedRules++;

        expect(is_array($fieldRules) ? $fieldRules : [])->not->toContain('required');
    }

    // Guards the guard: if the handle ever changes, this test must not silently
    // pass by checking nothing.
    expect($checkedRules)->toBeGreaterThan(0);
});

it('gates every footer information field behind the footer-address subject condition (#1351)', function () {
    /** @var Closure(string): array<string, mixed> $config */
    $config = $this->config;

    // Same `if:` as the footer address customization, so the fields only appear
    // on root / major-entity / sub-entity sites.
    foreach (['tax_id', 'display_tax_id', 'footer_action_link'] as $handle) {
        expect($config($handle)['if'] ?? null)
            ->toBe(['subject' => 'contains_any root,major_entity,sub_entity'], $handle);
    }
});
