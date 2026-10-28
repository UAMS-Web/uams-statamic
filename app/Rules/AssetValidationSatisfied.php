<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Statamic\Assets\AssetValidationSatisfiedResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Statamic\Assets\Asset;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\AssetContainer;

/**
 * Validate that a selected asset satisfies its blueprint validation rules.
 */
class AssetValidationSatisfied implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute  Name of the validated field
     * @param  mixed  $value  Incoming asset value or reference
     * @param  Closure  $fail  Closure to call if validation fails
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // Check if the field value is empty and should be skipped by this rule
        if ($this->isEmptyAssetSelection($value)) {
            // Return early because optional empty asset selections are valid
            return;
        }

        // Resolve the input value into a Statamic asset instance
        $asset = $this->resolveAsset($value);

        // Check if the asset could not be resolved
        if (! $asset instanceof StatamicAsset) {
            // Fail validation when the value is not a valid asset reference
            $fail(__('statamic::validation.asset_validation_satisfied.default'));

            // Return early after failing validation
            return;
        }

        // Resolve whether the selected asset satisfies blueprint validation
        $isValidationSatisfied = AssetValidationSatisfiedResolver::resolve($asset);

        // Check if the asset does not satisfy blueprint validation
        if (! $isValidationSatisfied) {
            // Fail validation with a message specific to asset validation status
            $fail(__('statamic::validation.asset_validation_satisfied.unsatisfied'));
        }

        // Unset local variables to free memory
        unset(
            $asset,
            $isValidationSatisfied
        );
    }

    /**
     * Resolve a rule input value into a Statamic asset instance.
     *
     * @param  mixed  $value  Validation input value
     * @return StatamicAsset|null Resolved asset, or null when unresolved
     */
    private function resolveAsset(
        mixed $value
    ): ?StatamicAsset {
        // Check if the incoming value is an array payload from the assets fieldtype
        if (\is_array($value)) {
            // Normalize array payload to the first candidate asset value
            $value = $this->resolveArrayAssetCandidate($value);
        }

        // Check if the incoming value is already a Statamic asset instance
        if ($value instanceof StatamicAsset) {
            // Return the provided asset instance directly
            return $value;
        }

        // Check if the value is not a non-empty string asset reference. The empty
        // string never reaches here: isEmptyAssetSelection() has already returned for
        // '', a blank string and a blank array candidate, so EmptyStringToNotEmpty on
        // the second clause cannot be told apart from the original (#255).
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if (
            ! \is_string($value)
            || $value === ''
        ) {
            // Return null for unsupported value types
            return null;
        }

        // Check if the string value includes a container-qualified asset ID
        if (\str_contains($value, '::')) {
            // Resolve and return asset by qualified ID
            /** @var Asset|null $asset */
            $asset = AssetFacade::find($value);

            return $asset;
        }

        // Attempt to resolve asset by URL-style path fallback
        /** @var Asset|null $assetByPath */
        $assetByPath = AssetFacade::findByPath($value);

        // Check if path fallback resolved an asset
        if ($assetByPath !== null) {
            // Return the asset resolved by path
            return $assetByPath;
        }

        // Loop through all asset containers to resolve relative stored paths
        foreach (AssetContainer::all() as $container) {
            // Attempt to resolve asset by container + relative path
            /** @var \Statamic\Assets\AssetContainer $container */
            $containerAsset = $container->asset($value);

            // Check if this container resolved the asset path
            if ($containerAsset !== null) {
                // Unset local variables to free memory
                unset(
                    $assetByPath,
                    $container
                );

                // Return the resolved asset from current container
                return $containerAsset;
            }
        }

        // Unset local variables to free memory
        unset(
            $assetByPath,
            $containerAsset,
            $container
        );

        // Return null when no asset can be resolved from provided value
        return null;
    }

    /**
     * Determine whether the incoming value represents an empty asset selection.
     *
     * @param  mixed  $value  Validation input value
     * @return bool True when no asset was selected
     */
    private function isEmptyAssetSelection(
        mixed $value
    ): bool {
        // Check if the value is explicitly null
        if ($value === null) {
            // Return true because null means no selected asset
            return true;
        }

        // Check if the value is an empty string
        if (
            \is_string($value)
            && \trim($value) === ''
        ) {
            // Return true because blank string means no selected asset
            return true;
        }

        // Check if the value is an array payload from assets fieldtype
        if (\is_array($value)) {
            // Check if the array is empty. Without this return an empty array has
            // no candidate, so the null-candidate check below returns true as well.
            // @pest-mutate-ignore: RemoveEarlyReturn
            if ($value === []) {
                // Return true because empty array means no selected asset
                return true;
            }

            // Extract the first candidate asset value from the array payload
            $candidateValue = $this->resolveArrayAssetCandidate($value);

            // Check if no candidate value exists in array payload
            if ($candidateValue === null) {
                // Return true because no candidate means no selected asset
                return true;
            }

            // Check if the candidate value is a blank string
            if (
                \is_string($candidateValue)
                && \trim($candidateValue) === ''
            ) {
                // Return true because blank candidate means no selected asset
                return true;
            }

            // Unset local variables to free memory
            unset($candidateValue);
        }

        // Return false because the value represents a non-empty selection
        return false;
    }

    /**
     * Resolve the first asset candidate from array-style field values.
     *
     * @param  array<mixed>  $value  Raw array value from validation input
     * @return mixed First candidate value or null when unresolved
     */
    private function resolveArrayAssetCandidate(array $value): mixed
    {
        // Check if the array contains a numeric first item
        if (\array_key_exists(0, $value)) {
            // Return the first numeric item as the candidate value
            return $value[0];
        }

        // Check if the array includes an explicit id key
        if (\array_key_exists('id', $value)) {
            // Return the id value as the candidate value
            return $value['id'];
        }

        // Check if the array includes an explicit value key
        if (\array_key_exists('value', $value)) {
            // Return the value key payload as the candidate value
            return $value['value'];
        }

        // Return null when no known candidate key is present
        return null;
    }
}
