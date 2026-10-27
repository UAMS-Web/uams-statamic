<?php

declare(strict_types=1);

namespace App\Http\Requests\Classifieds;

use App\Support\Classifieds\ClassifiedCategories;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Statamic\Assets\AssetContainer;
use Statamic\Facades\AssetContainer as AssetContainers;

/**
 * Validates a classified listing posted or edited from the front end (UAMS-Web/uams-statamic-archive#2519).
 *
 * Signing in is enforced by `RedirectGuestToLogin` and ownership of an edited listing by
 * `ClassifiedListingController`, so this request authorizes any caller that reaches it.
 *
 * The title may not contain `<` or `>`. Every classifieds template escapes it, but two
 * shared surfaces print an entry title raw: the page header and the primary navigation
 * fallback. Refusing markup where a visitor writes a title is what keeps it out of both,
 * without changing how those surfaces treat titles the Control Panel and the importer write.
 */
class ClassifiedListingRequest extends FormRequest
{
    /**
     * Allow any caller that reaches the request; sign-in and ownership are checked elsewhere.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The image types a listing accepts, by extension. An upload must both be named
     * with one of these and CONTAIN one of these: the container's `extensions` rule reads
     * only the name the browser sent, so a file named `.jpg` holding HTML or SVG would
     * pass it alone, and `mimes` is what checks the content.
     *
     * @var list<string>
     */
    public const array IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * The rules for a listing's fields and uploaded images.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200', 'not_regex:/[<>]/'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'classified_categories' => ['nullable', 'array'],
            'classified_categories.*' => ['string', Rule::in(app(ClassifiedCategories::class)->slugs())],
            'contact_name' => ['required', 'string', 'max:200'],
            'contact_email' => ['required', 'string', 'email', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:200'],
            'images' => ['nullable', 'array', 'max:'.self::maxImages()],
            'images.*' => array_merge(['file', 'mimes:'.implode(',', self::IMAGE_EXTENSIONS)], $this->imageRules()),
        ];
    }

    /**
     * Human-readable messages for the rules a poster is most likely to meet.
     *
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'title.not_regex' => 'The title cannot contain the characters < or >.',
            'images.max' => 'A listing can carry at most :max images.',
            'images.*.mimes' => 'Each image must be a JPEG, PNG, GIF or WebP image.',
            'classified_categories.*.in' => 'Choose categories from the list.',
        ];
    }

    /**
     * The most images one listing may carry, from `classifieds.images.max_files`.
     */
    public static function maxImages(): int
    {
        $max = config('classifieds.images.max_files', 12);

        return is_int($max) ? $max : 12;
    }

    /**
     * The image container's own upload rules, so a front-end upload meets exactly what a
     * Control Panel upload does.
     *
     * @return list<string>
     */
    private function imageRules(): array
    {
        // Resolve the container the listing images land in
        $handle = config('classifieds.images.container', 'images');
        $container = AssetContainers::find(is_string($handle) ? $handle : 'images');

        // Fall back to a conservative size limit when the container is missing
        if (! $container instanceof AssetContainer) {
            return ['max:10240'];
        }

        /** @var array<int, mixed> $rules */
        $rules = $container->validationRules();

        /** @var list<string> $strings */
        $strings = array_values(array_filter($rules, is_string(...)));

        return $strings;
    }
}
