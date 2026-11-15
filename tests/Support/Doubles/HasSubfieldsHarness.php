<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use App\Fieldtypes\Traits\HasSubfields;
use Statamic\Fields\Fieldtype;

/**
 * A HasSubfields fieldtype whose subfield definitions, preset options and preset
 * conditions are whatever its public properties hold. It counts reads of the
 * definitions in $definitionReads and exposes the trait's protected helpers.
 */
final class HasSubfieldsHarness extends Fieldtype
{
    use HasSubfields {
        getSubfieldValidationRules as private traitSubfieldValidationRules;
    }

    /** @var string */
    protected static $handle = 'has_subfields_harness';

    /** @var list<array<string, mixed>> */
    public array $definitions = [];

    /** @var array<string, mixed> */
    public array $presets = [];

    /** @var array<string, array<string, mixed>> */
    public array $presetConditions = [];

    public int $definitionReads = 0;

    /**
     * The preset most recently passed to getSubfieldPresetIfCondition(), by subfield handle.
     *
     * @var array<string, ?string>
     */
    public array $presetArguments = [];

    /**
     * Subfield rules to use in place of the trait's own, as a fieldtype overriding
     * getSubfieldValidationRules() would return them.
     *
     * @var ?array<string, mixed>
     */
    public ?array $ruleOverrides = null;

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function getSubfieldValidationRules(array $config, bool $parentRequired): array
    {
        return $this->ruleOverrides ?? $this->traitSubfieldValidationRules($config, $parentRequired);
    }

    /** @return list<array<string, mixed>> */
    protected function getSubfieldDefinitions(): array
    {
        $this->definitionReads++;

        return $this->definitions;
    }

    /** @return array<string, mixed> */
    protected function getPresetOptions(): array
    {
        return $this->presets;
    }

    /** @return ?array<string, mixed> */
    protected function getSubfieldPresetIfCondition(string $handle, ?string $preset): ?array
    {
        $this->presetArguments[$handle] = $preset;

        return $this->presetConditions[$handle] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        return $this->getCachedSubfieldDefinitions();
    }

    /**
     * Use this config as the cached config, bypassing the defaults the config
     * fields would merge in, as when the Control Panel strips default-valued keys.
     *
     * @param  array<string, mixed>  $config
     */
    public function useConfig(array $config): void
    {
        $this->configCache = $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function validationRules(array $config, bool $parentRequired): array
    {
        return $this->getSubfieldValidationRules($config, $parentRequired);
    }

    /** @return array<string, mixed> */
    public function cachedConfig(): array
    {
        return $this->getCachedConfig();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, value: mixed}>
     */
    public function forStorage(array $data): array
    {
        return $this->formatSubfieldsForStorage($data);
    }

    /**
     * @param  list<mixed>  $data
     * @return array<string, mixed>
     */
    public function fromStorage(array $data): array
    {
        return $this->parseSubfieldsFromStorage($data);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function subfieldConfigFields(array $config = []): array
    {
        return $this->getSubfieldConfigFields($config);
    }

    /** @return ?array<string, mixed> */
    public function presetConfigField(): ?array
    {
        return $this->getPresetConfigField();
    }
}
