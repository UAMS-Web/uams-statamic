<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\LabeledValue;
use Tests\TestCase;

// Returned to the parallel band on UAMS-Web/uams-statamic-archive#2291. This file was gated to the serial tail on
// "the shared, paratest-non-isolated compiled-view cache" (the UAMS-Web/uams-statamic-archive#1300 / UAMS-Web/uams-statamic-archive#1310 / UAMS-Web/uams-statamic-archive#1447 /
// UAMS-Web/uams-statamic-archive#1452 render-flake class). That directory is per worker under Laravel's `TestViews`
// -- `storage/framework/views/test_<token>`, the one UAMS-Web/uams-statamic-archive#2442 patched for concurrent bands
// -- so the hazard the gate named does not exist. Three parity-matched parallel bands
// with this file in them produced zero failures (macOS, 2026-09-16); runs refute and
// never establish, and tests/README.md carries the audit row.
uses(TestCase::class);

/**
 * Behavioral render coverage for the Group HH provider-profile sections on the
 * People profile: the awards list (UAMS-Web/uams-statamic-archive#1170) and the referral-requirement
 * disclaimer (UAMS-Web/uams-statamic-archive#1190), each an optional self-contained partial rendered
 * hermetically with a fixture scope.
 *
 * @command  php artisan test --compact tests/Feature/Views/PeopleProfileProviderSectionsTest.php
 */

/**
 * Render a People-profile component partial with the given entry scope.
 *
 * @param  array<string, mixed>  $scope
 */
function renderPeopleComponent(string $partial, array $scope): string
{
    return (string) Antlers::parse('{{ partial:people/components/'.$partial.' }}', $scope);
}

it('renders the awards section listing each award in entered order', function (): void {
    $html = renderPeopleComponent('awards', [
        'awards' => [
            ['title' => 'Best Doctors in America', 'notes' => 'Cardiology'],
            ['title' => 'Top 100 Physicians', 'notes' => ''],
        ],
    ]);

    expect($html)
        ->toContain('profile-awards')
        ->toContain('Best Doctors in America')
        ->toContain('Top 100 Physicians')
        ->toContain('Cardiology');

    // Awards render in the order they were entered.
    expect(positionOf($html, 'Best Doctors in America'))
        ->toBeLessThan(positionOf($html, 'Top 100 Physicians'));
});

// `awards` `title`/`notes` are editor/importer-controlled `text` fields rendered
// through raw-by-default Antlers, so an imported award carrying markup would
// otherwise render as live HTML on every people lens (UAMS-Web/uams-statamic-archive#1672).
it('escapes markup in an award title so injected tags render as inert text', function (): void {
    $html = renderPeopleComponent('awards', [
        'awards' => [
            ['title' => '<img src=x onerror=alert(1)>Distinguished Faculty', 'notes' => ''],
        ],
    ]);

    expect($html)->not->toContain('<img')
        ->and($html)->not->toContain('onerror=alert(1)>')
        ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;')
        ->and($html)->toContain('Distinguished Faculty');
});

it('escapes markup in award notes so injected tags render as inert text', function (): void {
    $html = renderPeopleComponent('awards', [
        'awards' => [
            ['title' => 'Best Doctors in America', 'notes' => '<script>alert(1)</script>Cardiology'],
        ],
    ]);

    expect($html)->not->toContain('<script>')
        ->and($html)->not->toContain('</script>')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($html)->toContain('Cardiology');
});

// The fixtures above pass bare PHP arrays, but a real profile renders the
// replicator AUGMENTED: `Replicator::performAugmentation()` materializes every
// blueprint handle and wraps each row in `Statamic\Fields\Values`. Prove the
// escape on that shape too, so the ticket's central assertion is not resting on
// a harness-only value shape (the adversarial-review fold-in on UAMS-Web/uams-statamic-archive#1672).
it('escapes markup in an award title on the augmented replicator shape', function (): void {
    $awardsField = Blueprint::find('collections/people/person')?->field('awards');

    if (! $awardsField instanceof Field) {
        $this->markTestSkipped('Requires the people blueprint with an `awards` field.');
    }

    $awardsField->setValue([[
        'type' => 'award',
        'id' => 'award-one',
        'title' => '<img src=x onerror=alert(1)>Distinguished Faculty',
        'notes' => '<script>alert(1)</script>Cardiology',
    ]]);

    $augmented = $awardsField->augment();

    if (! $augmented instanceof Field) {
        $this->markTestSkipped('The people blueprint `awards` field did not augment.');
    }

    $awards = $augmented->value();

    $html = renderPeopleComponent('awards', ['awards' => $awards]);

    expect($html)->not->toContain('<img')
        ->and($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($html)->toContain('Distinguished Faculty')
        ->and($html)->toContain('Cardiology');
});

// Escaping must encode, not delete: an award title is plain text whose literal
// characters have to survive verbatim, which is why the sink uses `sanitize`
// rather than `strip_tags`.
it('preserves literal award text that only looks like markup', function (): void {
    $html = renderPeopleComponent('awards', [
        'awards' => [
            ['title' => 'Top 5 & Under', 'notes' => 'Ranked > 90th percentile'],
        ],
    ]);

    expect($html)
        ->toContain('Top 5 &amp; Under')
        ->toContain('Ranked &gt; 90th percentile');
});

it('renders no awards section when the provider has no awards', function (): void {
    $html = renderPeopleComponent('awards', ['awards' => []]);

    expect(trim($html))->not->toContain('profile-awards');
    expect(trim($html))->not->toContain('Awards');
});

// A real `true_false` field augments to a `LabeledValue`, never a raw bool, in a
// profile render. These tests inject that augmented shape (not a raw scalar) so
// the strict `referral_required:value === false` guard is exercised faithfully —
// a raw-scalar fixture would pass while the production feature is inert (the
// LabeledValue-vs-bool trap the adversarial review caught).
it('renders the referral disclaimer only when a referral is explicitly not required', function (): void {
    $html = renderPeopleComponent('referral_disclaimer', ['referral_required' => new LabeledValue(false, 'No')]);

    expect($html)
        ->toContain('profile-referral-disclaimer')
        ->toContain('does not require a referral')
        ->toContain('insurance plan may still require one');
});

it('renders no disclaimer when a referral is required', function (): void {
    $html = renderPeopleComponent('referral_disclaimer', ['referral_required' => new LabeledValue(true, 'Yes')]);

    expect(trim($html))->toBe('');
});

it('renders no disclaimer when the referral state is unset (non-provider)', function (): void {
    $html = renderPeopleComponent('referral_disclaimer', ['referral_required' => null]);

    expect(trim($html))->toBe('');
});
