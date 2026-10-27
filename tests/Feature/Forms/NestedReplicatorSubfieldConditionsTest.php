<?php

declare(strict_types=1);

use App\Fieldtypes\Replicator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;
use Tests\TestCase;

/**
 * Render-level coverage for field conditions on a subfield inside a NESTED replicator (UAMS-Web/uams-statamic-archive#2133).
 *
 * A subfield two replicators deep carries one `[index][child]` pair per level,
 * `groups[i][members][j][guardian]`. The CSP driver used to rewrite `x-model` only for exactly one
 * pair, so a nested subfield kept its bracketed handle, which Alpine reads as identifier lookups.
 * And the nested replicator's blank set template reused the outer template's index token, so
 * cloning an outer set stamped the outer index over the nested placeholder too, leaving the nested
 * engine nothing to replace.
 *
 * These cases pin the emitted markup: the innermost `x-model`, the per-depth token, and the
 * untouched single-level shape. The live show/hide behavior is proven in
 * tests/Browser/NestedReplicatorSubfieldConditionsTest.php.
 *
 * @command  php artisan test --compact tests/Feature/Forms/NestedReplicatorSubfieldConditionsTest.php
 */
uses(TestCase::class);

/**
 * Handle of the throwaway form created for these cases.
 */
const PEST_NESTED_REPLICATOR_HANDLE = 'nested_replicator_conditions_probe';

/**
 * Isolated forms directory so the throwaway form never touches resources/forms.
 *
 * @var string $pestNestedReplicatorFormsDir
 */
$pestNestedReplicatorFormsDir = '';

/**
 * Isolated views directory holding the throwaway form template.
 *
 * @var string $pestNestedReplicatorViewsDir
 */
$pestNestedReplicatorViewsDir = '';

/**
 * Register a form whose outer set holds a nested replicator with a conditional subfield.
 */
function pestMakeNestedReplicatorForm(): void
{
    app(BlueprintRepository::class)->setFallback(
        'forms.'.PEST_NESTED_REPLICATOR_HANDLE,
        fn () => Blueprint::makeFromFields([
            'groups' => [
                'type' => 'replicator',
                'display' => 'Groups',
                'sets' => ['main' => ['sets' => ['group' => [
                    'display' => 'Group',
                    'fields' => [
                        ['handle' => 'group_name', 'field' => ['type' => 'text', 'display' => 'Group Name']],
                        ['handle' => 'members', 'field' => [
                            'type' => 'replicator',
                            'display' => 'Members',
                            'sets' => ['main' => ['sets' => ['member' => [
                                'display' => 'Member',
                                'fields' => [
                                    ['handle' => 'is_minor', 'field' => ['type' => 'toggle', 'display' => 'Member Is A Minor']],
                                    ['handle' => 'guardian', 'field' => ['type' => 'text', 'display' => 'Member Guardian', 'if' => ['is_minor' => 'equals true']]],
                                ],
                            ]]]],
                        ]],
                    ],
                ]]]],
            ],
        ])
    );

    $form = makeForm(PEST_NESTED_REPLICATOR_HANDLE);
    $form->title('Nested Replicator Conditions Probe');
    $form->save();
}

/**
 * Render the form through the real `form:create` pipeline under the CSP driver.
 *
 * @param  string  $viewsDir  Directory holding the throwaway template
 * @return string The rendered HTML, entity-decoded so attribute values can be matched literally
 */
function pestRenderNestedReplicatorForm(string $viewsDir): string
{
    File::put(
        $viewsDir.'/form.antlers.html',
        '{{ form:create handle="'.PEST_NESTED_REPLICATOR_HANDLE.'" js="alpine_csp" }}'
        .'{{ partial:vendor/statamic/forms/fields }}'
        .'{{ /form:create }}'
    );

    return html_entity_decode((string) View::make('nested_replicator_probe::form')->render());
}

beforeEach(function () use (&$pestNestedReplicatorFormsDir, &$pestNestedReplicatorViewsDir): void {
    $pestNestedReplicatorFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($pestNestedReplicatorFormsDir);
    Config::set('statamic.forms.forms', $pestNestedReplicatorFormsDir);

    $pestNestedReplicatorViewsDir = storage_path('framework/testing/views-'.uniqid('', true));
    File::ensureDirectoryExists($pestNestedReplicatorViewsDir);
    View::addNamespace('nested_replicator_probe', $pestNestedReplicatorViewsDir);

    pestMakeNestedReplicatorForm();
});

afterEach(function () use (&$pestNestedReplicatorFormsDir, &$pestNestedReplicatorViewsDir): void {
    foreach ([$pestNestedReplicatorFormsDir, $pestNestedReplicatorViewsDir, storage_path('forms/'.PEST_NESTED_REPLICATOR_HANDLE)] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('binds a nested subfield x-model to its innermost handle', function () use (&$pestNestedReplicatorViewsDir): void {
    $html = pestRenderNestedReplicatorForm($pestNestedReplicatorViewsDir);

    expect($html)->toContain('x-model="is_minor"')
        ->and($html)->toContain('x-model="guardian"')
        ->and($html)->toContain('x-model="group_name"')
        // No x-model is left bracketed at any depth.
        ->and(preg_match('/x-model="[^"]*\[/', $html))->toBe(0);
});

it('wraps the nested conditional subfield in its own conditions template', function () use (&$pestNestedReplicatorViewsDir): void {
    $html = pestRenderNestedReplicatorForm($pestNestedReplicatorViewsDir);

    expect($html)->toContain('$showField({"if":{"is_minor":"equals true"}}');
});

it('gives the nested set template its own index token, distinct from the outer one', function () use (&$pestNestedReplicatorViewsDir): void {
    $html = pestRenderNestedReplicatorForm($pestNestedReplicatorViewsDir);

    // The outer template keeps the original token; the nested one uses the depth-1 token, so
    // cloning an outer set cannot replace the nested placeholder.
    expect($html)->toContain('data-replicator-index-token="REPLICATORSETINDEX"')
        ->and($html)->toContain('data-replicator-index-token="REPLICATOR-NESTED-1-INDEX"')
        ->and($html)->toContain('name="groups[REPLICATORSETINDEX][members][REPLICATOR-NESTED-1-INDEX][guardian]"')
        ->and($html)->toContain('name="groups[REPLICATORSETINDEX][group_name]"');
});

it('derives each nesting depth a token no other depth contains', function (): void {
    $tokens = [
        Replicator::indexTokenFor('groups'),
        Replicator::indexTokenFor('groups[0][members]'),
        Replicator::indexTokenFor('groups[REPLICATORSETINDEX][members][REPLICATOR-NESTED-1-INDEX][more]'),
    ];

    expect($tokens)->toBe(['REPLICATORSETINDEX', 'REPLICATOR-NESTED-1-INDEX', 'REPLICATOR-NESTED-2-INDEX']);

    // A token swap is a substring replacement, so no token may contain another.
    foreach ($tokens as $token) {
        foreach ($tokens as $other) {
            if ($token !== $other) {
                expect(str_contains($token, $other))->toBeFalse();
            }
        }
    }
});
