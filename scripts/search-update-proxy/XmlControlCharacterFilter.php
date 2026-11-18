<?php

declare(strict_types=1);

namespace Scripts\SearchUpdateProxy;

use php_user_filter;

/**
 * A read filter that drops the C0 control characters XML 1.0 forbids, keeping
 * tab, line feed and carriage return.
 */
final class XmlControlCharacterFilter extends php_user_filter
{
    // cspell:ignore datalen PSFS

    public const string NAME = 'search-update-proxy.xml-control-characters';

    /**
     * @param  resource  $in  The incoming bucket brigade
     * @param  resource  $out  The outgoing bucket brigade
     * @param  int  $consumed  The bytes consumed
     * @param  bool  $closing  Whether the stream is closing
     */
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $consumed += $bucket->datalen;
            $bucket->data = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $bucket->data);
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
