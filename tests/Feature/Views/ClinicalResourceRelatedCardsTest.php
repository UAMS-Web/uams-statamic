<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;
use Statamic\Modifiers\Modifier;
use Tests\TestCase;
use UAMSWeb\Aether\People\Modifiers\ClinicalProviderEligible;

uses(TestCase::class);

/**
 * The related-entity sections and the document list on a `clinical_resources`
 * page (UAMS-Web/uams-statamic#1582), rendered in the layouts the Find-a-Doc
 * clinical resource page uses: provider, location and expertise cards, the
 * provider cutoff, related resources as cards up to four and a list above that,
 * and a file-type icon on each document.
 *
 * Rendered hermetically from synthetic data arrays, so no content or Stache is
 * needed. The eligibility modifier is replaced by a pass-through: it resolves
 * entries, which synthetic arrays are not, and its own behavior is covered by
 * `ProviderEligibilityFilterWiringTest` and the add-on's tests. Each render
 * appends a unique comment to bust Antlers' parsed-document cache.
 *
 * @command php artisan test --compact tests/Feature/Views/ClinicalResourceRelatedCardsTest.php
 */
beforeEach(function (): void {
    Blink::flush();

    $this->app->bind(ClinicalProviderEligible::class, fn (): Modifier => new class extends Modifier
    {
        /**
         * Return the related providers unchanged, as a list.
         *
         * @param  mixed  $value  The related providers.
         * @return list<mixed> The same providers.
         */
        public function index(mixed $value): array
        {
            return is_array($value) ? array_values($value) : [];
        }
    });
});

/**
 * Render a template string with the given context.
 *
 * @param  string  $template  The Antlers template.
 * @param  array<string, mixed>  $context  The render context.
 * @return string The rendered markup.
 */
function renderRelatedCards(string $template, array $context): string
{
    /** @var int $render */
    static $render = 0;
    $render++;

    return (string) Antlers::parse($template."\n{{# render-{$render} #}}", $context, true);
}

/**
 * Render the related-entities partial for a resource with the given relations.
 *
 * @param  array<string, mixed>  $context  The resource's relations and fields.
 * @return string The rendered markup.
 */
function renderResourceRelated(array $context): string
{
    return renderRelatedCards('{{ partial:clinical_resources/related }}', array_merge([
        'clinical_providers' => [],
        'clinical_locations' => [],
        'clinical_expertise' => [],
        'clinical_conditions' => [],
        'clinical_services' => [],
        'clinical_resources' => [],
    ], $context));
}

/**
 * Build synthetic related providers.
 *
 * @param  int  $count  How many providers.
 * @return list<array<string, mixed>> The providers, named "Provider 01" onward.
 */
function relatedProviders(int $count): array
{
    $providers = [];
    for ($i = 1; $i <= $count; $i++) {
        $name = sprintf('Provider %02d', $i);
        $providers[] = [
            'url' => '/people/provider-'.$i,
            'display_name_full_with_clinical_credentials' => ['text' => $name],
            'display_name_short_with_prefix' => ['text' => $name],
        ];
    }

    return $providers;
}

/**
 * Build synthetic related clinical resources.
 *
 * @param  int  $count  How many resources.
 * @return list<array<string, mixed>> The resources, titled "Resource 1" onward.
 */
function relatedResources(int $count): array
{
    $resources = [];
    for ($i = 1; $i <= $count; $i++) {
        $resources[] = ['title' => 'Resource '.$i, 'url' => '/resources/r-'.$i, 'blueprint' => 'article'];
    }

    return $resources;
}

it('renders every related provider as a card when the list is at the cutoff', function (): void {
    $html = renderResourceRelated(['clinical_providers' => relatedProviders(18)]);

    expect(substr_count($html, '>View Profile</a>'))->toBe(18)
        ->and($html)->toContain('card-list card-list-doctors')
        ->and($html)->toContain('Provider 18')
        ->and($html)->not->toContain('Load all providers')
        ->and($html)->not->toContain('<details');
});

it('shows the first 12 providers and puts the rest behind a control above the cutoff', function (): void {
    $html = renderResourceRelated(['clinical_providers' => relatedProviders(19)]);

    [$visible, $rest] = explode('<details', $html, 2);

    expect(substr_count($visible, '>View Profile</a>'))->toBe(12)
        ->and($visible)->toContain('Provider 12')
        ->and($visible)->not->toContain('Provider 13')
        ->and(substr_count($rest, '>View Profile</a>'))->toBe(7)
        ->and($rest)->toContain('<summary class="btn btn-primary">Load all providers</summary>')
        ->and($rest)->toContain('Provider 13')
        ->and($rest)->toContain('Provider 19')
        ->and($rest)->not->toContain('Provider 12<');
});

it('renders related locations and areas of expertise as cards', function (): void {
    $html = renderResourceRelated([
        'clinical_locations' => [['title' => 'UAMS Medical Center', 'url' => '/locations/medical-center']],
        'clinical_expertise' => [['title' => 'Cardiology', 'url' => '/expertise/cardiology']],
    ]);

    expect($html)->toContain('Related Locations')
        ->and($html)->toContain('itemtype="https://schema.org/MedicalClinic"')
        ->and($html)->toContain('UAMS Medical Center')
        ->and($html)->toContain('Areas of Expertise')
        ->and($html)->toContain('class="card expertise-card h-100"')
        ->and($html)->toContain('Cardiology')
        ->and($html)->not->toContain('clinical-resource-related-list');
});

it('renders conditions and treatments as link lists, as Find-a-Doc does', function (): void {
    $html = renderResourceRelated([
        'clinical_conditions' => [['title' => 'Heart Failure', 'url' => '/conditions/heart-failure']],
        'clinical_services' => [['title' => 'Bypass Surgery', 'url' => '/treatments/bypass']],
    ]);

    expect(substr_count($html, 'class="clinical-resource-related-list"'))->toBe(2)
        ->and($html)->toContain('<a href="/conditions/heart-failure">Heart Failure</a>')
        ->and($html)->toContain('<a href="/treatments/bypass">Bypass Surgery</a>')
        ->and($html)->not->toContain('card h-100');
});

it('renders up to four related resources as cards', function (): void {
    $html = renderResourceRelated(['clinical_resources' => relatedResources(4)]);

    expect(substr_count($html, '<div class="card h-100">'))->toBe(4)
        ->and($html)->toContain('Related Resources')
        ->and($html)->toContain('Resource 4')
        ->and($html)->not->toContain('clinical-resource-related-list');
});

it('renders more than four related resources as a link list', function (): void {
    $html = renderResourceRelated(['clinical_resources' => relatedResources(5)]);

    expect($html)->toContain('class="clinical-resource-related-list"')
        ->and($html)->toContain('<a href="/resources/r-5">Resource 5</a>')
        ->and($html)->not->toContain('<div class="card h-100">');
});

it('does not let the page resource\'s own introduction fall through into a related resource card', function (): void {
    $html = renderResourceRelated([
        'intro' => '<p>The page resource introduction.</p>',
        'clinical_resources' => relatedResources(1),
    ]);

    expect($html)->toContain('Resource 1')
        ->and($html)->not->toContain('The page resource introduction.');
});

it('still shows a related resource\'s own introduction on its card', function (): void {
    $html = renderResourceRelated([
        'intro' => '<p>The page resource introduction.</p>',
        'clinical_resources' => [
            ['title' => 'Resource 1', 'url' => '/resources/r-1', 'blueprint' => 'article', 'intro' => '<p>Its own introduction.</p>'],
        ],
    ]);

    expect($html)->toContain('<p>Its own introduction.</p>')
        ->and($html)->not->toContain('The page resource introduction.');
});

it('does not let a page featured image fall through into a related location card', function (): void {
    $html = renderResourceRelated([
        'featured_image' => ['url' => '/assets/page-image.jpg', 'alt' => 'Page image'],
        'clinical_locations' => [['title' => 'UAMS Medical Center', 'url' => '/locations/medical-center']],
    ]);

    expect($html)->toContain('UAMS Medical Center')
        ->and($html)->not->toContain('/assets/page-image.jpg');
});

it('leaves the page fields intact for what renders after the related sections', function (): void {
    $html = renderRelatedCards(
        '{{ partial:clinical_resources/related }}AFTER[{{ intro }}]',
        [
            'intro' => 'The page resource introduction.',
            'clinical_providers' => [],
            'clinical_locations' => [],
            'clinical_expertise' => [],
            'clinical_conditions' => [],
            'clinical_services' => [],
            'clinical_resources' => relatedResources(1),
        ],
    );

    expect($html)->toContain('AFTER[The page resource introduction.]');
});

it('renders nothing when no relation is populated', function (): void {
    expect(trim(renderResourceRelated([])))->toBe('');
});

it('marks each document with the icon of its file type and a text alternative', function (string $extension, string $icon, string $type): void {
    $html = renderRelatedCards('{{ partial:clinical_resources/document_link :label="label" }}', [
        'label' => 'Discharge Instructions',
        'url' => '/assets/discharge.'.$extension,
        'extension' => $extension,
    ]);

    expect($html)->toContain('<span class="far '.$icon.' fa-fw" aria-hidden="true"></span>')
        ->and($html)->toContain('<span class="sr-only"> ('.$type.')</span>')
        ->and($html)->toContain('<span class="attachment-label">Discharge Instructions</span>')
        ->and($html)->toContain('href="/assets/discharge.'.$extension.'"');
})->with([
    'pdf' => ['pdf', 'fa-file-pdf', 'PDF'],
    'upper-case pdf' => ['PDF', 'fa-file-pdf', 'PDF'],
    'doc' => ['doc', 'fa-file-word', 'Word document'],
    'docx' => ['docx', 'fa-file-word', 'Word document'],
    'ppt' => ['ppt', 'fa-file-powerpoint', 'PowerPoint presentation'],
    'pptx' => ['pptx', 'fa-file-powerpoint', 'PowerPoint presentation'],
    'xls' => ['xls', 'fa-file-excel', 'Excel spreadsheet'],
    'xlsx' => ['xlsx', 'fa-file-excel', 'Excel spreadsheet'],
    'jpg' => ['jpg', 'fa-file-image', 'Image'],
    'jpeg' => ['jpeg', 'fa-file-image', 'Image'],
    'gif' => ['gif', 'fa-file-image', 'Image'],
    'png' => ['png', 'fa-file-image', 'Image'],
    'bmp' => ['bmp', 'fa-file-image', 'Image'],
    'unmapped type' => ['txt', 'fa-file', 'File'],
]);

it('escapes the document label', function (): void {
    $html = renderRelatedCards('{{ partial:clinical_resources/document_link :label="label" }}', [
        'label' => '<script>alert(1)</script>',
        'url' => '/assets/x.pdf',
        'extension' => 'pdf',
    ]);

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('renders the document list through the document link partial on the show page', function (): void {
    $src = File::get(resource_path('views/clinical_resources/show.antlers.html'));

    expect($src)->toContain('partial:clinical_resources/document_link :label="$doc_label"');
});

it('titles each related card one level below its section heading', function (): void {
    $html = renderResourceRelated([
        'clinical_locations' => [['title' => 'UAMS Medical Center', 'url' => '/locations/medical-center']],
        'clinical_expertise' => [['title' => 'Cardiology', 'url' => '/expertise/cardiology']],
        'clinical_resources' => relatedResources(1),
    ]);

    expect($html)->toContain('<h3 class="card-title h5" itemprop="https://schema.org/name">UAMS Medical Center</h3>')
        ->and($html)->toContain('<h3 class="card-title h5"><span itemprop="name">Cardiology</span></h3>')
        ->and($html)->toContain('<h3 class="card-title h5" itemprop="https://schema.org/name">Resource 1</h3>')
        ->and($html)->not->toContain('<h2 class="card-title');
});

it('keeps an archive card titled h2 when no heading level is passed', function (string $partial, string $title, string $heading): void {
    $html = renderRelatedCards('{{ partial:'.$partial.' }}', ['title' => $title, 'url' => '/card', 'blueprint' => 'article']);

    expect($html)->toContain($heading);
})->with([
    'resource card' => ['clinical_resources/card', 'Resource 1', '<h2 class="card-title h5" itemprop="https://schema.org/name">Resource 1</h2>'],
    'location card' => ['clinical_locations/card', 'UAMS Medical Center', '<h2 class="card-title h5" itemprop="https://schema.org/name">UAMS Medical Center</h2>'],
    'expertise card' => ['clinical_expertise/card', 'Cardiology', '<h2 class="card-title h5"><span itemprop="name">Cardiology</span></h2>'],
]);

it('gives the related provider cards their own item scope', function (): void {
    $html = renderResourceRelated(['clinical_providers' => relatedProviders(19)]);

    expect(substr_count($html, '<div class="card-list card-list-doctors" itemscope itemtype="https://schema.org/ItemList">'))->toBe(2);
});

it('marks every related provider card for its own width rule, behind the control too', function (): void {
    $html = renderResourceRelated(['clinical_providers' => relatedProviders(19)]);

    expect(substr_count($html, '<div class="card provider-card">'))->toBe(19);
});

it('escapes a related title on the link-list path', function (): void {
    $html = renderResourceRelated([
        'clinical_conditions' => [['title' => '<img src=x onerror=alert(1)>', 'url' => '/conditions/x']],
        'clinical_resources' => array_merge(relatedResources(4), [['title' => '<b>Bold</b>', 'url' => null]]),
    ]);

    expect($html)->not->toContain('<img src=x onerror=alert(1)>')
        ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;')
        ->and($html)->not->toContain('<b>Bold</b>')
        ->and($html)->toContain('&lt;b&gt;Bold&lt;/b&gt;');
});
