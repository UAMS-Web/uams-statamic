<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Fields;
use Statamic\Fields\Validator;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the UAMS Bio page-builder set (UAMS-Web/uams-statamic-archive#1169): a clone of the Content
 * block with a right-aligned image and a sets-less text field (no inline "add
 * media"). A Bio with no image renders the text with no empty image slot.
 *
 * @command  php artisan test --compact tests/Feature/Views/BioBlockTest.php
 */
beforeEach(fn () => Blink::flush());

/**
 * The Bio set's fields, keyed by handle.
 *
 * @return array<string, array<string, mixed>>
 */
function bioBlockFields(): array
{
    /** @var non-empty-string $path */
    $path = realpath(__DIR__.'/../../../resources/fieldsets/uamsweb_block_bio.yaml');

    /** @var array{fields: array<int, array<string, mixed>>} $fieldset */
    $fieldset = Yaml::parseFile($path);

    $keyed = [];
    foreach ($fieldset['fields'] as $field) {
        /** @var string $handle */
        $handle = $field['handle'];
        $keyed[$handle] = $field;
    }

    return $keyed;
}

/**
 * The set handles registered in the page-builder replicator, flattened.
 *
 * @return array<int, string>
 */
function bioPageBuilderSetHandles(): array
{
    /** @var non-empty-string $path */
    $path = realpath(__DIR__.'/../../../resources/fieldsets/page_builder.yaml');

    /** @var array{fields: array{0: array{field: array{sets: array<string, array{sets?: array<string, mixed>}>}}}} $pb */
    $pb = Yaml::parseFile($path);

    $handles = [];
    foreach ($pb['fields'][0]['field']['sets'] as $group) {
        $handles = array_merge($handles, array_keys($group['sets'] ?? []));
    }

    return $handles;
}

/**
 * Render the Bio partial with the given block data.
 *
 * @param  array<string, mixed>  $block
 */
function renderBioBlock(array $block): string
{
    /** @var int $render */
    static $render = 0;
    $render++;

    return (string) Antlers::parse(
        '{{ blocks scope="block" }}{{ partial:page_builder/uamsweb_block_bio }}{{ /blocks }}'
        ."\n{{# render-{$render} #}}",
        ['blocks' => [$block]],
    );
}

/**
 * A valid Bio block (Bard body as pre-augmented text node), overridable.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bioBlock(array $overrides = []): array
{
    return array_merge([
        'block_heading' => 'Dr. Example',
        'hide_block_heading' => false,
        'body' => [['type' => 'text', 'text' => '<p>BioProse</p>']],
        'background_color' => 'bg-white',
    ], $overrides);
}

it('registers the UAMS Bio set in the page builder (#1169)', function (): void {
    expect(bioPageBuilderSetHandles())->toContain('uamsweb_block_bio');
});

it('has a single image field and a sets-less body (no inline add-media)', function (): void {
    $fields = bioBlockFields();

    // The body reuses the sets-less article Bard, so it has no "add set" control.
    expect($fields['body']['field'])->toBe('uamsweb_common.article')
        ->and($fields['image']['field'])->toBe('uamsweb_common_images.image');
});

it('renders the image right-aligned beside the body text', function (): void {
    $html = renderBioBlock(bioBlock(['image' => ['url' => '/img/portrait.jpg', 'alt' => 'Portrait']]));

    expect($html)->toContain('bio-image')
        ->and($html)->toContain('float-md-right')
        ->and($html)->toContain('/img/portrait.jpg')
        ->and($html)->toContain('BioProse');
});

it('renders the text with no empty image slot when no image is set', function (): void {
    $html = renderBioBlock(bioBlock());

    expect($html)->toContain('BioProse')
        ->and($html)->toContain('<section')
        ->and($html)->not->toContain('bio-image')
        ->and($html)->not->toContain('<img');
});

/*
|--------------------------------------------------------------------------
| Optional Person connection (UAMS-Web/uams-statamic-archive#1418)
|--------------------------------------------------------------------------
|
| Single source of truth: when `bio_person` is set the manual Image/Body fields
| are hidden (fieldset conditions) and the block sources the portrait (with a
| `portrait_wide` fallback) and the `bio_field`-selected biography from the person.
*/

/**
 * A connected-person Bio block: no manual image/body, sourcing from `bio_person`.
 *
 * @param  array<string, mixed>  $person
 * @return array<string, mixed>
 */
function bioConnectedBlock(array $person, string $bioField = 'bio'): array
{
    return bioBlock([
        'body' => null,
        'image' => null,
        'bio_person' => [$person],
        'bio_field' => $bioField,
    ]);
}

it('exposes the optional Person connection and a 12-variant bio selector, hiding the manual fields when connected', function (): void {
    $fields = bioBlockFields();

    /** @var array<string, mixed> $bioPersonField */
    $bioPersonField = $fields['bio_person']['field'];
    /** @var array<string, mixed> $bioFieldSelect */
    $bioFieldSelect = $fields['bio_field']['field'];
    /** @var array<string, mixed> $bioFieldOptions */
    $bioFieldOptions = $bioFieldSelect['options'];
    /** @var array<string, mixed> $imageConfig */
    $imageConfig = $fields['image']['config'];
    /** @var array<string, mixed> $bodyConfig */
    $bodyConfig = $fields['body']['config'];

    // Optional single Person entries connection.
    expect($bioPersonField['type'])->toBe('entries')
        ->and($bioPersonField['collections'])->toBe(['people'])
        ->and($bioPersonField['max_items'])->toBe(1);

    // A hardcoded select of the 12 known bio* handles, defaulting to the general bio,
    // shown only when a person is connected.
    expect($bioFieldSelect['type'])->toBe('select')
        ->and($bioFieldSelect['default'])->toBe('bio')
        ->and(array_keys($bioFieldOptions))->toBe([
            'bio', 'bio_short',
            'bio_institutional', 'bio_institutional_short',
            'bio_clinical', 'bio_clinical_short',
            'bio_academic', 'bio_academic_short',
            'bio_research', 'bio_research_short',
            'bio_creator', 'bio_creator_short',
        ])
        ->and($bioFieldSelect['if'])->toBe(['bio_person' => 'not empty']);

    // The manual image + body are hidden when a person is connected (single source of truth).
    expect($imageConfig['if'])->toBe(['bio_person' => 'empty'])
        ->and($bodyConfig['if'])->toBe(['bio_person' => 'empty']);
});

it('sources the selected biography and portrait from the connected person', function (): void {
    $html = renderBioBlock(bioConnectedBlock([
        'bio' => '<p>General bio prose</p>',
        'bio_clinical' => '<p>Clinical bio prose</p>',
        'portrait' => [['url' => '/img/portrait.jpg', 'alt' => 'Dr. Example']],
    ], 'bio_clinical'));

    // The selected (clinical) bio + the person's portrait render; the unselected bio does not.
    expect($html)->toContain('Clinical bio prose')
        ->and($html)->toContain('/img/portrait.jpg')
        ->and($html)->toContain('bio-image')
        ->and($html)->not->toContain('General bio prose');
});

it('falls back to the wide portrait when the person has no standard portrait', function (): void {
    $html = renderBioBlock(bioConnectedBlock([
        'bio' => '<p>General bio prose</p>',
        'portrait_wide' => [['url' => '/img/portrait-wide.jpg', 'alt' => 'Dr. Example']],
    ], 'bio'));

    expect($html)->toContain('General bio prose')
        ->and($html)->toContain('/img/portrait-wide.jpg')
        ->and($html)->toContain('bio-image');
});

it('renders no empty slot when the connected person\'s selected biography is empty', function (): void {
    $html = renderBioBlock(bioConnectedBlock([
        'bio_clinical' => '',
        'portrait' => [['url' => '/img/portrait.jpg', 'alt' => 'Dr. Example']],
    ], 'bio_clinical'));

    // The block still renders (person connected), the portrait shows, but there is no
    // empty/broken bio slot for the missing biography.
    expect($html)->toContain('<section')
        ->and($html)->toContain('/img/portrait.jpg')
        ->and($html)->not->toContain('module-body"></div>');
});

it('preserves Bard augmentation when selecting a real person\'s biography by handle', function (): void {
    if (! Collection::find('people')) {
        ContentSubmodule::reportMissing('people collection');
    }

    // The risk synthetic string fixtures can't catch: the person's `bio*` fields are
    // Bard, so the handle-selecting `switch` must yield augmented HTML, never the raw
    // ProseMirror nodes. Exercise the partial's exact selection on a real augmented
    // person (the portrait path is covered separately — augmenting it needs asset infra).
    /** @var Statamic\Entries\Entry $person */
    $person = Entry::make();
    $person->collection('people');
    $person->slug('zz-bio-block-aug');
    $person->data([
        'title' => 'Dr Augmented',
        'bio_clinical' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Augmented clinical bio']]]],
    ]);
    $person->saveQuietly();

    try {
        /** @var array<string, mixed> $scope */
        $scope = $person->toAugmentedArray();
        $scope['bio_field'] = 'bio_clinical';
        $out = (string) Antlers::parse(
            "{{ \$person_bio = switch( ( bio_field == 'bio' ) => bio, ( bio_field == 'bio_clinical' ) => bio_clinical ) }}"
            .'{{ if $person_bio }}{{ $person_bio }}{{ /if }}',
            $scope,
        );
    } finally {
        $person->delete();
    }

    expect($out)->toContain('<p>Augmented clinical bio</p>')
        ->and($out)->not->toContain('"type":"paragraph"');
})->group('serial');

it('requires the manual body only when no person is connected', function (): void {
    // A `required` field hidden by conditions still fails Statamic validation, so the
    // body requirement is conditional (`required_without:bio_person`) — otherwise an
    // editor could never save a person-connected bio (the body is hidden and empty).
    $errors = function (array $values): array {
        try {
            /** @var Statamic\Fields\Fieldset $fieldset */
            $fieldset = Fieldset::find('uamsweb_block_bio');
            /** @var Fields $fields */
            $fields = $fieldset->fields()->addValues($values);
            /** @var Validator $validator */
            $validator = $fields->validator();
            $validator->validate();

            return [];
        } catch (ValidationException $e) {
            return $e->errors();
        }
    };
    $base = ['block_heading' => 'Heading', 'hide_block_heading' => false, 'background_color' => 'bg-white'];

    // Person connected → the hidden manual body is not required.
    expect($errors($base + ['bio_person' => ['pid'], 'bio_field' => 'bio']))->not->toHaveKey('body');
    // Manual bio with no body → body is required.
    expect($errors($base))->toHaveKey('body');
    // Manual bio with a body → valid.
    expect($errors($base + ['body' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x']]]]]))->not->toHaveKey('body');
});
