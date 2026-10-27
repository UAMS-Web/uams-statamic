<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\ProviderSpotlight;

use App\Services\ComputedFields\ProviderPeople\ClinicalTitleResolver;
use App\Services\ComputedFields\ProviderPeople\ProviderPeoplePersonNameComputedValues;
use App\Services\PhoneUtil;
use App\Support\People\ProviderNarrativeLanguage;
use Illuminate\Support\Str;
use Statamic\Assets\Asset;
use Statamic\Entries\Entry;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\Entry as EntryFacade;

/**
 * Provider-derived values for the `provider_spotlight` clinical-resource type
 * (UAMS-Web/uams-statamic-archive#1425). A spotlight leans entirely on one featured `people` provider, so the
 * card/OG excerpt, the introduction prose, and the featured image are all
 * derived from that provider here rather than re-entered by editors.
 *
 * The name / clinical-title reads reuse the same computed helpers the public
 * provider profile and the Find-a-Doc API use, so the spotlight never drifts
 * from the provider's own presentation. The narrative wording is kept simple and
 * centralized here.
 */
final class ProviderSpotlightComputedValues
{
    /**
     * The one blueprint in `clinical_resources` these computeds apply to.
     */
    public const string BLUEPRINT = 'provider_spotlight';

    /**
     * Per-spotlight memo of the resolved provider (keyed by spotlight id), so the
     * ~half-dozen derived computeds resolve the featured provider once per render
     * rather than issuing an `Entry::find` each. Instance-scoped, so it lives only
     * for the request that resolves it.
     *
     * @var array<string, ?Entry>
     */
    private array $providerMemo = [];

    public function __construct(
        private readonly ProviderPeoplePersonNameComputedValues $names,
        private readonly ClinicalTitleResolver $clinicalTitle,
    ) {}

    /**
     * The featured `people` provider entry, or null when unset / unresolved.
     */
    public function resolveFeaturedProvider(Entry $spotlight): ?Entry
    {
        $key = $spotlight->id();
        $key = \is_string($key) ? $key : '';

        // Only a non-empty id is ever stored below, so a memo hit needs no '' check
        if (\array_key_exists($key, $this->providerMemo)) {
            return $this->providerMemo[$key];
        }

        $id = $this->firstReference($spotlight->get('featured_provider'));
        $provider = $id === null ? null : EntryFacade::find($id);
        // The app's entry repository returns an Entry or null, never another type,
        // so this instanceof reads as a null check either way.
        // @pest-mutate-ignore: InstanceOfToTrue
        $resolved = $provider instanceof Entry ? $provider : null;

        if ($key !== '') {
            $this->providerMemo[$key] = $resolved;
        }

        return $resolved;
    }

    /**
     * The generated introduction prose (the spotlight has no editor intro field —
     * it is always derived): the provider's display name and articled, lowercased
     * clinical title, then a second sentence naming the short display name and
     * possessive pronoun. Null when no provider resolves.
     */
    public function autoIntroduction(Entry $spotlight): ?string
    {
        $provider = $this->resolveFeaturedProvider($spotlight);

        if (! $provider instanceof Entry) {
            return null;
        }

        $name = $this->displayName($provider);
        $shortName = $this->shortDisplayName($provider);
        $title = $this->clinicalTitle->resolve($provider);
        $possessive = ProviderNarrativeLanguage::possessivePronoun($this->stringValue($provider->get('gender')));

        return $this->composeIntroduction($name, $shortName, $title, $possessive);
    }

    /**
     * Assemble the introduction prose from resolved parts. Pure so the article
     * choice, lowercasing, pronoun, and empty-title fallback are directly testable.
     * The clinical title is lowercased for the prose (e.g. "an optometrist").
     */
    public function composeIntroduction(string $name, string $shortName, string $clinicalTitle, string $possessivePronoun): string
    {
        $title = mb_strtolower(trim($clinicalTitle));

        $opening = $title !== ''
            ? sprintf('Get to know %s, %s %s at UAMS Health.', $name, ProviderNarrativeLanguage::indefiniteArticle($title), $title)
            : sprintf('Get to know %s of UAMS Health.', $name);

        return sprintf(
            '%s In the Q&A below, %s shares what inspired %s work in healthcare, %s life outside of medicine, and more.',
            $opening,
            $shortName,
            $possessivePronoun,
            $possessivePronoun
        );
    }

    /**
     * The auto summary (≤160 chars) that feeds the OG description and the card
     * excerpt when no excerpt is entered. Null when no provider resolves.
     */
    public function autoExcerpt(Entry $spotlight): ?string
    {
        $provider = $this->resolveFeaturedProvider($spotlight);

        if (! $provider instanceof Entry) {
            return null;
        }

        $name = $this->displayName($provider);
        $title = $this->clinicalTitle->resolve($provider);

        return $this->composeExcerpt($name, $title);
    }

    /**
     * Assemble the ≤160-char summary from resolved parts. Pure and testable. The
     * clinical title is lowercased for the prose.
     */
    public function composeExcerpt(string $name, string $clinicalTitle): string
    {
        $title = mb_strtolower(trim($clinicalTitle));

        $summary = $title !== ''
            ? sprintf('Meet %s, %s at UAMS Health, in this provider spotlight Q&A.', $name, $title)
            : sprintf('Meet %s of UAMS Health in this provider spotlight Q&A.', $name);

        return $this->truncate($summary, 160);
    }

    /**
     * The derived entry title, "Get to Know {provider display name}" (with the
     * "Dr." prefix when the provider is credentialed). Null when no provider
     * resolves or the provider has no resolvable name, so a caller leaves the
     * stored title untouched rather than writing an empty one.
     */
    public function autoTitle(Entry $spotlight): ?string
    {
        $provider = $this->resolveFeaturedProvider($spotlight);

        if (! $provider instanceof Entry) {
            return null;
        }

        $name = $this->displayName($provider);

        return $name === '' ? null : $this->composeTitle($name);
    }

    /**
     * Assemble the title from a resolved provider display name. Pure so the
     * exact phrasing is directly testable.
     */
    public function composeTitle(string $displayName): string
    {
        return sprintf('Get to Know %s', trim($displayName));
    }

    /**
     * The derived slug, "get-to-know-{prefix/degree-free provider name}". The
     * slug drops the "Dr." prefix and any credentials so the URL reads as a
     * plain name. Null when no provider resolves or the provider has no
     * resolvable name.
     */
    public function autoSlug(Entry $spotlight): ?string
    {
        $provider = $this->resolveFeaturedProvider($spotlight);

        if (! $provider instanceof Entry) {
            return null;
        }

        $name = $this->plainName($provider);

        return $name === '' ? null : $this->composeSlug($name);
    }

    /**
     * Assemble the slug from a prefix/degree-free provider name. Pure and
     * testable; `Str::slug` lowercases, transliterates, and hyphenates.
     */
    public function composeSlug(string $plainName): string
    {
        return Str::slug(sprintf('get-to-know-%s', $plainName));
    }

    /**
     * The featured provider's display name (with "Dr." prefix when credentialed),
     * exposed for the portrait alt text and CTA copy. Null when no provider.
     */
    public function providerName(Entry $spotlight): ?string
    {
        $provider = $this->resolveFeaturedProvider($spotlight);

        return $provider instanceof Entry ? $this->displayName($provider) : null;
    }

    /**
     * The featured provider's patient-facing clinical title, exposed for the CTA
     * copy. Null when no provider or no title.
     */
    public function providerTitle(Entry $spotlight): ?string
    {
        $provider = $this->resolveFeaturedProvider($spotlight);

        if (! $provider instanceof Entry) {
            return null;
        }

        $title = $this->clinicalTitle->resolve($provider);

        return $title === '' ? null : $title;
    }

    /**
     * Whether the featured provider is bookable, gating the appointment CTA
     * (UAMS-Web/uams-statamic-archive#1425 §4): they take scheduled appointments and are accepting new
     * patients, and are not a resident. Residents are never bookable, mirroring
     * the provider profile's own appointment eligibility.
     */
    public function isBookable(Entry $spotlight): bool
    {
        $provider = $this->resolveFeaturedProvider($spotlight);

        if (! $provider instanceof Entry) {
            return false;
        }

        if ($this->truthy($provider->get('resident_query'))) {
            return false;
        }

        return $this->truthy($provider->get('appointments_query'))
            && $this->truthy($provider->get('accepting_patients_query'));
    }

    /**
     * Whether the featured provider requires a referral for new patients — the
     * CTA prepends a referral-only note in the bookable branch.
     */
    public function referralRequired(Entry $spotlight): bool
    {
        $provider = $this->resolveFeaturedProvider($spotlight);

        return $provider instanceof Entry && $this->truthy($provider->get('referral_required'));
    }

    /**
     * The appointment phone formatted for display in the bookable CTA (national
     * style, e.g. "501-686-8998"). Falls back to the entered value when it cannot
     * be parsed, so a phone the editor set is never hidden. Null when unset.
     */
    public function appointmentPhoneDisplay(Entry $spotlight): ?string
    {
        $raw = trim($this->stringValue($spotlight->get('spotlight_phone')) ?? '');

        if ($raw === '') {
            return null;
        }

        $e164 = $this->usE164($raw);

        if ($e164 === null) {
            return $raw;
        }

        $formatted = PhoneUtil::formatForDisplay($e164, 'national');

        // formatForDisplay() echoes its input on a parse failure; show the raw
        // entered value rather than a "+…" form in that case.
        return $formatted === $e164 ? $raw : $formatted;
    }

    /**
     * The appointment phone as a `tel:`-safe string. A clean US number dials as
     * E.164; anything else keeps its dialable characters (digits and a leading
     * "+"), falling back to the raw value. Null when unset.
     */
    public function appointmentPhoneHref(Entry $spotlight): ?string
    {
        $raw = trim($this->stringValue($spotlight->get('spotlight_phone')) ?? '');

        if ($raw === '') {
            return null;
        }

        $e164 = $this->usE164($raw);

        if ($e164 !== null) {
            return $e164;
        }

        $tel = preg_replace('/[^0-9+]/', '', $raw) ?? '';

        return $tel === '' ? $raw : $tel;
    }

    /**
     * The E.164 form of a clean US phone — ten digits, or eleven with a leading
     * country code "1". Null for anything else (a local seven-digit number, a
     * number carrying an extension, or a genuinely foreign number the editor
     * typed), so callers show the entered value verbatim rather than reinterpret
     * a malformed number as a wrong foreign one.
     */
    private function usE164(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);

        // On a PCRE failure '' and any other non-digit fallback both fail the
        // length checks below, so the fallback value is equivalent.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $digits ??= '';

        return match (true) {
            \strlen($digits) === 10 => '+1'.$digits,
            \strlen($digits) === 11 && str_starts_with($digits, '1') => '+'.$digits,
            default => null,
        };
    }

    /**
     * Which source supplies the featured image, resolved by precedence:
     * the 16:9 override → the provider's wide portrait → the provider's 3:4
     * portrait. Returns `none` when nothing is available. Pure over the presence
     * of each source so the precedence is directly testable.
     */
    public function imageSource(Entry $spotlight): string
    {
        if ($this->hasReference($spotlight->get('spotlight_image_wide'))) {
            return 'override';
        }

        $provider = $this->resolveFeaturedProvider($spotlight);

        if ($provider instanceof Entry && $this->hasReference($provider->get('portrait_wide'))) {
            return 'provider_wide';
        }

        if ($provider instanceof Entry && $this->hasReference($provider->get('portrait'))) {
            return 'provider_headshot';
        }

        return 'none';
    }

    /**
     * The resolved featured-image asset by precedence ({@see imageSource()}), or
     * null. Asset resolution needs a real container, so the hermetic tests cover
     * {@see imageSource()} and the populated path is exercised by the import smoke.
     */
    public function featuredImage(Entry $spotlight): ?Asset
    {
        $provider = $this->resolveFeaturedProvider($spotlight);

        $path = match ($this->imageSource($spotlight)) {
            'override' => $this->firstReference($spotlight->get('spotlight_image_wide')),
            'provider_wide' => $this->providerReference($provider, 'portrait_wide'),
            'provider_headshot' => $this->providerReference($provider, 'portrait'),
            default => null,
        };

        if ($path === null) {
            return null;
        }

        $asset = AssetFacade::find($path);

        return $asset instanceof Asset ? $asset : null;
    }

    /**
     * First reference in one of the featured provider's image fields.
     *
     * @param  Entry|null  $provider  The featured provider, when one resolved
     * @param  string  $field  The provider's image field handle
     */
    private function providerReference(?Entry $provider, string $field): ?string
    {
        // imageSource() names a provider source only when the provider resolved, so
        // this check can never be false where it is read.
        // @pest-mutate-ignore: InstanceOfToTrue
        return $provider instanceof Entry ? $this->firstReference($provider->get($field)) : null;
    }

    /**
     * The provider's display name with the "Dr." prefix when credentialed.
     */
    private function displayName(Entry $provider): string
    {
        return $this->names->getDisplayNameFullWithPrefix($provider)['text'];
    }

    /**
     * The provider's short display name ("Dr. Smith") with the "Dr." prefix when
     * credentialed, used for the intro prose's second sentence.
     */
    private function shortDisplayName(Entry $provider): string
    {
        return $this->names->getDisplayNameShortWithPrefix($provider)['text'];
    }

    /**
     * The provider's display name without the "Dr." prefix or credentials — the
     * prefix/degree-free name the slug is built from.
     */
    private function plainName(Entry $provider): string
    {
        return $this->names->getDisplayNameFull($provider)['text'];
    }

    /**
     * First entry/asset reference from a relationship or assets field value.
     */
    private function firstReference(mixed $value): ?string
    {
        if (\is_string($value)) {
            return $value === '' ? null : $value;
        }

        if (\is_array($value)) {
            foreach ($value as $item) {
                if (\is_string($item) && $item !== '') {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Whether a relationship / assets field value holds at least one reference.
     */
    private function hasReference(mixed $value): bool
    {
        return $this->firstReference($value) !== null;
    }

    /**
     * Narrow a stored scalar to a string (for the gender read).
     */
    private function stringValue(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    /**
     * Coerce a stored `true_false` value to a strict bool (handles bool / "1" / "true").
     */
    private function truthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Truncate to a maximum length on a word boundary, appending an ellipsis.
     */
    private function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $clipped = mb_substr($text, 0, $max - 1);
        $lastSpace = mb_strrpos($clipped, ' ');

        // The excerpt always opens "Meet ", so the clip always holds a space and
        // $lastSpace is never false; `!== true` behaves the same.
        // @pest-mutate-ignore: FalseToTrue
        if ($lastSpace !== false) {
            $clipped = mb_substr($clipped, 0, $lastSpace);
        }

        return rtrim($clipped, ' ,.;:').'…';
    }
}
