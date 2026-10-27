<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Support\People\ProviderNarrativeLanguage;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier `{{ value | indefinite_article }}`: the indefinite article
 * ("a" or "an") for the value's first word, via {@see ProviderNarrativeLanguage}.
 * Used in provider-spotlight CTA / intro prose ("Get to know Jane Smith,
 * {{ clinical_title | indefinite_article }} {{ clinical_title }} at UAMS Health").
 */
class IndefiniteArticle extends Modifier
{
    /**
     * Return "a" / "an" for the following phrase.
     *
     * @param  mixed  $value  The word or phrase the article precedes.
     * @param  array<int, string>  $params  Unused.
     * @param  array<string, mixed>  $context  Unused.
     */
    public function index($value, array $params, array $context): string
    {
        $following = \is_scalar($value) ? (string) $value : '';

        return ProviderNarrativeLanguage::indefiniteArticle($following);
    }
}
