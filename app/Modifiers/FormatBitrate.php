<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier that formats bitrate values into human-readable units.
 *
 * Usage examples:
 *
 * In Antlers, each `:` after the modifier name adds the next positional
 * parameter, in order: `system`, `display`, `precision`, `unit`. The form
 * `key="value"` in the pipe chain is not supported (use one segment per
 * parameter). To set only later parameters, repeat earlier defaults
 * (e.g., `decimal`, `abbreviated_unit_with_space`).
 *
 * - `{{ bitrate | format_bitrate }}` -> `1.5 Mbit/s` (input `1500000`, defaults)
 * - `{{ bitrate | format_bitrate:binary }}` -> `1.5 kibit/s` (input `1536`)
 * - `{{ bitrate | format_bitrate:decimal:full_unit:1:bit }}` (input `1`) -> `1 bit per second`
 * - `{{ bitrate | format_bitrate:decimal:nonstandard_abbreviated_unit_with_space }}` (input `1500000`) -> `1.5 Mbps`
 * - `{{ bitrate | format_bitrate:decimal:abbreviated_unit_with_space:2:kbps }}` (input `1500000`) -> `1500 kbit/s`
 * - `{{ bitrate | format_bitrate:decimal:abbreviated_unit_with_space:-1:kb }}` (input `1234`) -> `1.234 kbit/s`
 *
 * Parameters (same order as positional segments; see `getParam()` in
 * `resolveSystem`, `resolveDisplay`, `resolvePrecision`,
 * `resolveRequestedUnit`):
 *
 * - `system` (string, default: `decimal`)
 *     - `decimal` or `binary` (aliases include `base2`, `1024`).
 * - `display` (string, default: `abbreviated_unit_with_space`)
 *     - See `resolveDisplay()` aliases (e.g. `full_unit`, `no_unit_string`,
 *       `nonstandard_abbreviated_unit_with_space`).
 * - `precision` (int, default: `1`)
 *     - `-1` keeps available decimals; trailing zeros are trimmed.
 * - `unit` (string, default: `auto`)
 *     - Explicit unit label or `auto`.
 */
class FormatBitrate extends Modifier
{
    /**
     * Format bitrate according to modifier parameters.
     *
     * @param  mixed  $value  Bitrate value in bits per second
     * @param  array<int|string, mixed>  $params  Modifier parameters
     * @param  array<int|string, mixed>  $context  Modifier context (unused)
     * @return int|float|string Formatted value based on display mode
     */
    public function index(
        $value,
        array $params,
        array $context
    ): int|float|string {
        // Cast the input value to bits-per-second.
        $bitsPerSecond = (int) (is_scalar($value) ? $value : 0);

        // Resolve the conversion system from params.
        $system = $this->resolveSystem($params);

        // Resolve the display mode from params.
        $display = $this->resolveDisplay($params);

        // Resolve the precision from params.
        $precision = $this->resolvePrecision($params);

        // Resolve the requested output unit from params.
        $requestedUnit = $this->resolveRequestedUnit($params);

        // Resolve the unit data map for the selected system.
        $unitDataBySystem = $this->unitDataBySystem();

        // Resolve the selected system units.
        $units = $unitDataBySystem[$system];

        // Resolve the normalized target unit key.
        $unitKey = $this->resolveUnitKey(
            $bitsPerSecond,
            $requestedUnit,
            $units
        );

        // Resolve the divisor for the selected unit key.
        $divisor = $units[$unitKey]['divisor'];

        // Compute the raw numeric value in the selected unit.
        $rawValue = $bitsPerSecond / $divisor;

        // Resolve the formatted number string after precision handling.
        $numberString = $this->formatNumberString(
            $rawValue,
            $precision
        );

        // Resolve the output value using the selected display mode.
        $result = $this->formatDisplayValue(
            $numberString,
            $display,
            $unitKey,
            $units
        );

        // Return the formatted output.
        return $result;
    }

    /**
     * Resolve display system from parameters.
     *
     * @param  array<int|string, mixed>  $params  Modifier parameters
     * @return string Either decimal or binary
     */
    private function resolveSystem(
        array $params
    ): string {
        // Resolve the raw system parameter.
        $rawSystem = $this->getParam(
            $params,
            'system',
            0,
            'decimal'
        );

        // Normalize the system parameter.
        $normalizedSystem = strtolower(
            (string) (is_scalar($rawSystem) ? $rawSystem : 'decimal')
        );

        // Check if binary system was requested.
        if (
            in_array($normalizedSystem, ['binary', 'base2', 'base-2', '1024'], true)
        ) {
            // Return binary system.
            return 'binary';
        }

        // Return decimal system by default.
        return 'decimal';
    }

    /**
     * Resolve display mode from parameters.
     *
     * @param  array<int|string, mixed>  $params  Modifier parameters
     * @return string Display mode identifier
     */
    private function resolveDisplay(
        array $params
    ): string {
        // Resolve the raw display parameter.
        $rawDisplay = $this->getParam(
            $params,
            'display',
            1,
            'abbreviated_unit_with_space'
        );

        // Normalize the display parameter.
        $normalizedDisplay = strtolower(
            str_replace(
                '-',
                '_',
                (string) (is_scalar($rawDisplay) ? $rawDisplay : 'abbreviated_unit_with_space')
            )
        );

        // Build supported display mode aliases.
        $aliases = [
            'no_unit_integer' => 'no_unit_integer',
            'noint' => 'no_unit_integer',
            'integer' => 'no_unit_integer',
            'int' => 'no_unit_integer',
            'no_unit_string' => 'no_unit_string',
            'nostring' => 'no_unit_string',
            'string' => 'no_unit_string',
            'str' => 'no_unit_string',
            'full_unit' => 'full_unit',
            'full' => 'full_unit',
            'long' => 'full_unit',
            'abbreviated_unit_with_space' => 'abbreviated_unit_with_space',
            'abbr_with_space' => 'abbreviated_unit_with_space',
            'abbreviated_unit_without_space' => 'abbreviated_unit_without_space',
            'abbr_without_space' => 'abbreviated_unit_without_space',
            'nonstandard_abbreviated_unit_with_space' => 'nonstandard_abbreviated_unit_with_space',
            'nonstandard_with_space' => 'nonstandard_abbreviated_unit_with_space',
            'nonstandard_abbreviated_unit_without_space' => 'nonstandard_abbreviated_unit_without_space',
            'nonstandard_without_space' => 'nonstandard_abbreviated_unit_without_space',
        ];

        // Return default display mode when unsupported.
        return $aliases[$normalizedDisplay] ?? 'abbreviated_unit_with_space';
    }

    /**
     * Resolve precision from parameters.
     *
     * @param  array<int|string, mixed>  $params  Modifier parameters
     * @return int Decimal places or -1 for no rounding
     */
    private function resolvePrecision(
        array $params
    ): int {
        // Resolve the raw precision parameter.
        $rawPrecision = $this->getParam(
            $params,
            'precision',
            2,
            1
        );

        // Cast precision to integer.
        $precision = (int) (is_scalar($rawPrecision) ? $rawPrecision : 1);

        // Clamp precision to -1 minimum.
        if ($precision < -1) {
            // Update precision to supported minimum.
            return -1;
        }

        // Return the normalized precision.
        return $precision;
    }

    /**
     * Resolve requested unit from parameters.
     *
     * @param  array<int|string, mixed>  $params  Modifier parameters
     * @return string Requested unit identifier or auto
     */
    private function resolveRequestedUnit(
        array $params
    ): string {
        // Resolve the raw unit parameter.
        $rawUnit = $this->getParam(
            $params,
            'unit',
            3,
            'auto'
        );

        // Normalize the unit parameter.
        $normalizedUnit = strtolower(
            trim((string) (is_scalar($rawUnit) ? $rawUnit : 'auto'))
        );

        // Check if the unit is empty.
        if ($normalizedUnit === '') {
            // Return auto for empty unit.
            return 'auto';
        }

        // Return normalized unit identifier.
        return $normalizedUnit;
    }

    /**
     * Resolve canonical unit key.
     *
     * @param  int  $bitsPerSecond  Input bitrate value
     * @param  string  $requestedUnit  Requested unit name
     * @param  array<string, array{abbr: string, abbr_nonstandard: string, divisor: positive-int, plural: string, singular: string}>  $units  Unit map
     * @return string Canonical unit key
     */
    private function resolveUnitKey(
        int $bitsPerSecond,
        string $requestedUnit,
        array $units
    ): string {
        // Resolve explicit unit key when provided.
        if ($requestedUnit !== 'auto') {
            // Build unit aliases for explicit lookup.
            $aliases = $this->buildUnitAliases();

            // Check if the requested unit alias is known.
            if (isset($aliases[$requestedUnit])) {
                // Resolve the canonical key from alias map.
                $canonicalUnit = $aliases[$requestedUnit];

                // Check if canonical unit exists in selected system.
                if (isset($units[$canonicalUnit])) {
                    // Return explicit canonical unit key.
                    return $canonicalUnit;
                }
            }
        }

        // Initialize absolute value for auto unit selection.
        $absoluteBitsPerSecond = abs($bitsPerSecond);

        // Build the ordered unit keys list.
        $unitKeys = array_keys($units);

        // Initialize selected key to bits.
        $selectedUnitKey = 'b';

        // Iterate through unit keys from largest to smallest.
        for ($i = \count($unitKeys) - 1; $i >= 0; $i--) {
            // Resolve the current unit key.
            $candidateUnitKey = $unitKeys[$i];

            // Resolve the current unit divisor.
            $candidateDivisor = $units[$candidateUnitKey]['divisor'];

            // Check if the current unit is greater than or equal to one.
            if ($absoluteBitsPerSecond >= $candidateDivisor) {
                // Update selected unit key to current candidate.
                $selectedUnitKey = $candidateUnitKey;

                // Stop iterating after selecting largest matching unit.
                break;
            }
        }

        // Return the selected unit key.
        return $selectedUnitKey;
    }

    /**
     * Format number value to normalized string.
     *
     * @param  float  $value  Numeric unit value
     * @param  int  $precision  Decimal places or -1 for no rounding
     * @return string Normalized numeric string
     */
    private function formatNumberString(
        float $value,
        int $precision
    ): string {
        // Render numeric text with or without rounding.
        $renderedNumber = $precision === -1
            ? sprintf(
                '%.14F',
                $value
            )
            : number_format(
                $value,
                $precision,
                '.',
                ''
            );

        // Initialize trimmed output with rendered numeric text.
        $trimmedNumber = $renderedNumber;

        // Check if the rendered value has a decimal point.
        if (str_contains($trimmedNumber, '.')) {
            // Trim trailing zeros from the decimal portion.
            $trimmedNumber = rtrim(
                $trimmedNumber,
                '0'
            );

            // Trim trailing decimal point when decimals are removed.
            $trimmedNumber = rtrim(
                $trimmedNumber,
                '.'
            );
        }

        // Check if trimming produced empty or negative-zero output.
        if (
            $trimmedNumber === ''
            || $trimmedNumber === '-0'
        ) {
            // Return normalized zero output.
            return '0';
        }

        // Return normalized numeric text.
        return $trimmedNumber;
    }

    /**
     * Format final output value for requested display mode.
     *
     * @param  string  $numberString  Formatted number string
     * @param  string  $display  Display mode
     * @param  string  $unitKey  Canonical unit key
     * @param  array<string, array{abbr: string, abbr_nonstandard: string, divisor: positive-int, plural: string, singular: string}>  $units  Unit map
     * @return int|float|string Final display value
     */
    private function formatDisplayValue(
        string $numberString,
        string $display,
        string $unitKey,
        array $units
    ): int|float|string {
        // Check if output must be numeric.
        if ($display === 'no_unit_integer') {
            // Check if number has a decimal point.
            if (str_contains($numberString, '.')) {
                // Cast and return a float result.
                return (float) $numberString;
            }

            // Cast and return an integer result.
            return (int) $numberString;
        }

        // Check if output must be plain numeric string.
        if ($display === 'no_unit_string') {
            // Return numeric string without unit.
            return $numberString;
        }

        // Resolve abbreviated standard unit label.
        $abbreviatedLabel = $units[$unitKey]['abbr'];

        // Resolve abbreviated nonstandard unit label.
        $nonstandardLabel = $units[$unitKey]['abbr_nonstandard'];

        // Resolve singular full unit label.
        $singularLabel = $units[$unitKey]['singular'];

        // Resolve plural full unit label.
        $pluralLabel = $units[$unitKey]['plural'];

        // Check if full unit display was requested.
        if ($display === 'full_unit') {
            // Resolve label based on singular numeric value.
            $fullLabel = $numberString === '1' ? $singularLabel : $pluralLabel;

            // Return full unit output.
            return sprintf('%s %s', $numberString, $fullLabel);
        }

        // Check if standard abbreviated display without space was requested.
        if ($display === 'abbreviated_unit_without_space') {
            // Return standard abbreviated output without space.
            return $numberString.$abbreviatedLabel;
        }

        // Check if nonstandard abbreviated display with space was requested.
        if ($display === 'nonstandard_abbreviated_unit_with_space') {
            // Return nonstandard abbreviated output with space.
            return sprintf('%s %s', $numberString, $nonstandardLabel);
        }

        // Check if nonstandard abbreviated display without space was requested.
        if ($display === 'nonstandard_abbreviated_unit_without_space') {
            // Return nonstandard abbreviated output without space.
            return $numberString.$nonstandardLabel;
        }

        // Return standard abbreviated output with space.
        return sprintf('%s %s', $numberString, $abbreviatedLabel);
    }

    /**
     * Resolve named or positional parameter with default fallback.
     *
     * @param  array<int|string, mixed>  $params  Modifier parameters
     * @param  string  $name  Named parameter key
     * @param  int  $index  Positional index fallback
     * @param  mixed  $default  Default value
     * @return mixed Resolved parameter value
     */
    private function getParam(
        array $params,
        string $name,
        int $index,
        mixed $default
    ): mixed {
        // Check if named parameter exists.
        if (array_key_exists($name, $params)) {
            // Return named parameter value.
            return $params[$name];
        }

        // Check if positional parameter exists.
        if (array_key_exists($index, $params)) {
            // Return positional parameter value.
            return $params[$index];
        }

        // Return default parameter value.
        return $default;
    }

    /**
     * Build the per-system unit data map.
     *
     * @return array<string, array<string, array{abbr: string, abbr_nonstandard: string, divisor: positive-int, plural: string, singular: string}>>
     */
    private function unitDataBySystem(): array
    {
        return [
            'decimal' => [
                'b' => [
                    'abbr' => 'bit/s',
                    'abbr_nonstandard' => 'bps',
                    'divisor' => 1,
                    'plural' => 'bits per second',
                    'singular' => 'bit per second',
                ],
                'kb' => [
                    'abbr' => 'kbit/s',
                    'abbr_nonstandard' => 'kbps',
                    'divisor' => 1000,
                    'plural' => 'kilobits per second',
                    'singular' => 'kilobit per second',
                ],
                'Mb' => [
                    'abbr' => 'Mbit/s',
                    'abbr_nonstandard' => 'Mbps',
                    'divisor' => 1000000,
                    'plural' => 'megabits per second',
                    'singular' => 'megabit per second',
                ],
                'Gb' => [
                    'abbr' => 'Gbit/s',
                    'abbr_nonstandard' => 'Gbps',
                    'divisor' => 1000000000,
                    'plural' => 'gigabits per second',
                    'singular' => 'gigabit per second',
                ],
                'Tb' => [
                    'abbr' => 'Tbit/s',
                    'abbr_nonstandard' => 'Tbps',
                    'divisor' => 1000000000000,
                    'plural' => 'terabits per second',
                    'singular' => 'terabit per second',
                ],
            ],
            'binary' => [
                'b' => [
                    'abbr' => 'bit/s',
                    'abbr_nonstandard' => 'bps',
                    'divisor' => 1,
                    'plural' => 'bits per second',
                    'singular' => 'bit per second',
                ],
                'kib' => [
                    'abbr' => 'kibit/s',
                    'abbr_nonstandard' => 'kibbps',
                    'divisor' => 1024,
                    'plural' => 'kibibits per second',
                    'singular' => 'kibibit per second',
                ],
                'Mib' => [
                    'abbr' => 'Mibit/s',
                    'abbr_nonstandard' => 'Mibbps',
                    'divisor' => 1048576,
                    'plural' => 'mebibits per second',
                    'singular' => 'mebibit per second',
                ],
                'Gib' => [
                    'abbr' => 'Gibit/s',
                    'abbr_nonstandard' => 'Gibbps',
                    'divisor' => 1073741824,
                    'plural' => 'gibibits per second',
                    'singular' => 'gibibit per second',
                ],
                'Tib' => [
                    'abbr' => 'Tibit/s',
                    'abbr_nonstandard' => 'Tibbps',
                    'divisor' => 1099511627776,
                    'plural' => 'tebibits per second',
                    'singular' => 'tebibit per second',
                ],
            ],
        ];
    }

    /**
     * Build supported unit aliases.
     *
     * @return array<string, string> Alias map to canonical unit keys
     */
    private function buildUnitAliases(): array
    {
        return [
            'b' => 'b',
            'bit' => 'b',
            'bits' => 'b',
            'bps' => 'b',
            'bit/s' => 'b',
            'kb' => 'kb',
            'kilobit' => 'kb',
            'kilobits' => 'kb',
            'kbps' => 'kb',
            'kbit/s' => 'kb',
            'kib' => 'kib',
            'kibibit' => 'kib',
            'kibibits' => 'kib',
            'kibbps' => 'kib',
            'kibit/s' => 'kib',
            'mb' => 'Mb',
            'megabit' => 'Mb',
            'megabits' => 'Mb',
            'mbps' => 'Mb',
            'mbit/s' => 'Mb',
            'mib' => 'Mib',
            'mebibit' => 'Mib',
            'mebibits' => 'Mib',
            'mibbps' => 'Mib',
            'mibit/s' => 'Mib',
            'gb' => 'Gb',
            'gigabit' => 'Gb',
            'gigabits' => 'Gb',
            'gbps' => 'Gb',
            'gbit/s' => 'Gb',
            'gib' => 'Gib',
            'gibibit' => 'Gib',
            'gibibits' => 'Gib',
            'gibbps' => 'Gib',
            'gibit/s' => 'Gib',
            'tb' => 'Tb',
            'terabit' => 'Tb',
            'terabits' => 'Tb',
            'tbps' => 'Tb',
            'tbit/s' => 'Tb',
            'tib' => 'Tib',
            'tebibit' => 'Tib',
            'tebibits' => 'Tib',
            'tibbps' => 'Tib',
            'tibit/s' => 'Tib',
        ];
    }
}
