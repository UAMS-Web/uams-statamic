<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets\Concerns;

use App\Services\IptcDigitalSourceValueResolver;

/**
 * Augmented-asset helper: computed IPTC Digital Source value/schema fields.
 */
trait ComputesAssetIptcDigitalSourceValues
{
    /**
     * Merge computed digital source keys into augmented asset key listings.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        // Read the parent augmented key list
        $parentKeys = parent::keys();

        // Normalize parent keys into a plain string array
        /** @var array<int, string> $normalizedParentKeys */
        $normalizedParentKeys = \is_array($parentKeys)
            ? $parentKeys
            : [];

        // Initialize computed key list for all digital source groups
        /** @var list<string> $computedKeys */
        $computedKeys = [];

        // Read supported digital source group handles
        $groupHandles = IptcDigitalSourceValueResolver::digitalSourceTypeGroupHandles();

        // Loop through each digital source group handle
        foreach ($groupHandles as $groupHandle) {
            // Build value/schema key names for this group handle
            $computedKeyPair = IptcDigitalSourceValueResolver::computedKeysForGroupHandle(
                $groupHandle
            );

            // Append the resolved value key to computed key list
            $computedKeys[] = $computedKeyPair['value'];

            // Append the resolved schema key to computed key list
            $computedKeys[] = $computedKeyPair['schema'];

            // Unset per-iteration variables to free memory
            unset(
                $computedKeyPair
            );
        }

        // Build final unique key list preserving parent keys first
        $mergedKeys = array_values(
            array_unique(
                [
                    ...$normalizedParentKeys,
                    ...$computedKeys,
                ]
            )
        );

        // Unset local variables to free memory
        unset(
            $computedKeys,
            $groupHandles,
            $groupHandle,
            $normalizedParentKeys,
            $parentKeys
        );

        // Return merged key list with computed digital source keys included
        return $mergedKeys;
    }

    /**
     * Computed resolved type key for `digital_source_type`.
     */
    protected function digitalSourceTypeValue(): string
    {
        // Resolve the computed value key from the primary digital source group
        return $this->resolveDigitalSourceTypeValueForGroup('digital_source_type');
    }

    /**
     * Computed schema.org URL for `digital_source_type`.
     */
    protected function digitalSourceTypeValueSchema(): string
    {
        // Resolve the schema URL from the primary digital source group
        return $this->resolveDigitalSourceTypeSchemaForGroup('digital_source_type');
    }

    /**
     * Computed resolved type key for `digital_source_type_text`.
     */
    protected function digitalSourceTypeTextDigitalSourceTypeValue(): string
    {
        // Resolve the computed value key from the text digital source group
        return $this->resolveDigitalSourceTypeValueForGroup('digital_source_type_text');
    }

    /**
     * Computed schema.org URL for `digital_source_type_text`.
     */
    protected function digitalSourceTypeTextDigitalSourceTypeValueSchema(): string
    {
        // Resolve the schema URL from the text digital source group
        return $this->resolveDigitalSourceTypeSchemaForGroup('digital_source_type_text');
    }

    /**
     * Computed resolved type key for `digital_source_type_portrait`.
     */
    protected function digitalSourceTypePortraitDigitalSourceTypeValue(): string
    {
        // Resolve the computed value key from the portrait digital source group
        return $this->resolveDigitalSourceTypeValueForGroup('digital_source_type_portrait');
    }

    /**
     * Computed schema.org URL for `digital_source_type_portrait`.
     */
    protected function digitalSourceTypePortraitDigitalSourceTypeValueSchema(): string
    {
        // Resolve the schema URL from the portrait digital source group
        return $this->resolveDigitalSourceTypeSchemaForGroup(
            'digital_source_type_portrait'
        );
    }

    /**
     * Computed resolved type key for `digital_source_type_image`.
     */
    protected function digitalSourceTypeImageDigitalSourceTypeValue(): string
    {
        // Resolve the computed value key from the image digital source group
        return $this->resolveDigitalSourceTypeValueForGroup('digital_source_type_image');
    }

    /**
     * Computed schema.org URL for `digital_source_type_image`.
     */
    protected function digitalSourceTypeImageDigitalSourceTypeValueSchema(): string
    {
        // Resolve the schema URL from the image digital source group
        return $this->resolveDigitalSourceTypeSchemaForGroup('digital_source_type_image');
    }

    /**
     * Computed resolved type key for `digital_source_type_audio`.
     */
    protected function digitalSourceTypeAudioDigitalSourceTypeValue(): string
    {
        // Resolve the computed value key from the audio digital source group
        return $this->resolveDigitalSourceTypeValueForGroup('digital_source_type_audio');
    }

    /**
     * Computed schema.org URL for `digital_source_type_audio`.
     */
    protected function digitalSourceTypeAudioDigitalSourceTypeValueSchema(): string
    {
        // Resolve the schema URL from the audio digital source group
        return $this->resolveDigitalSourceTypeSchemaForGroup('digital_source_type_audio');
    }

    /**
     * Computed resolved type key for `digital_source_type_video`.
     */
    protected function digitalSourceTypeVideoDigitalSourceTypeValue(): string
    {
        // Resolve the computed value key from the video digital source group
        return $this->resolveDigitalSourceTypeValueForGroup('digital_source_type_video');
    }

    /**
     * Computed schema.org URL for `digital_source_type_video`.
     */
    protected function digitalSourceTypeVideoDigitalSourceTypeValueSchema(): string
    {
        // Resolve the schema URL from the video digital source group
        return $this->resolveDigitalSourceTypeSchemaForGroup('digital_source_type_video');
    }

    /**
     * Resolve digital source type key for a given group handle.
     *
     * @param  string  $groupHandle  Group handle containing digital source data
     */
    private function resolveDigitalSourceTypeValueForGroup(
        string $groupHandle
    ): string {
        // Read the raw group payload from asset blueprint data
        $groupPayload = $this->getFromData($groupHandle);

        // Resolve the IPTC type key from the group payload
        return IptcDigitalSourceValueResolver::resolveFromGroupPayload($groupPayload);
    }

    /**
     * Resolve digital source schema URL for a given group handle.
     *
     * @param  string  $groupHandle  Group handle containing digital source data
     */
    private function resolveDigitalSourceTypeSchemaForGroup(
        string $groupHandle
    ): string {
        // Read the raw group payload from asset blueprint data
        $groupPayload = $this->getFromData($groupHandle);

        // Resolve the schema URL from the group payload
        return IptcDigitalSourceValueResolver::schemaUrlFromGroupPayload(
            $groupPayload
        );
    }
}
