<?php

declare(strict_types=1);

namespace App\Rules;

use App\Fieldtypes\Assets;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Statamic\Assets\Asset as AssetItem;
use Statamic\Facades\Asset;

/**
 * Validates that every selected asset in an Assets field meets a minimum pixel
 * width and/or height, read from Statamic's STORED dimension meta
 * (`Asset::width()` / `Asset::height()`) rather than re-reading an uploaded file
 * with `getimagesize()`.
 *
 * Reading stored meta has two deliberate consequences (UAMS-Web/uams-statamic-archive#1299, follow-up to UAMS-Web/uams-statamic-archive#192):
 *
 *   - **Enforced on selection, not just upload.** The rule resolves the field's
 *     selected asset reference(s) and checks their dimensions, so an *already
 *     uploaded* undersized asset picked from the container is rejected on save —
 *     not only a fresh upload.
 *   - **HEIC-safe by construction.** When the image driver could not extract
 *     dimensions (GD cannot read HEIC/HEIF), `width()`/`height()` are null — or
 *     zero for a file it could not decode; the asset is then **skipped** (minimum
 *     unenforced) rather than false-rejected for being unmeasurable. A valid image
 *     is never rejected just because the driver could not measure it. **SVGs are
 *     skipped too** — a vector scales freely, so a pixel minimum does not apply.
 *
 * Scope: this validates STORED asset references (the value shape on entry save and
 * on selecting an existing asset). A raw `UploadedFile` value — a fresh front-end
 * form upload before it becomes a stored asset — is not measured here; enforcing
 * the minimum at that instant would require `getimagesize()`, which re-introduces
 * the HEIC false-reject this rule exists to avoid. Uploaded assets are validated
 * once stored.
 *
 * The caller only attaches this rule when the field is restricted to images
 * exclusively ({@see Assets::restrictsToImagesOnly()}), so a
 * minimum on a mixed/unrestricted field never rejects a valid non-image (the
 * UAMS-Web/uams-statamic-archive#192 image-only guard). Non-image assets that slip through would also be
 * skipped here, since they carry no width/height meta.
 */
class AssetMinimumDimensions implements ValidationRule
{
    /**
     * @param  int  $minWidth  Minimum width in pixels (0 = no width minimum).
     * @param  int  $minHeight  Minimum height in pixels (0 = no height minimum).
     * @param  list<string>  $containers  Container handles used to resolve bare-path references.
     */
    public function __construct(
        public readonly int $minWidth,
        public readonly int $minHeight,
        public readonly array $containers = [],
    ) {}

    /**
     * Run the validation rule against the field's selected asset reference(s).
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach ($this->references($value) as $reference) {
            $asset = $this->resolveAsset($reference);

            // An unresolvable reference is skipped — never false-reject something
            // we cannot load and measure.
            if (! $asset instanceof AssetItem) {
                continue;
            }

            // A vector image scales freely, so a pixel minimum is meaningless, and
            // Statamic reports an SVG's viewBox as its "dimensions" (often tiny).
            // Skip SVGs rather than false-reject a scalable asset.
            if ($asset->isSvg()) {
                continue;
            }

            $width = $asset->width();
            $height = $asset->height();
            // Unmeasurable (HEIC/HEIF, some SVG, a corrupt image, or any non-image
            // that slipped the image-only guard): the driver reports null — or a
            // zero/negative dimension for a file it could not decode. Skip rather
            // than reject; a real, measurable image always has positive width and
            // height, so anything non-positive means "could not measure", not
            // "too small".
            if (! is_numeric($width)) {
                continue;
            }

            if (! is_numeric($height)) {
                continue;
            }

            $width = (int) $width;
            $height = (int) $height;
            if ($width <= 0) {
                continue;
            }

            if ($height <= 0) {
                continue;
            }

            if ($this->minWidth > 0 && $width < $this->minWidth) {
                $fail($this->message($asset, $width, $height));

                return;
            }

            if ($this->minHeight > 0 && $height < $this->minHeight) {
                $fail($this->message($asset, $width, $height));

                return;
            }
        }
    }

    /**
     * Flatten the field value to a list of non-empty string references.
     *
     * @return list<string>
     */
    protected function references(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter(
            array_map(
                static fn ($reference): string => is_string($reference) ? $reference : '',
                $values,
            ),
            static fn (string $reference): bool => $reference !== '',
        ));
    }

    /**
     * Resolve an asset reference to a concrete asset, or null when it cannot be
     * loaded. A full `container::path` id is resolved directly; a bare path is
     * tried as `container::path` against each configured container in turn (the
     * canonical asset-id form Asset::find() understands).
     */
    protected function resolveAsset(string $reference): ?AssetItem
    {
        if (str_contains($reference, '::')) {
            return $this->find($reference);
        }

        foreach ($this->containers as $handle) {
            $asset = $this->find($handle.'::'.$reference);

            if ($asset instanceof AssetItem) {
                return $asset;
            }
        }

        return null;
    }

    /**
     * Find a concrete asset by its `container::path` id, or null when it does not
     * resolve to one.
     */
    private function find(string $id): ?AssetItem
    {
        $asset = Asset::find($id);

        return $asset instanceof AssetItem ? $asset : null;
    }

    /**
     * Build a failure message naming the required minimum and the asset's actual
     * dimensions.
     */
    protected function message(AssetItem $asset, int $width, int $height): string
    {
        $name = $asset->basename();

        if ($this->minWidth > 0 && $this->minHeight > 0) {
            return sprintf('%s must be at least %dx%d pixels; it is %dx%d.', $name, $this->minWidth, $this->minHeight, $width, $height);
        }

        if ($this->minWidth > 0) {
            return sprintf('%s must be at least %d pixels wide; it is %d.', $name, $this->minWidth, $width);
        }

        return sprintf('%s must be at least %d pixels tall; it is %d.', $name, $this->minHeight, $height);
    }
}
