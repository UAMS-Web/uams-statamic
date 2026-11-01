<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use App\Fieldtypes\Assets;

/**
 * Exposes the protected file-size scope producers of the Assets fieldtype so the
 * test can drive them with a plain $config array (no Field needed).
 */
class AssetsFileSizeScopeProbe extends Assets
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    public function encodeForTest(array $config): array
    {
        return $this->encodedFileSizeScopeHandles($config);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function applyForTest(array $config): array
    {
        $this->applyFileSizeQueryScopeToConfig($config);

        return $config;
    }
}
