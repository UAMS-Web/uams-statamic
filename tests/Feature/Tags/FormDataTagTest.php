<?php

declare(strict_types=1);

use App\Tags\FormData;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Returns the isolated forms directory for the current test.
 *
 * Replaces the PHPUnit instance property `$this->formsDir`, which is not
 * available on Pest's `TestCall`. The value is established in `beforeEach`.
 */
function formDataTagFormsDir(?string $value = null): string
{
    static $dir = '';

    if ($value !== null) {
        $dir = $value;
    }

    return $dir;
}

/**
 * @covers \App\Tags\FormData
 *
 * Mutation: 100% on App\Tags\FormData (50 mutants, without --covered-only, on Linux), measured
 * for UAMS-Web/uams-statamic#805. One equivalent mutant carries `@pest-mutate-ignore` with its reason.
 *
 * @command  php artisan test --compact tests/Feature/Tags/FormDataTagTest.php
 */
beforeEach(function () {
    // Point the forms repository at an isolated directory so YAML reads are self-contained.
    $formsDir = storage_path(
        'framework/testing/form-data-'.uniqid(
            '',
            true
        )
    );
    formDataTagFormsDir($formsDir);
    File::ensureDirectoryExists($formsDir);
    Config::set(
        'statamic.forms.forms',
        $formsDir
    );
});

afterEach(function () {
    // Remove the isolated forms directory.
    $formsDir = formDataTagFormsDir();
    if ($formsDir !== '' && File::isDirectory($formsDir)) {
        File::deleteDirectory($formsDir);
    }
});

it('returns empty without handle', function () {
    $tag = new FormData;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('');
});

it('returns empty when yaml missing', function () {
    $tag = new FormData;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'missing',
    ]);

    expect($tag->index())->toBe('');
});

it('returns scalar key and full array', function () {
    File::put(
        formDataTagFormsDir().'/contact.yaml',
        "heading: Hello\ndescription: There\nextra: 1\n"
    );

    $tag = new FormData;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'contact',
        'key' => 'heading',
    ]);

    expect($tag->index())->toBe('Hello');

    $tag->setParameters([
        'handle' => 'contact',
    ]);
    $all = $tag->index();
    expect($all)->toBeArray();
    expect(data_get($all, 'heading'))->toBe('Hello');
});

it('reads heading and description helpers', function () {
    File::put(
        formDataTagFormsDir().'/signup.yaml',
        "heading: Join\n"
    );

    $tag = new FormData;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'signup',
    ]);

    expect($tag->heading())->toBe('Join');
    expect($tag->description())->toBe('No description property found');
});

it('errors from heading when handle missing', function () {
    $tag = new FormData;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->heading())->toBe('No handle provided');
});

it('returns empty heading and description when yaml missing and outputs contain no paths', function () {
    $tag = new FormData;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'missing-form-yaml',
    ]);

    $heading = $tag->heading();
    $description = $tag->description();

    expect($heading)->toBe('');
    expect($description)->toBe('');
    expect($heading)->not->toContain(formDataTagFormsDir());
    expect($description)->not->toContain(formDataTagFormsDir());
});

/**
 * Build a form_data tag with the given parameters and no context.
 *
 * @param  array<string, mixed>  $params  The tag parameters
 * @return FormData The tag
 */
function formDataTag(array $params): FormData
{
    $tag = new FormData;
    $tag->setContext([]);
    $tag->setParameters($params);

    return $tag;
}

it('returns empty without handle even when a nameless form file exists', function () {
    // An empty handle must not build the path `<forms>/.yaml` and read it.
    File::put(formDataTagFormsDir().'/.yaml', "heading: Nameless\n");

    expect(formDataTag(['handle' => ''])->index())->toBe('');
});

it('reads a form named by a non-string scalar handle', function () {
    File::put(formDataTagFormsDir().'/5.yaml', "heading: Five\n");

    expect(formDataTag(['handle' => 5, 'key' => 'heading'])->index())->toBe('Five');
});

it('returns empty for a key the form does not set', function () {
    File::put(formDataTagFormsDir().'/contact.yaml', "heading: Hello\n");

    expect(formDataTag(['handle' => 'contact', 'key' => 'absent'])->index())->toBe('');
});

it('returns an array value for a key whole', function () {
    File::put(formDataTagFormsDir().'/contact.yaml', "options:\n  a: One\n  b: Two\n");

    expect(formDataTag(['handle' => 'contact', 'key' => 'options'])->index())->toBe(['a' => 'One', 'b' => 'Two']);
});

it('returns a scalar value for a key as a string', function () {
    File::put(formDataTagFormsDir().'/contact.yaml', "extra: 1\n");

    expect(formDataTag(['handle' => 'contact', 'key' => 'extra'])->index())->toBe('1');
});

it('reads a key given as a float by its string form', function () {
    // An array offset of 1.5 would truncate to 1; the key is read as '1.5'.
    File::put(formDataTagFormsDir().'/contact.yaml', "'1.5': Decimal\n1: Integer\n");

    expect(formDataTag(['handle' => 'contact', 'key' => 1.5])->index())->toBe('Decimal');
});

it('reads the description, and casts a scalar heading or description to a string', function () {
    File::put(formDataTagFormsDir().'/survey.yaml', "heading: 5\ndescription: Tell us\n");

    $tag = formDataTag(['handle' => 'survey']);

    expect($tag->heading())->toBe('5')
        ->and($tag->description())->toBe('Tell us');

    File::put(formDataTagFormsDir().'/survey.yaml', "heading: Survey\ndescription: 7\n");

    expect($tag->description())->toBe('7');
});

it('falls back to the missing-property text for a heading or description that is not a scalar', function () {
    File::put(formDataTagFormsDir().'/survey.yaml', "heading:\n  - a\ndescription:\n  - b\n");

    $tag = formDataTag(['handle' => 'survey']);

    expect($tag->heading())->toBe('No heading property found')
        ->and($tag->description())->toBe('No description property found');
});

it('errors from description when handle missing', function () {
    expect(formDataTag([])->description())->toBe('No handle provided');
});

it('logs a missing form file from heading and description with its handle and path, and not from the tag itself', function (mixed $handle, string $loggedHandle) {
    Event::fake([MessageLogged::class]);

    $tag = formDataTag(['handle' => $handle]);

    expect($tag->index())->toBe('');
    Event::assertNotDispatched(MessageLogged::class);

    $tag->heading();
    $tag->description();

    $expectedContext = [
        'handle' => $loggedHandle,
        'path' => formDataTagFormsDir().'/'.$loggedHandle.'.yaml',
    ];

    Event::assertDispatchedTimes(MessageLogged::class, 2);
    Event::assertDispatched(
        MessageLogged::class,
        fn (MessageLogged $event): bool => $event->level === 'warning'
            && $event->message === 'FormData tag: form YAML file not found.'
            && $event->context === $expectedContext
    );
})->with([
    'string handle' => ['missing-form-yaml', 'missing-form-yaml'],
    'integer handle' => [5, '5'],
]);

it('finds no form for a handle that is not a scalar, even when a nameless form file exists', function () {
    // Before #805 a non-scalar handle narrowed to '' and read `<forms>/.yaml`.
    File::put(formDataTagFormsDir().'/.yaml', "heading: Nameless\n");
    Event::fake([MessageLogged::class]);

    $tag = formDataTag(['handle' => ['contact']]);

    expect($tag->index())->toBe('')
        ->and($tag->heading())->toBe('');

    Event::assertDispatchedTimes(MessageLogged::class, 1);
    Event::assertDispatched(
        MessageLogged::class,
        fn (MessageLogged $event): bool => $event->context === ['handle' => ['contact'], 'path' => null]
    );
});

it('returns empty for a key that is not a scalar', function () {
    // Before #805 a non-scalar key narrowed to '' and read the form's '' property.
    File::put(formDataTagFormsDir().'/contact.yaml', "'': Nameless\nheading: Hello\n");

    expect(formDataTag(['handle' => 'contact', 'key' => ['heading']])->index())->toBe('');
});

it('finds no form when the forms directory is not configured as a string', function () {
    File::put(formDataTagFormsDir().'/contact.yaml', "heading: Hello\n");
    Config::set('statamic.forms.forms', [formDataTagFormsDir()]);

    $tag = formDataTag(['handle' => 'contact', 'key' => 'heading']);

    expect($tag->index())->toBe('')
        ->and($tag->heading())->toBe('');
});
