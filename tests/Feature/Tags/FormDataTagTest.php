<?php

declare(strict_types=1);

use App\Tags\FormData;
use Illuminate\Support\Facades\Config;
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
 * Mutation: 100% on App\Tags\FormData (69 mutants, --covered-only).
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
