<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests the public-form signature template (UAMS-Web/uams-statamic-archive#674). `signature.antlers.html`
 * renders a canvas capture surface (driven client-side by the Alpine
 * `signatureField` component) plus an accessible typed-name + attestation
 * fallback, writing the submitted value into a hidden input named for the field
 * handle. Client-side canvas capture (touch/mouse drawing) is out of scope for a
 * server render test and is verified in-browser.
 *
 * @command php artisan test --compact tests/Feature/Views/FormsFieldsSignatureTest.php
 */

/**
 * Render the signature form template against a hand-built context.
 *
 * @param  array<string, mixed>  $overrides
 */
function renderSignatureField(array $overrides = []): string
{
    return (string) Antlers::parse(
        File::get(resource_path('views/vendor/statamic/forms/fields/signature.antlers.html')),
        [
            'handle' => 'signature',
            'id' => 'form-signature',
            'value' => '',
            'validate' => [],
            'error' => null,
            ...$overrides,
        ],
        true,
    );
}

it('renders the canvas capture surface bound to the Alpine signature component', function () {
    $out = renderSignatureField();

    expect($out)->toContain('x-data="signatureField()"');
    expect($out)->toContain('<canvas');
    expect($out)->toContain('x-ref="canvas"');
});

it('submits through a hidden input named for the field handle', function () {
    $out = renderSignatureField();

    expect($out)->toContain('type="hidden"');
    expect($out)->toContain('name="signature"');
    expect($out)->toContain(':value="value"');
});

it('renders the accessible typed-name and attestation fallback', function () {
    $out = renderSignatureField();

    // Typed-name input + attestation checkbox, both label-associated
    expect($out)->toContain('id="form-signature-typed"');
    expect($out)->toContain('x-model="typedName"');
    expect($out)->toContain('id="form-signature-attest"');
    expect($out)->toContain('type="checkbox"');
    expect($out)->toContain('x-model="attested"');
    // Mode toggles between draw and type
    expect($out)->toContain('useTypeMode()');
    expect($out)->toContain('useDrawMode()');
});

it('keeps the typed fallback hidden until Alpine toggles it', function () {
    // The type-mode block ships display:none so it does not flash before Alpine inits.
    $out = renderSignatureField();

    expect($out)->toContain('x-show="mode === \'type\'"');
    expect($out)->toContain('style="display: none;"');
});

it('marks the canvas invalid and wires aria-invalid when the field has an error', function () {
    $out = renderSignatureField(['error' => 'The signature field is required.']);

    expect($out)->toContain('is-invalid');
    expect($out)->toContain('aria-invalid="true"');
    expect($out)->toContain('aria-describedby="form-signature-error"');
});

it('preserves a prior submission value for re-render after a validation error', function () {
    // The server-rendered value seeds the hidden input; Alpine reads it on init.
    $out = renderSignatureField(['value' => 'Jane Q. Public']);

    expect($out)->toContain('value="Jane Q. Public"');
});
