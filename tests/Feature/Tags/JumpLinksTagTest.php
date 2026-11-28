<?php

declare(strict_types=1);

use App\Tags\JumpLinks;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the "On this page" jump-links nav (UAMS-Web/uams-statamic#1575): the
 * `jump_links` tag ({@see JumpLinks}), its `partials/jump_links` markup, and the
 * three Find-a-Doc profile views that use it.
 *
 * The tag is driven through Antlers with literal sections, so each case states the
 * headings it renders. The view cases render real section partials inside the tag where
 * they render hermetically, and otherwise read the templates for the wiring.
 *
 * @command  php artisan test --compact tests/Feature/Tags/JumpLinksTagTest.php
 */

/**
 * Render content inside the tag.
 *
 * @param  array<array-key, mixed>  $scope  The scope the content renders in.
 */
function renderJumpLinks(string $content, string $params = '', array $scope = []): string
{
    return (string) Antlers::parse('{{ jump_links'.$params.' }}'.$content.'{{ /jump_links }}', $scope);
}

/**
 * The links the nav renders, as id to label.
 *
 * @return array<string, string>
 */
function jumpLinkTargets(string $html): array
{
    preg_match('~<nav class="jump-links".*?</nav>~s', $html, $nav);
    preg_match_all('~<a href="#([^"]+)">([^<]*)</a>~', $nav[0] ?? '', $links, PREG_SET_ORDER);

    $targets = [];
    foreach ($links as $link) {
        $targets[$link[1]] = html_entity_decode($link[2]);
    }

    return $targets;
}

it('shows the nav, named by its On this page heading, once two sections render', function (): void {
    $html = renderJumpLinks('<h2 id="hours" data-jump-link="">Hours</h2><p>x</p><h2 id="parking" data-jump-link="Parking Information">Parking and Directions</h2>');

    expect($html)->toContain('<nav class="jump-links" id="jump-links" aria-labelledby="jump-links-title">')
        ->and($html)->toContain('<h2 id="jump-links-title">On this page</h2>')
        ->and(jumpLinkTargets($html))->toBe(['hours' => 'Hours', 'parking' => 'Parking Information']);
});

it('shows no nav with fewer than two sections', function (string $content): void {
    $html = renderJumpLinks('<!--jump-links-->'.$content);

    expect($html)->not->toContain('<nav')
        ->and($html)->not->toContain('<!--jump-links-->')
        ->and($html)->toContain($content);
})->with([
    'none' => ['<p>Nothing to list.</p>'],
    'one' => ['<h2 id="hours" data-jump-link="">Hours</h2>'],
    'one marked, one unmarked' => ['<h2 data-jump-link="">Hours</h2><h2>Address</h2>'],
]);

it('honors a different threshold', function (): void {
    $one = '<h2 id="hours" data-jump-link="">Hours</h2>';

    expect(renderJumpLinks($one, ' min="1"'))->toContain('<nav')
        ->and(renderJumpLinks($one.'<h2 data-jump-link="">B</h2>', ' min="3"'))->not->toContain('<nav');
});

it('gives a heading without an id a unique one that its link reaches', function (): void {
    $html = renderJumpLinks('<h2 id="jump-telemedicine">Elsewhere</h2><h2 data-jump-link="">Telemedicine</h2><h2 data-jump-link="Telemedicine">Virtual Visits</h2>');

    expect(jumpLinkTargets($html))->toBe(['jump-telemedicine-2' => 'Telemedicine', 'jump-telemedicine-3' => 'Telemedicine'])
        ->and($html)->toContain('<h2 data-jump-link="" id="jump-telemedicine-2">Telemedicine</h2>')
        ->and($html)->toContain('<h2 data-jump-link="Telemedicine" id="jump-telemedicine-3">Virtual Visits</h2>');

    // Every id in the page is unique.
    preg_match_all('~\sid="([^"]+)"~', $html, $ids);
    expect($ids[1])->toBe(array_values(array_unique($ids[1])));
});

it('takes an empty label from the heading text, and escapes labels', function (): void {
    $html = renderJumpLinks('<h2 id="a" data-jump-link=""><span class="title">Areas Within <b>Heart</b></span></h2><h2 id="b" data-jump-link="Treatments &amp; Procedures">T</h2>');

    expect(jumpLinkTargets($html))->toBe(['a' => 'Areas Within Heart', 'b' => 'Treatments & Procedures'])
        ->and($html)->toContain('<a href="#b">Treatments &amp; Procedures</a>');
});

it('places the nav at the marker, or before the content without one', function (): void {
    $sections = '<h2 id="a" data-jump-link="">A</h2><h2 id="b" data-jump-link="">B</h2>';

    $marked = renderJumpLinks('<p>Lead</p><!--jump-links-->'.$sections);
    expect(strpos($marked, '<p>Lead</p>'))->toBeLessThan((int) strpos($marked, '<nav'))
        ->and(strpos($marked, '<nav'))->toBeLessThan((int) strpos($marked, '<h2 id="a"'))
        ->and($marked)->not->toContain('<!--jump-links-->');

    $unmarked = renderJumpLinks('<p>Lead</p>'.$sections);
    expect(strpos($unmarked, '<nav'))->toBeLessThan((int) strpos($unmarked, '<p>Lead</p>'));
});

it('lists a related-entities group only when the caller names a link', function (): void {
    $entries = [['title' => 'Heart Failure', 'url' => '/conditions/heart-failure']];
    $html = renderJumpLinks(
        '{{ partial:clinical_resources/related_list :entries="conditions" heading="Conditions" jump_link="Conditions" }}'
        .'{{ partial:clinical_resources/related_list :entries="treatments" heading="Treatments" jump_link="Treatments & Procedures" }}'
        .'{{ partial:clinical_resources/related_list :entries="conditions" heading="Unlisted" }}',
        '',
        ['conditions' => $entries, 'treatments' => $entries]
    );

    expect(array_values(jumpLinkTargets($html)))->toBe(['Conditions', 'Treatments & Procedures'])
        ->and($html)->toContain('<h2 class="h5">Unlisted</h2>');
});

it('wraps each profile view section list in the tag', function (string $view, string $first): void {
    $source = File::get(resource_path('views/'.$view.'/show.antlers.html'));
    $open = strpos($source, '{{ jump_links }}');
    $close = strpos($source, '{{ /jump_links }}');

    expect($open)->toBeInt()
        ->and($close)->toBeInt()
        ->and($open < (int) strpos($source, $first) && strpos($source, $first) < $close)->toBeTrue();
})->with([
    'locations' => ['clinical_locations', '{{ partial:clinical_locations/profile_details }}'],
    'expertise' => ['clinical_expertise', '{{ partial:clinical_expertise/related }}'],
    'resources' => ['clinical_resources', '{{ partial:clinical_resources/related }}'],
]);

it('marks the sections Find-a-Doc links to on each profile', function (string $file, int $marked): void {
    $source = File::get(resource_path('views/'.$file));

    expect(substr_count($source, 'data-jump-link=').substr_count($source, 'jump_link="'))->not->toBe('00')
        ->and(substr_count($source, 'data-jump-link=') + substr_count($source, ' jump_link="'))->toBe($marked);
})->with([
    'location details: alert, closing, parking, appointment, prescription, telemedicine' => ['clinical_locations/_profile_details.antlers.html', 6],
    'location About' => ['clinical_locations/_profile_overview.antlers.html', 1],
    'location child clinics' => ['clinical_locations/_child_locations.antlers.html', 1],
    'location related lists' => ['clinical_locations/show.antlers.html', 5],
    'expertise Areas Within' => ['clinical_expertise/_areas_within.antlers.html', 1],
    'expertise related blocks' => ['clinical_expertise/_related.antlers.html', 6],
    'expertise appointment' => ['clinical_expertise/_appointment.antlers.html', 1],
    'resource related blocks' => ['clinical_resources/_related.antlers.html', 7],
    'resource appointment' => ['clinical_resources/_appointment.antlers.html', 1],
]);

it('links a location profile to the sections it renders, and to no others', function (): void {
    $location = [
        'title' => 'Enterprise Clinic',
        'about' => '<p>A full-service clinic.</p>',
        'resolved_parking_instructions' => 'Use Garage B.',
        'telemed_query' => true,
    ];
    $html = renderJumpLinks(
        '<!--jump-links-->{{ partial:clinical_locations/profile_overview }}{{ partial:clinical_locations/profile_details }}',
        '',
        $location
    );
    $targets = jumpLinkTargets($html);

    expect(array_values($targets))->toBe(['About', 'Parking Information', 'Telemedicine']);

    // Each link reaches a heading carrying that id, and the parking button's section keeps its own.
    foreach (array_keys($targets) as $id) {
        expect(preg_match('~<h2[^>]*\sid="'.preg_quote($id, '~').'"~', $html))->toBe(1);
    }
    expect($html)->toContain('<section class="location-parking" id="parking-info">');

    // A location rendering only one of them shows no nav.
    $alone = renderJumpLinks('{{ partial:clinical_locations/profile_details }}', '', ['title' => 'Enterprise Clinic', 'telemed_query' => true]);
    expect($alone)->not->toContain('<nav');
});
