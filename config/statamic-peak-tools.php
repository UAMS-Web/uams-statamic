<?php

declare(strict_types=1);

/**
 * Statamic Peak Tools Configuration
 *
 * This configuration file allows you to customize the default picture formats
 * used by the `statamic-peak-tools::components/picture` partial. The formats
 * are used to generate responsive image sources in the `<picture>` element.
 *
 * How It Works:
 *   - All formats listed here are generated from the original uploaded image
 *     (regardless of the original format - JPEG, PNG, etc.)
 *   - The picture partial uses Glide (Statamic's image manipulation) to convert
 *     the original image to each format on-demand
 *   - The browser automatically selects the first format it supports
 *   - This provides optimal compression for modern browsers (WebP) while
 *     maintaining compatibility with older browsers (JPEG fallback)
 *
 * The formats array defines the order and types of image formats that will be
 * generated. Browsers will use the first format they support, so order matters.
 * Formats are listed from most modern/compressed to fallback.
 *
 * Example: If you upload a JPEG image, the system will generate:
 *   - WebP version (for modern browsers)
 *   - JPEG version (for legacy browsers)
 *
 * The browser will automatically use the best format it supports.
 *
 * @see  https://peak.1902.studio/features/images.html
 */
return [
    /**
     * Picture component configuration
     */
    'picture' => [
        /**
         * Default image formats for the picture partial
         *
         * Defines the image formats that will be generated for responsive images.
         * Each format is specified as 'format_extension' => 'mime/type'.
         *
         * All formats are generated from the original uploaded image using Glide
         * image manipulation, regardless of the original file format. The browser
         * automatically selects the first format it supports from this list.
         *
         * Format order matters: browsers will use the first format they support.
         * Formats are typically ordered from most modern/compressed to fallback:
         *   1. WebP - Widely supported modern format (good compression)
         *   2. JPG - Universal fallback format (widest browser support)
         *
         * Note: AVIF has been removed from the default formats because it is not
         * supported by the current Imagick installation. If AVIF support is added
         * to Imagick in the future, it can be re-added to this list.
         *
         * You can customize this array to:
         *   - Add additional formats (e.g., 'png' => 'image/png')
         *   - Remove formats you don't want to generate
         *   - Reorder formats to change priority
         *
         * Note: The picture partial will use these defaults unless overridden
         * via the `formats` parameter in the template.
         *
         * @var array<string, string> Format extension => MIME type mapping
         */
        'default_formats' => [
            'webp' => 'image/webp',
            'jpg' => 'image/jpeg',
        ],
    ],
];
