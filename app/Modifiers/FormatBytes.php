<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier that formats byte counts into human-readable units.
 *
 * Usage examples:
 *
 * In Antlers, each `:` after the modifier name adds the next positional
 * parameter, in order: `system`, `display`, `precision`, `unit`. The form
 * `key="value"` in the pipe chain is not supported (use one segment per
 * parameter). To set only later parameters, repeat earlier defaults
 * (e.g., `decimal`, `abbreviated_unit_with_space`).
 *
 * - `{{ file_size | format_bytes }}` -> `1.5 kB` (input `1500`, defaults)
 * - `{{ file_size | format_bytes:binary }}` -> `1.5 KiB` (input `1536`)
 * - `{{ file_size | format_bytes:decimal:full_unit }}` (input `1500`) -> `1.5 kilobytes`
 * - `{{ file_size | format_bytes:decimal:no_unit_string }}` (input `1500`) -> `1.5`
 * - `{{ file_size | format_bytes:decimal:abbreviated_unit_with_space:0:Megabytes }}` (input `3000000`) -> `3 MB`
 * - `{{ file_size | format_bytes:decimal:abbreviated_unit_with_space:-1:kB }}` (input `1234`) -> `1.234 kB`
 *
 * Parameters (same order as positional segments):
 *
 * - `system` (string, default: `decimal`)
 *   - Controls the conversion base.
 *   - `decimal`: 1000 bytes per kB, 1000 kB per MB, etc.
 *   - `binary`: 1024 bytes per KiB, 1024 KiB per MiB, etc.
 *
 * - `display` (string, default: `abbreviated_unit_with_space`)
 *   - `no_unit_integer`: numeric `int|float` with no unit
 *   - `no_unit_string`: numeric string with no unit
 *   - `full_unit`: full unit label (e.g., `bytes`, `kilobytes`)
 *   - `abbreviated_unit_with_space`: abbreviated label with space
 *   - `abbreviated_unit_without_space`: abbreviated label without space
 *
 * - `precision` (int, default: `1`)
 *   - Number of decimal places to round to.
 *   - `-1` disables rounding and preserves available decimal precision.
 *   - Trailing zeroes are removed; whole numbers are returned without
 *     decimal places.
 *
 * - `unit` (string, default: `auto`)
 *   - `auto` selects the largest unit whose value is `>= 1`.
 *   - Supports case-insensitive aliases such as `B`, `bytes`, `kB`,
 *     `Kilobytes`, `MB`, `Megabytes`, `GB`, `Gigabyte`, and binary
 *     variants like `KiB`, `MiB`, and `GiB`.
 */
class FormatBytes extends Modifier
{
    /**
     * Format bytes according to modifier parameters.
     *
     * @param  mixed  $value  Byte value to format
     * @param  array<int|string, mixed>  $params  Modifier parameters
     * @param  array<int|string, mixed>  $context  Modifier context (unused)
     * @return int|float|string Formatted value based on display mode
     */
    public function index(
        $value,
        array $params,
        array $context
    ): int|float|string {
        // Cast the input value to bytes.
        $bytes = (int) (is_scalar($value) ? $value : 0);

        // Resolve the byte system from params.
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
            $bytes,
            $requestedUnit,
            $units
        );

        // Resolve the divisor for the selected unit key.
        $divisor = $units[$unitKey]['divisor'];

        // Compute the raw numeric value in the selected unit.
        $rawValue = $bytes / $divisor;

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

        // Free temporary variables before returning result.
        unset(
            $bytes,
            $display,
            $divisor,
            $numberString,
            $precision,
            $rawValue,
            $requestedUnit,
            $system,
            $unitDataBySystem,
            $unitKey,
            $units
        );

        // Return the formatted result.
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
            (string) (is_scalar($rawSystem) ? $rawSystem : '')
        );

        // Check if binary system was requested.
        if (
            in_array($normalizedSystem, ['binary', 'base2', 'base-2', '1024'], true)
        ) {
            // Free local variables before early return.
            unset(
                $normalizedSystem,
                $rawSystem
            );

            // Return binary system.
            return 'binary';
        }

        // Free local variables before return.
        unset(
            $normalizedSystem,
            $rawSystem
        );

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
                (string) (is_scalar($rawDisplay) ? $rawDisplay : '')
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
            'abbr_space' => 'abbreviated_unit_with_space',
            'abbreviated_unit_without_space' => 'abbreviated_unit_without_space',
            'abbr_without_space' => 'abbreviated_unit_without_space',
            'abbr_nospace' => 'abbreviated_unit_without_space',
        ];

        // Check if the display alias is supported.
        if (isset($aliases[$normalizedDisplay])) {
            // Resolve the canonical display mode.
            $displayMode = $aliases[$normalizedDisplay];

            // Free local variables before early return.
            unset(
                $aliases,
                $normalizedDisplay,
                $rawDisplay
            );

            // Return the resolved display mode.
            return $displayMode;
        }

        // Free local variables before return.
        unset(
            $aliases,
            $normalizedDisplay,
            $rawDisplay
        );

        // Return default display mode when unsupported.
        return 'abbreviated_unit_with_space';
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
        $precision = (int) (is_scalar($rawPrecision) ? $rawPrecision : 0);

        // Clamp precision to -1 minimum.
        if ($precision < -1) {
            // Update precision to supported minimum.
            $precision = -1;
        }

        // Free local variables before return.
        unset($rawPrecision);

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
            trim((string) (is_scalar($rawUnit) ? $rawUnit : ''))
        );

        // Check if the unit is empty.
        if ($normalizedUnit === '') {
            // Free local variables before early return.
            unset(
                $normalizedUnit,
                $rawUnit
            );

            // Return auto for empty unit.
            return 'auto';
        }

        // Free local variables before return.
        unset($rawUnit);

        // Return normalized unit identifier.
        return $normalizedUnit;
    }

    /**
     * Resolve canonical unit key.
     *
     * @param  int  $bytes  Input bytes
     * @param  string  $requestedUnit  Requested unit name
     * @param  array<string, array{abbr: string, divisor: positive-int, plural: string, singular: string}>  $units  Unit map
     * @return string Canonical unit key
     */
    private function resolveUnitKey(
        int $bytes,
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

                // Free canonical unit when unavailable in selected system.
                unset($canonicalUnit);
            }

            // Free aliases when explicit lookup fails.
            unset($aliases);
        }

        // Initialize absolute bytes for auto unit selection.
        $absoluteBytes = abs($bytes);

        // Build the ordered unit keys list.
        $unitKeys = array_keys($units);

        // Initialize selected key to bytes.
        $selectedUnitKey = 'B';

        // Iterate through unit keys from largest to smallest.
        for ($i = \count($unitKeys) - 1; $i >= 0; $i--) {
            // Resolve the current unit key.
            $candidateUnitKey = $unitKeys[$i];

            // Resolve the current unit divisor.
            $candidateDivisor = $units[$candidateUnitKey]['divisor'];

            // Check if the current unit is greater than or equal to one.
            if ($absoluteBytes >= $candidateDivisor) {
                // Update selected unit key to current candidate.
                $selectedUnitKey = $candidateUnitKey;

                // Free loop locals before leaving loop.
                unset(
                    $candidateDivisor,
                    $candidateUnitKey
                );

                // Stop iterating after selecting largest matching unit.
                break;
            }

            // Free loop locals before next iteration.
            unset(
                $candidateDivisor,
                $candidateUnitKey
            );
        }

        // Free local variables before return.
        unset(
            $absoluteBytes,
            $i,
            $unitKeys
        );

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
        // Render the number with or without rounding based on precision.
        $renderedNumber = $precision === -1
            ? \sprintf(
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

        // Check if trimming produced an empty string.
        if (
            $trimmedNumber === ''
            || $trimmedNumber === '-0'
        ) {
            // Free local variables before early return.
            unset(
                $renderedNumber,
                $trimmedNumber
            );

            // Return zero string.
            return '0';
        }

        // Free local variables before return.
        unset($renderedNumber);

        // Return normalized numeric string.
        return $trimmedNumber;
    }

    /**
     * Format final output value for requested display mode.
     *
     * @param  string  $numberString  Formatted number string
     * @param  string  $display  Display mode
     * @param  string  $unitKey  Canonical unit key
     * @param  array<string, array{abbr: string, divisor: positive-int, plural: string, singular: string}>  $units  Unit map
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

        // Resolve abbreviated unit label.
        $abbreviatedLabel = $units[$unitKey]['abbr'];

        // Resolve singular full unit label.
        $singularLabel = $units[$unitKey]['singular'];

        // Resolve plural full unit label.
        $pluralLabel = $units[$unitKey]['plural'];

        // Check if full unit display was requested.
        if ($display === 'full_unit') {
            // Resolve label based on singular numeric value.
            $fullLabel = $numberString === '1' ? $singularLabel : $pluralLabel;

            // Build full unit output value.
            $result = sprintf('%s %s', $numberString, $fullLabel);

            // Free local variables before return.
            unset(
                $abbreviatedLabel,
                $fullLabel,
                $pluralLabel,
                $singularLabel
            );

            // Return full unit output.
            return $result;
        }

        // Check if abbreviated display without space was requested.
        if ($display === 'abbreviated_unit_without_space') {
            // Build abbreviated output without space.
            $result = $numberString.$abbreviatedLabel;

            // Free local variables before return.
            unset(
                $abbreviatedLabel,
                $pluralLabel,
                $singularLabel
            );

            // Return abbreviated output without space.
            return $result;
        }

        // Build abbreviated output with space.
        $result = sprintf('%s %s', $numberString, $abbreviatedLabel);

        // Free local variables before return.
        unset(
            $abbreviatedLabel,
            $pluralLabel,
            $singularLabel
        );

        // Return abbreviated output with space.
        return $result;
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
        if (\array_key_exists($name, $params)) {
            // Return named parameter value.
            return $params[$name];
        }

        // Check if positional parameter exists.
        if (\array_key_exists($index, $params)) {
            // Return positional parameter value.
            return $params[$index];
        }

        // Return default parameter value.
        return $default;
    }

    /**
     * Build the per-system unit data map.
     *
     * @return array<string, array<string, array{abbr: string, divisor: positive-int, plural: string, singular: string}>>
     */
    private function unitDataBySystem(): array
    {
        return [
            'decimal' => [
                'B' => [
                    'abbr' => 'B',
                    'divisor' => 1,
                    'plural' => 'bytes',
                    'singular' => 'byte',
                ],
                'kB' => [
                    'abbr' => 'kB',
                    'divisor' => 1000,
                    'plural' => 'kilobytes',
                    'singular' => 'kilobyte',
                ],
                'MB' => [
                    'abbr' => 'MB',
                    'divisor' => 1000000,
                    'plural' => 'megabytes',
                    'singular' => 'megabyte',
                ],
                'GB' => [
                    'abbr' => 'GB',
                    'divisor' => 1000000000,
                    'plural' => 'gigabytes',
                    'singular' => 'gigabyte',
                ],
                'TB' => [
                    'abbr' => 'TB',
                    'divisor' => 1000000000000,
                    'plural' => 'terabytes',
                    'singular' => 'terabyte',
                ],
                'PB' => [
                    'abbr' => 'PB',
                    'divisor' => 1000000000000000,
                    'plural' => 'petabytes',
                    'singular' => 'petabyte',
                ],
                'EB' => [
                    'abbr' => 'EB',
                    'divisor' => 1000000000000000000,
                    'plural' => 'exabytes',
                    'singular' => 'exabyte',
                ],
            ],
            'binary' => [
                'B' => [
                    'abbr' => 'B',
                    'divisor' => 1,
                    'plural' => 'bytes',
                    'singular' => 'byte',
                ],
                'KiB' => [
                    'abbr' => 'KiB',
                    'divisor' => 1024,
                    'plural' => 'kibibytes',
                    'singular' => 'kibibyte',
                ],
                'MiB' => [
                    'abbr' => 'MiB',
                    'divisor' => 1048576,
                    'plural' => 'mebibytes',
                    'singular' => 'mebibyte',
                ],
                'GiB' => [
                    'abbr' => 'GiB',
                    'divisor' => 1073741824,
                    'plural' => 'gibibytes',
                    'singular' => 'gibibyte',
                ],
                'TiB' => [
                    'abbr' => 'TiB',
                    'divisor' => 1099511627776,
                    'plural' => 'tebibytes',
                    'singular' => 'tebibyte',
                ],
                'PiB' => [
                    'abbr' => 'PiB',
                    'divisor' => 1125899906842624,
                    'plural' => 'pebibytes',
                    'singular' => 'pebibyte',
                ],
                'EiB' => [
                    'abbr' => 'EiB',
                    'divisor' => 1152921504606846976,
                    'plural' => 'exbibytes',
                    'singular' => 'exbibyte',
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
            'b' => 'B',
            'byte' => 'B',
            'bytes' => 'B',
            'kb' => 'kB',
            'kbyte' => 'kB',
            'kbytes' => 'kB',
            'kilobyte' => 'kB',
            'kilobytes' => 'kB',
            'mb' => 'MB',
            'mbyte' => 'MB',
            'mbytes' => 'MB',
            'megabyte' => 'MB',
            'megabytes' => 'MB',
            'gb' => 'GB',
            'gbyte' => 'GB',
            'gbytes' => 'GB',
            'gigabyte' => 'GB',
            'gigabytes' => 'GB',
            'tb' => 'TB',
            'terabyte' => 'TB',
            'terabytes' => 'TB',
            'pb' => 'PB',
            'petabyte' => 'PB',
            'petabytes' => 'PB',
            'eb' => 'EB',
            'exabyte' => 'EB',
            'exabytes' => 'EB',
            'kib' => 'KiB',
            'kibibyte' => 'KiB',
            'kibibytes' => 'KiB',
            'mib' => 'MiB',
            'mebibyte' => 'MiB',
            'mebibytes' => 'MiB',
            'gib' => 'GiB',
            'gibibyte' => 'GiB',
            'gibibytes' => 'GiB',
            'tib' => 'TiB',
            'tebibyte' => 'TiB',
            'tebibytes' => 'TiB',
            'pib' => 'PiB',
            'pebibyte' => 'PiB',
            'pebibytes' => 'PiB',
            'eib' => 'EiB',
            'exbibyte' => 'EiB',
            'exbibytes' => 'EiB',
        ];
    }
}
