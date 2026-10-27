<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets\Logos;

use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blink;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;

/**
 * Option labels for `logos` asset blueprint select-style fields, read from the
 * live blueprint so YAML stays the single source of truth.
 */
final class LogosBlueprintOptionLabels
{
    /**
     * Asset container handle for branded logo assets.
     */
    private const string CONTAINER_HANDLE = 'logos';

    /**
     * Cached value-keyed label maps for fields used in computed title/alt.
     *
     * @return array<string, array<string, string>> Field handle => stored value => label
     */
    public static function maps(): array
    {
        // Cache the label maps for the logos container
        /** @var array<string, array<string, string>> $maps */
        $maps = Blink::once(
            'app.support.logos-blueprint-option-label-maps',
            static function (): array {
                // Get the logos container
                $container = AssetContainer::find(self::CONTAINER_HANDLE);

                // Check if the container is not found
                if ($container === null) {
                    // Return empty label maps
                    return [
                        'orientation' => [],
                        'color_scheme' => [],
                    ];
                }

                // Get the blueprint for the container
                /** @var Blueprint $blueprint */
                $blueprint = $container->blueprint();

                // Return the label maps
                return [
                    'orientation' => self::mapForField($blueprint, 'orientation'),
                    'color_scheme' => self::mapForField($blueprint, 'color_scheme'),
                ];
            }
        );

        // Return the cached label maps
        return $maps;
    }

    /**
     * Build stored value => display label from a field's fieldtype options.
     *
     * @param  Blueprint  $blueprint  The blueprint to get the field from
     * @param  string  $fieldHandle  The handle of the field to get the map for
     * @return array<string, string> Stored value => display label
     */
    private static function mapForField(
        Blueprint $blueprint,
        string $fieldHandle
    ): array {
        // Check if the blueprint does not have the field
        if (! $blueprint->hasField($fieldHandle)) {
            // Return empty label map
            return [];
        }

        // Get the field from the blueprint
        /** @var Field $field */
        $field = $blueprint->field($fieldHandle);

        // Get the fieldtype for the field
        $fieldtype = $field->fieldtype();

        // Check if the fieldtype is not resolvable
        if (! $fieldtype instanceof Fieldtype) {
            // Return empty label map
            return [];
        }

        // Get the extra renderable field data for the field
        $extra = $fieldtype->extraRenderableFieldData();

        // Return the options for the field
        /** @var array<string, string> */
        return $extra['options'] ?? [];
    }
}
