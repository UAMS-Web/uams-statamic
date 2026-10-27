<?php

declare(strict_types=1);

namespace App\Tags;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use JsonException;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Read a Laravel config value from Antlers when the key is allowlisted.
 *
 * Usage:
 *   {{ get_config key="crosswalk.mappings.CredentialRegistry.AssociateDegree.IscedA2011" }}
 *
 * Parameters:
 *   - key (required): Dot key as for the Laravel `config()` helper (e.g. crosswalk.mappings…).
 *   - format: when the resolved value is an array, set to `json` to output
 *     JSON; otherwise array values render as an empty string.
 */
class GetConfig extends Tags
{
    /**
     * Antlers handle for this tag.
     *
     * @var string
     */
    protected static $handle = 'get_config';

    /**
     * Resolve an allowlisted config value for the template.
     *
     * @return string|int|float|bool Scalar or JSON string for arrays when format=json
     */
    public function index(): string|int|float|bool
    {
        /** @var Parameters $params */
        $params = $this->params;

        /** @var string|int|float|bool|null $rawKey */
        $rawKey = $params->get('key');

        // Equivalent: a null or empty key normalizes to '', which no step below can
        // turn into a value. The markers sit on parent nodes, the placement that holds
        // on Windows, so the returned '' stays under test.
        // @pest-mutate-ignore: BooleanOrToBooleanAnd, RemoveEarlyReturn
        if (
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            $rawKey === null || $rawKey === ''
        ) {
            return '';
        }

        $normalizedKey = $this->normalizeConfigKey((string) $rawKey);

        // Equivalent: Config::get('') returns null, so an empty key that passes the
        // allowlist (Str::is('*', '') is true) still ends at the null return below.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            $normalizedKey === ''
        ) {
            return '';
        }

        if (! $this->isKeyAllowed($normalizedKey)) {
            return '';
        }

        $value = Config::get($normalizedKey);

        // Equivalent: null matches no branch below and falls through to the final ''.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($value === null) {
            return '';
        }

        // An array without format=json falls through to the final ''.
        if (\is_array($value)) {
            $format = $params->get('format');

            if (
                \is_string($format)
                && strtolower(trim($format)) === 'json'
            ) {
                try {
                    return json_encode(
                        $value,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                } catch (JsonException) {
                    return '';
                }
            }
        }

        if (\is_bool($value) || \is_int($value) || \is_float($value)) {
            return $value;
        }

        if (\is_string($value)) {
            return $value;
        }

        return '';
    }

    /**
     * Trim and optionally strip a leading `config.` segment (non-Laravel style).
     */
    private function normalizeConfigKey(string $key): string
    {
        // A blank key has no prefix to strip and trims to '' below
        $key = trim($key);

        if (str_starts_with($key, 'config.')) {
            $key = substr($key, \strlen('config.'));
        }

        return trim($key);
    }

    /**
     * Whether the key matches any pattern from config get_config.allowed_key_patterns.
     */
    private function isKeyAllowed(string $normalizedKey): bool
    {
        $patterns = Config::get('get_config.allowed_key_patterns');

        if (! \is_array($patterns) || $patterns === []) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (! \is_string($pattern)) {
                continue;
            }

            // Equivalent: Str::is('', $key) is false for the non-empty keys that reach here.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if ($pattern === '') {
                continue;
            }

            if (Str::is($pattern, $normalizedKey)) {
                return true;
            }
        }

        return false;
    }
}
