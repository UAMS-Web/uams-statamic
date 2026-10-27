<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Stache;
use Statamic\Fields\BlueprintRepository;
use Statamic\Forms\Form;
use Statamic\Stache\Stores\Store;
use Tests\TestCase;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

/**
 * Proves a public-form Signature field round-trips server-side (UAMS-Web/uams-statamic-archive#674). The template's JS writes
 * either a base64 data URL (drawn) or the typed-name fallback into the hidden input; both are
 * plain strings, so a POST carrying either persists. An empty submission is nulled by
 * Signature::process(), so the form `required` rule rejects it — the server-side guard that stands
 * in for the HTML5 `required` a hidden input cannot carry.
 *
 * @command php artisan test --compact tests/Feature/Forms/SignatureSubmissionTest.php
 */
uses(TestCase::class);

$sigFormsDir = '';
$sigSubsDir = '';
$sigHandle = '';

beforeEach(function () use (&$sigFormsDir, &$sigSubsDir, &$sigHandle): void {
    withServerVariables(['REMOTE_ADDR' => '198.51.'.random_int(0, 255).'.'.random_int(1, 254)]);

    $sigHandle = 'signature_probe_'.str_replace('.', '', uniqid('', true));

    $sigFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($sigFormsDir);
    Config::set('statamic.forms.forms', $sigFormsDir);

    $sigSubsDir = storage_path('framework/testing/subs-'.uniqid('', true));
    File::ensureDirectoryExists($sigSubsDir);
    Config::set('statamic.forms.submissions', $sigSubsDir);
    /** @var Store $submissionsStore */
    $submissionsStore = Stache::store('form-submissions');
    $submissionsStore->directory($sigSubsDir);

    app(BlueprintRepository::class)->setFallback('forms.'.$sigHandle, fn () => Blueprint::makeFromFields([
        'signature' => ['type' => 'signature', 'display' => 'Signature', 'validate' => ['required']],
    ]));
});

afterEach(function () use (&$sigFormsDir, &$sigSubsDir): void {
    foreach ([$sigFormsDir, $sigSubsDir] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

/**
 * Count the submission YAML files written for the handle, bypassing the Stache cache.
 */
function signatureStoredCount(string $handle): int
{
    $base = Config::get('statamic.forms.submissions');
    $dir = (is_string($base) ? $base : '').'/'.$handle;

    return File::isDirectory($dir) ? count(File::files($dir)) : 0;
}

it('stores a drawn data-URL signature submission', function () use (&$sigHandle): void {
    $form = makeForm($sigHandle);
    $form->store(true);
    $form->save();

    post('/!/forms/'.$sigHandle, ['signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCA'])
        ->assertRedirect();

    expect(signatureStoredCount($sigHandle))->toBe(1);
});

it('stores a typed-name fallback signature submission', function () use (&$sigHandle): void {
    $form = makeForm($sigHandle);
    $form->store(true);
    $form->save();

    // What the accessible fallback writes to the hidden input when the user types + attests.
    post('/!/forms/'.$sigHandle, ['signature' => 'Jane Q. Public'])->assertRedirect();

    expect(signatureStoredCount($sigHandle))->toBe(1);
});

it('rejects an empty required signature server-side', function () use (&$sigHandle): void {
    $form = makeForm($sigHandle);
    $form->store(true);
    $form->save();

    // An empty signature is nulled by process(), so the required rule rejects it — nothing stored.
    post('/!/forms/'.$sigHandle, ['signature' => ''])
        ->assertSessionHasErrors('signature', null, 'form.'.$sigHandle);

    expect(signatureStoredCount($sigHandle))->toBe(0);
});
