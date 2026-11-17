<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use App\Fieldtypes\Traits\PresetOptions;

/**
 * A Select fieldtype using the PresetOptions trait. Its preset definitions are
 * the trait's own unless $definitions holds a replacement, and it exposes the
 * trait's protected helpers.
 */
final class PresetOptionsHarness extends PresetOptionsHarnessParent
{
    use PresetOptions {
        getPresetDefinitions as private traitPresetDefinitions;
    }

    /** @var string */
    protected static $handle = 'preset_options_harness';

    /** @var ?array<string, array<string, mixed>> */
    public ?array $definitions = null;

    /** @return array<string, array<string, mixed>> */
    protected function getPresetDefinitions(): array
    {
        return $this->definitions ?? $this->traitPresetDefinitions();
    }

    /** @return array<array-key, mixed> */
    public function options(): array
    {
        return $this->getOptionsWithPreset();
    }

    /** @return array<array-key, mixed> */
    public function parentConfig(): array
    {
        return $this->getParentConfigWithConditionalVisibility();
    }

    public function mapScope(?string $scope, string $enumClass): ?string
    {
        return $this->mapLanguageScope($scope, $enumClass);
    }

    /** @return array<string, string> */
    public function dynamicOptions(string $preset): array
    {
        return $this->getDynamicPresetOptions($preset);
    }
}
