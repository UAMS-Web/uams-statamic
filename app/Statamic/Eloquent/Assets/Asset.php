<?php

declare(strict_types=1);

namespace App\Statamic\Eloquent\Assets;

use DateTimeZone;
use Facades\Statamic\Assets\ExtractInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Statamic\Eloquent\Assets\Asset as EloquentDriverAsset;
use Statamic\Support\Arr;
use Throwable;

/**
 * Eloquent-backed Statamic asset with safe `lastModified` when meta omits
 * `last_modified`.
 *
 * Core Statamic assumes `meta('last_modified')` is always a Unix timestamp.
 * `App\Models\Asset` normalizes meta on save and during YAML sync; this class
 * remains a safety net when meta is still incomplete.
 */
class Asset extends EloquentDriverAsset
{
    /**
     * Get the asset bitrate in bits per second.
     */
    public function bitrate(): ?int
    {
        // Attempt to read bitrate from the cached/generated asset metadata.
        try {
            // Read raw bitrate value from metadata.
            $bitrate = $this->meta('bitrate');
        } catch (Throwable) {
            // Return null when metadata cannot be read.
            return null;
        }

        // Check whether bitrate is numeric and greater than zero.
        if (
            // A numeric string compares with 0 as a number in PHP 8, so the cast changes
            // nothing here; the cast below matters, since round() takes no string.
            // @pest-mutate-ignore: RemoveDoubleCast
            is_numeric($bitrate)
            && (float) $bitrate > 0
        ) {
            // Return normalized integer bitrate value.
            return (int) round((float) $bitrate);
        }

        // Return null when bitrate is missing or invalid.
        return null;
    }

    /**
     * Generate asset metadata and include bitrate for audio/video assets.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function generateMeta(): array
    {
        // Build baseline metadata from Statamic's Eloquent asset implementation.
        /** @var array<string, mixed> $meta */
        $meta = parent::generateMeta();

        // Check if the asset type can provide bitrate metadata.
        if (
            ! $this->isAudio()
            && ! $this->isVideo()
        ) {
            // Return baseline metadata when bitrate is not applicable.
            return $meta;
        }

        // Check if baseline metadata confirms the file exists on disk.
        if (! isset($meta['size'])) {
            // Return baseline metadata when disk file metadata is unavailable.
            return $meta;
        }

        // Attempt to analyze media metadata with getID3 through Statamic.
        try {
            // Resolve media analysis data from the current asset.
            /** @var array<string, mixed> $analysis */
            $analysis = ExtractInfo::fromAsset($this);
        } catch (Throwable) {
            // Return baseline metadata when getID3 cannot read the pending file.
            return $meta;
        }

        // Resolve bitrate from available getID3 keys.
        $bitrate = $this->resolveBitrateFromAnalysis($analysis);

        // Check if a bitrate value was successfully resolved.
        if ($bitrate !== null) {
            // Persist bitrate alongside duration and mime_type.
            $meta['bitrate'] = $bitrate;
        }

        // Clean up temporary analysis variables.
        unset(
            $analysis,
            $bitrate
        );

        // Return metadata including bitrate when available.
        return $meta;
    }

    /**
     * Resolve bitrate in bits per second from getID3 analysis output.
     *
     * @param  array<string, mixed>  $analysis  Parsed getID3 analysis payload
     * @return int|null Bitrate in bits per second or null if not found
     */
    protected function resolveBitrateFromAnalysis(
        array $analysis
    ): ?int {
        // Define preferred getID3 keys for bitrate extraction.
        $candidateKeys = [
            'audio.bitrate',
            'bitrate',
        ];

        // Iterate over candidate keys in priority order.
        foreach ($candidateKeys as $candidateKey) {
            // Extract the candidate bitrate value by key.
            $candidateValue = Arr::get(
                $analysis,
                $candidateKey
            );

            // Check if the candidate value is numeric and positive.
            if (
                // As in bitrate(), the cast changes no comparison with 0.
                // @pest-mutate-ignore: RemoveDoubleCast
                is_numeric($candidateValue)
                && (float) $candidateValue > 0
            ) {
                // Normalize and return bitrate as integer bits per second.
                return (int) round((float) $candidateValue);
            }
        }

        // Clean up iteration variables after candidate checks.
        unset(
            $candidateKeys,
            $candidateKey,
            $candidateValue
        );

        // Return null when no valid bitrate value is present.
        return null;
    }

    /**
     * Last modified time for this asset.
     */
    #[\Override]
    public function lastModified(): Carbon
    {
        // When cache_meta is true, full meta() avoids metaValue()'s generateMeta()
        // fallback for missing keys (which needs disk + container). When it is false,
        // meta('last_modified') reads the file, and answers null when the file is
        // missing; Statamic's parent would read that null as 0, the Unix epoch (UAMS-Web/uams-statamic-archive#2995).
        // config/statamic/assets.php always sets cache_meta, so the default is never read.
        // @pest-mutate-ignore: TrueToFalse
        if (config('statamic.assets.cache_meta', true)) {
            /** @var array<string, mixed> $meta */
            $meta = $this->meta();
            $timestamp = Arr::get($meta, 'last_modified');
        } else {
            $timestamp = $this->meta('last_modified');
        }

        // Check if meta exposes a numeric Unix timestamp
        if (is_numeric($timestamp)) {
            /** @var DateTimeZone|int|string|null $timezone */
            $timezone = config('app.timezone');

            return Carbon::createFromTimestamp(
                (int) $timestamp,
                $timezone
            );
        }

        // Resolve fallback timestamps from the backing Eloquent row
        /** @var Model|null $model */
        $model = $this->model();

        // Check if a backing model exists
        if ($model !== null) {
            $fallback = $model->getAttribute('updated_at') ?? $model->getAttribute('created_at');

            // Check if a model timestamp is present
            if ($fallback !== null) {
                /** @var DateTimeZone|int|string|null $timezone */
                $timezone = config('app.timezone');

                /** @var \DateTimeInterface|float|int|string $fallback */
                return Carbon::parse(
                    $fallback,
                    $timezone
                );
            }
        }

        // Use current time when meta and model provide no timestamp
        /** @var DateTimeZone|int|string|null $timezone */
        $timezone = config('app.timezone');

        return Carbon::now($timezone);
    }
}
