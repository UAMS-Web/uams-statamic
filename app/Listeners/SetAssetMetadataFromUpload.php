<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\Assets\AssetUploadMetadataMapper;
use App\Services\Assets\AssetUploadTitleResolver;
use App\Services\Assets\PdfDocumentMetadataReader;
use Statamic\Assets\Asset;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Events\AssetReuploaded;
use Statamic\Events\AssetUploaded;

/**
 * Sets asset `title` and container-specific meta after upload from embedded metadata
 * or deslugified filename.
 */
class SetAssetMetadataFromUpload
{
    /**
     * Create the listener with upload metadata services.
     *
     * @param  AssetUploadTitleResolver  $titleResolver  Resolves embedded or filename-derived asset titles.
     * @param  PdfDocumentMetadataReader  $pdfDocumentMetadataReader  Loads merged PDF details once per upload.
     * @param  AssetUploadMetadataMapper  $metadataMapper  Maps embedded fields to blueprint handles by container.
     */
    public function __construct(
        private readonly AssetUploadTitleResolver $titleResolver,
        private readonly PdfDocumentMetadataReader $pdfDocumentMetadataReader,
        private readonly AssetUploadMetadataMapper $metadataMapper
    ) {}

    /**
     * Apply title and mapped meta from file metadata or original filename after upload.
     *
     * @param  AssetUploaded|AssetReuploaded  $event  Statamic event after an asset file is uploaded or replaced.
     */
    public function handle(
        AssetUploaded|AssetReuploaded $event
    ): void {
        // Resolve the Statamic asset from the event
        /** @var Asset $asset */
        $asset = $event->asset;

        // Track whether any blueprint fields were modified
        $changed = false;

        // Parse PDF details once when the upload is a PDF (title + excerpt mapping)
        $extension = strtolower((string) $asset->extension());

        // Load merged PDF metadata when the file is a PDF
        $preloadedPdfDetails = $extension === 'pdf'
            ? $this->pdfDocumentMetadataReader->loadDetails($asset)
            : null;

        // Prefer embedded title from getID3-backed metadata or preloaded PDF details
        $embeddedTitle = $this->titleResolver->resolveEmbeddedTitle(
            $asset,
            $preloadedPdfDetails
        );

        // Check if embedded title was found
        if (
            $embeddedTitle !== null
            && $embeddedTitle !== ''
        ) {
            // Store the embedded title on the asset blueprint `title` field
            $asset->set(
                'title',
                $embeddedTitle
            );

            // Mark the asset dirty for persistence
            $changed = true;
        } else {
            // Build fallback title from the original filename when possible
            $fallbackTitle = $this->resolveFilenameTitle(
                $event,
                $asset
            );

            // Apply filename-derived title when available
            if ($fallbackTitle !== '') {
                // Store the deslugified filename stem as the asset title
                $asset->set(
                    'title',
                    $fallbackTitle
                );

                // Mark the asset dirty for persistence
                $changed = true;
            }
        }

        // Resolve supplemental meta (excerpt, alt, caption, exempt_from_alt) by container
        $metaUpdates = $this->metadataMapper->resolveMetaUpdates(
            $asset,
            $preloadedPdfDetails
        );

        // Apply each mapped field to the asset
        foreach ($metaUpdates as $fieldHandle => $value) {
            // Write the mapped value onto the asset data
            $asset->set(
                $fieldHandle,
                $value
            );

            // Mark the asset dirty for persistence
            $changed = true;
        }

        // Skip persisting when nothing changed
        if (! $changed) {
            return;
        }

        // Persist meta so the control panel and indexes see updates
        $asset->save();
    }

    /**
     * Resolve display title from the upload's original name or stored stem.
     *
     * @param  AssetUploaded|AssetReuploaded  $event  Statamic event carrying the original client filename when available.
     * @param  AssetContract  $asset  Asset whose title stem may be derived from disk path.
     * @return string Title text for blueprint `title` (empty when no stem is available).
     */
    private function resolveFilenameTitle(
        AssetUploaded|AssetReuploaded $event,
        AssetContract $asset
    ): string {
        // Read original filename from the event when present
        $original = $event->originalFilename ?? '';

        // Check if the event carries a usable original filename
        if (
            \is_string($original)
            && $original !== ''
        ) {
            // Build title from client original name (preserves casing)
            return $this->titleResolver->titleFromOriginalFilename($original);
        }

        // Fall back to deslugifying the stored filename stem
        return $this->titleResolver->titleFromAssetFilenameStem($asset->filename());
    }
}
