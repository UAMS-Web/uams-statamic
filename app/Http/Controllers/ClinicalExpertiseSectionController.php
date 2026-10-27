<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ClinicalExpertise\ClinicalExpertiseSectionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Http\Controllers\FrontendController;
use Statamic\Sites\Site as SiteContract;
use Statamic\View\View;

/**
 * Renders the per-Area-of-Expertise "virtual subpage" sections (UAMS-Web/uams-statamic-archive#1058):
 * `/{entry}/{section}` serves each relationship-derived section of a
 * `clinical_expertise` entry at its own URL at any nesting depth.
 *
 * This custom route bypasses Statamic's normal DataResponse pipeline, so it must
 * reproduce its guards itself: it renders the section ONLY for a published,
 * publicly-visible AoE entry whose section resolves. Anything else — a draft,
 * a restricted entry, a non-AoE URL, or a real page whose last URL segment merely
 * happens to be a section token (e.g. `/about/locations`) — is **delegated to the
 * normal front end** ({@see FrontendController::index}) for the full URL, so the
 * route never discloses draft content and never 404s (shadows) a legitimate page.
 */
class ClinicalExpertiseSectionController extends Controller
{
    /**
     * @param  ClinicalExpertiseSectionResolver  $resolver  Resolves the section payload / 404 gate.
     */
    public function __construct(
        private readonly ClinicalExpertiseSectionResolver $resolver
    ) {}

    /**
     * Render the AoE section, or fall through to the normal front end.
     */
    public function __invoke(Request $request, string $entry, string $section): mixed
    {
        /** @var SiteContract $site */
        $site = Site::current();
        $siteHandle = $site->handle();

        // findByUri yields the Entry contract; narrow to the concrete Entry the
        // resolver + accessors expect (a non-entry URL falls through below).
        $aoe = is_string($siteHandle)
            ? EntryFacade::findByUri('/'.ltrim($entry, '/'), $siteHandle)
            : null;

        if ($aoe instanceof Entry && $this->isPubliclyRenderable($aoe)) {
            $payload = $this->resolver->resolve($aoe, $section);

            if ($payload !== null) {
                return $this->renderSection($payload);
            }
        }

        // Not a renderable AoE section — behave exactly like the Statamic catch-all
        // for the full URL: render a real page (incl. one whose last segment is a
        // section token), and 404 / protect draft or restricted content.
        return app(FrontendController::class)->index($request);
    }

    /**
     * Whether the AoE entry may be rendered publicly — published and not
     * private / password-protected. Mirrors the front-end publish + visibility
     * gate (UAMS-Web/uams-statamic-archive#223) the custom route would otherwise bypass. (The AoE blueprint has
     * no visibility fields today, so the visibility checks are defensive.)
     */
    public function isPubliclyRenderable(Entry $aoe): bool
    {
        return $aoe->status() === 'published'
            && $aoe->value('status_and_visibility') !== 'private'
            && $aoe->value('password_protected') !== true;
    }

    /**
     * Render the section view through the front-end View pipeline (layout +
     * cascade + augmentation of the loaded AoE entry), mirroring
     * {@see FrontendController::route}.
     *
     * @param  array<string, mixed>  $payload
     */
    private function renderSection(array $payload): Response
    {
        $load = $payload['load'] ?? null;

        // The fluent View setters return $this but are untyped in the stubs, so
        // apply them as statements on the one typed instance rather than chaining.
        /** @var View $view */
        $view = app(View::class);
        $view->template('clinical_expertise.section');
        $view->layout(config('statamic.system.layout', 'layout'));
        $view->with($payload);
        $view->cascadeContent(is_string($load) ? EntryFacade::find($load) : null);

        return response($view->render(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
