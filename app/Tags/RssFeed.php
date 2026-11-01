<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\Http\OutboundUrlGuard;
use App\Support\Statamic\Tags\TagParam;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Provides Statamic tags for fetching and parsing RSS/Atom feeds.
 *
 * Allows retrieval of RSS feed items from external RSS or Atom feeds.
 *
 * Feed URLs are limited to HTTP(S) for safety. Templates are staff-controlled;
 * requests use Laravel's HTTP client with timeouts and bounded redirects.
 * Parsed items are cached for `config('rss_feed.cache_ttl_seconds')` seconds
 * (override with `RSS_FEED_CACHE_TTL` in the environment). When the TTL is
 * zero or negative, each request fetches the feed without caching.
 *
 * Usage:
 *   {{ rss_feed url="https://example.com/feed" limit="5" }}
 *     {{ items }}
 *       {{ title }}
 *       {{ permalink }}
 *       {{ date }}
 *       {{ author }}
 *       {{ description }}
 *     {{ /items }}
 *   {{ /rss_feed }}
 */
class RssFeed extends Tags
{
    /**
     * The {{ rss_feed }} tag.
     *
     * Fetches and parses an RSS or Atom feed, returning feed items.
     *
     * @return array<array-key, mixed>|string
     */
    public function index(): mixed
    {
        // Resolve tag parameters to a plain array (see {@see TagParam::all()}).
        /** @var Parameters $params */
        $params = $this->params;
        $paramBag = TagParam::all($params);

        // Get the feed URL from parameters
        $url = Arr::get(
            $paramBag,
            'url'
        );

        // Normalize URL to a trimmed string for validation and fetching
        $url = trim(\is_scalar($url) ? (string) $url : '');

        // Check if URL was provided
        if ($url === '') {
            // Return empty array if no URL provided
            return $this->parseNoResults();
        }

        // Validate URL is HTTP or HTTPS only (blocks file:// and other schemes). An
        // empty URL has already returned above, so no `required` rule is needed.
        $urlValidator = Validator::make(
            ['url' => $url],
            ['url' => ['url:http,https']],
        );

        // Check if URL validation failed
        if ($urlValidator->fails()) {
            // Check if 'as' parameter is provided for alias
            $as = Arr::get(
                $paramBag,
                'as'
            );

            // Narrow alias to a string only when a scalar value was supplied
            if ($as && \is_scalar($as)) {
                // Return structure with alias key and metadata matching empty fetch
                return [
                    (string) $as => [],
                    'total_results' => 0,
                    'no_results' => true,
                ];
            }

            // Return empty result if no alias specified
            return $this->parseNoResults();
        }

        // ---
        // Get limit from parameters (default to 10). Use 'items_to_show' to avoid
        // conflict with Antlers' reserved 'limit' parameter
        // ---
        $limitParam = Arr::get(
            $paramBag,
            'items_to_show',
            Arr::get(
                $paramBag,
                'limit',
                10
            )
        );
        // A non-scalar limit yields no items; -1 would end the loop just as early.
        // @pest-mutate-ignore: DecrementInteger
        $limit = \is_scalar($limitParam) ? (int) $limitParam : 0;

        // ---
        // Get excerpt limit from parameters (optional). Use 'excerpt_char_limit' to
        // avoid potential conflicts
        // ---
        $excerptLimit = Arr::get(
            $paramBag,
            'excerpt_char_limit',
            Arr::get(
                $paramBag,
                'excerpt_limit'
            )
        );

        // Convert empty string to null for excerpt limit, otherwise cast to integer
        $excerptLimit = ($excerptLimit && \is_scalar($excerptLimit)) ? (int) $excerptLimit : null;

        // Resolve cache TTL from application configuration
        $cacheTtlConfig = config('rss_feed.cache_ttl_seconds');
        // A non-numeric TTL takes the uncached branch below, and -1 would take it too.
        // @pest-mutate-ignore: DecrementInteger
        $cacheTtlSeconds = \is_numeric($cacheTtlConfig) ? (int) $cacheTtlConfig : 0;

        // Check if feed caching is disabled or misconfigured
        if ($cacheTtlSeconds <= 0) {
            // Fetch and parse the feed without using the cache
            $items = $this->fetchFeed(
                $url,
                $limit,
                $excerptLimit
            );
        } else {
            // Build a stable cache key for this URL and parse parameters
            $cacheKey = $this->buildRssFeedCacheKey(
                $url,
                $limit,
                $excerptLimit
            );

            // Resolve parsed items from cache or fetch and parse the feed
            $items = Cache::remember(
                $cacheKey,
                $cacheTtlSeconds,
                fn (): array => $this->fetchFeed(
                    $url,
                    $limit,
                    $excerptLimit
                )
            );
        }

        // Check if items were fetched
        if (empty($items)) {
            // Check if 'as' parameter is provided for alias
            $as = Arr::get(
                $paramBag,
                'as'
            );

            // Narrow alias to a string only when a scalar value was supplied
            if ($as && \is_scalar($as)) {
                // Return structure with alias key and metadata
                return [
                    (string) $as => [],
                    'total_results' => 0,
                    'no_results' => true,
                ];
            }

            // Return empty array if no alias specified
            return $this->parseNoResults();
        }

        // Check if 'as' parameter is provided for alias
        $as = Arr::get(
            $paramBag,
            'as'
        );

        // Narrow alias to a string only when a scalar value was supplied
        if ($as && \is_scalar($as)) {
            // ---
            // Return structure with alias key containing items array. This allows the
            // template to loop over {{ feed_item }} etc.
            // ---
            return [
                (string) $as => $items,
                'total_results' => \count($items),
                'no_results' => false,
            ];
        }

        // If no 'as' parameter, use parseLoop for direct iteration
        return $this->parseLoop($items);
    }

    /**
     * Build a deterministic cache key for parsed RSS feed items.
     *
     * The key includes the feed URL and parsing parameters so different
     * `items_to_show` or excerpt limits do not share one cached payload.
     *
     * @param  string  $url  Validated HTTP(S) feed URL
     * @param  int  $limit  Maximum number of items stored in the cached array
     * @param  ?int  $excerptLimit  Optional description character limit, or null
     * @return string Cache store key
     */
    private function buildRssFeedCacheKey(
        string $url,
        int $limit,
        ?int $excerptLimit
    ): string {
        // Encode excerpt absence distinctly from zero-length limits
        // sprintf() renders an int and its string form alike under %s.
        // @pest-mutate-ignore: RemoveStringCast
        $excerptSegment = $excerptLimit === null ? 'none' : (string) $excerptLimit;

        // Hash URL and parameters so keys stay bounded for long URLs
        return 'rss_feed:'.hash(
            'sha256',
            sprintf('%s|%d|%s', $url, $limit, $excerptSegment)
        );
    }

    /**
     * Fetches and parses an RSS or Atom feed.
     *
     * @param  string  $url  The feed URL (must already pass HTTP(S) validation)
     * @param  int  $limit  Maximum number of items to return
     * @param  ?int  $excerptLimit  Optional character limit for descriptions
     * @return list<array{title: string, permalink: string, date: string, authors: list<string>, author: string, description: string}> Array of feed items with title, permalink, date, author, and description
     */
    private function fetchFeed(
        string $url,
        int $limit,
        ?int $excerptLimit = null
    ): array {
        // Initialize items array
        $items = [];

        // Reject internal/loopback/link-local/private targets before any fetch
        // so an editor-supplied feed URL cannot reach internal services (SSRF).
        // OutboundUrlGuard::fetch() pins each hop and throws on the same targets
        // before sending, so dropping this early return still fetches nothing.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! OutboundUrlGuard::isAllowedUrl($url)) {
            return [];
        }

        // Disable libxml errors for cleaner error handling; the finally block below
        // restores the mode found on every path, a thrown fetch included
        $libxmlPreviousState = \libxml_use_internal_errors(true);

        // Wrap feed fetching in try-catch block to handle exceptions gracefully
        try {
            // Fetch the feed content over HTTP(S) through the egress guard: the
            // connection is pinned to a vetted public IP (no DNS-rebinding TOCTOU)
            // and each redirect hop is independently re-validated and re-pinned
            // (no public→internal pivot).
            $response = OutboundUrlGuard::fetch(
                'GET',
                $url,
                static fn (PendingRequest $request): PendingRequest => $request->timeout(15),
                5
            );

            // Check if HTTP response was unsuccessful
            if (! $response->successful()) {
                // Return empty array if fetch failed
                return [];
            }

            // Get response body as feed content string
            $feedContent = $response->body();

            // Parse the XML feed
            $xml = @\simplexml_load_string($feedContent);

            // Check if XML parsing failed
            if ($xml === false) {
                // Clear XML errors
                \libxml_clear_errors();

                // Return empty array if parsing failed
                return [];
            }

            // Determine feed type (RSS or Atom)
            $isAtom = property_exists($xml, 'entry') && $xml->entry !== null;

            // Get feed items based on feed type. No items loops zero times, and an RSS
            // document with no channel has none at all, so no separate empty check
            // is needed.
            $feedItems = ($isAtom ? $xml->entry : $xml->channel->item) ?? [];

            // Process feed items
            $itemCount = 0;

            // Whether descriptions are cut to the excerpt limit
            $truncatesDescriptions = $excerptLimit !== null && $excerptLimit > 0;

            // Loop through each feed item and extract data
            foreach ($feedItems as $item) {
                // Check if limit has been reached. `continue` would skip every later
                // item the same way, only more slowly.
                // @pest-mutate-ignore: BreakToContinue
                if ($itemCount >= $limit) {
                    // Break loop if limit reached
                    break;
                }

                // Initialize item data array
                $itemData = [];

                // Extract title
                $itemData['title'] = (string) ($item->title ?? '');

                // Extract permalink/URL from feed item
                $itemData['permalink'] = (string) ($isAtom
                    ? (($item->link['href'] ?? '')) // Atom feeds use <link> element with href attribute
                    : ($item->link ?? $item->guid ?? '')); // RSS feeds use <link> or <guid> element

                // Extract publication date from feed item
                $itemData['date'] = (string) ($isAtom
                    ? ($item->published ?? $item->updated ?? '') // Atom feeds use <published> or <updated> element
                    : ($item->pubDate ?? '')); // RSS feeds use <pubDate> element

                // ---
                // Extract author information from feed item
                // ---

                // Initialize authors array
                $authors = [];

                // Extract authors
                if ($isAtom) {
                    // ---
                    // Atom feeds use <author> element containing <name>
                    // ---

                    // Loop through all author elements from Atom feed item
                    foreach ($item->author as $author) {
                        // Extract author name from author element
                        $authorName = (string) ($author->name ?? '');

                        // Check if author name is not empty
                        if ($authorName !== '' && $authorName !== '0') {
                            // Add author to array
                            $authors[] = $authorName;
                        }
                    }
                } else { // Otherwise
                    // ---
                    // RSS feeds use <author> or <dc:creator>
                    // ---

                    // Get all XML namespaces from the feed item
                    $namespaces = $item->getNamespaces(true);

                    // Extract dc namespace for dc:creator element
                    $dcNamespace = $namespaces['dc'] ?? null;

                    // Check if dc namespace is available
                    if ($dcNamespace !== null) {
                        // Get children elements from dc namespace
                        $dc = $item->children($dcNamespace);
                        // Get all dc:creator elements (can be multiple)
                        // property_exists() is true only when the item has such a child
                        if (property_exists($dc, 'creator')) {
                            // Loop through all creator elements
                            foreach ($dc->creator as $creator) {
                                // Extract creator name
                                $creatorName = (string) $creator;

                                // Check if creator name is not empty
                                if ($creatorName !== '' && $creatorName !== '0') {
                                    // Add creator to array
                                    $authors[] = $creatorName;
                                }
                            }
                        }

                        // ---
                        // Fallback to author element if no creators found
                        // ---
                        // Check if no creators were found and author element exists
                        if (
                            $authors === []
                            && property_exists($item, 'author')
                        ) {
                            // Extract author name from author element
                            $authorName = (string) $item->author;

                            // Check if author name is not empty
                            if ($authorName !== '' && $authorName !== '0') {
                                // Add author to array
                                $authors[] = $authorName;
                            }
                        }
                    } elseif (property_exists($item, 'author')) {
                        // Otherwise
                        // Check if author element exists
                        // Extract author name from author element
                        $authorName = (string) $item->author;
                        // Check if author name is not empty
                        if ($authorName !== '' && $authorName !== '0') {
                            // Add author to array
                            $authors[] = $authorName;
                        }
                    }
                }

                // Store authors array
                $itemData['authors'] = $authors;

                // Format author string with intelligent comma handling
                $itemData['author'] = $this->formatAuthors($authors);

                // ---
                // Extract description/excerpt from feed item
                // ---

                // Extract description/excerpt
                if ($isAtom) {
                    // ---
                    // Atom feeds use <summary> or <content>
                    // ---

                    // Extract description from summary or content element
                    $itemData['description'] = (string) ($item->summary ?? $item->content ?? '');
                } else { // Otherwise
                    // ---
                    // RSS feeds use <description> or <content:encoded>
                    // ---

                    // Extract description from description element if available
                    $description = (string) ($item->description ?? '');

                    // Check if description element has a value
                    if ($description !== '' && $description !== '0') {
                        // Use description element value
                        $itemData['description'] = $description;
                    } else { // Otherwise
                        // ---
                        // Fallback to content:encoded if description is empty
                        // ---

                        // Get all XML namespaces from the feed item
                        $namespaces = $item->getNamespaces(true);

                        // Extract content namespace for <content:encoded> element
                        $contentNamespace = $namespaces['content'] ?? null;

                        // Check if content namespace is available
                        if ($contentNamespace !== null) {
                            // Get children elements from content namespace
                            $content = $item->children($contentNamespace);

                            // Extract description from content:encoded
                            $itemData['description'] = (string) ($content->encoded ?? '');
                        } else { // Otherwise
                            // Use empty string if neither description nor content:encoded available
                            $itemData['description'] = '';
                        }
                    }
                }

                // Strip HTML tags from description for excerpt
                $itemData['description'] = \strip_tags($itemData['description']);

                // Truncate description if excerpt limit is set
                if ($truncatesDescriptions) {
                    // Get description length
                    $descriptionLength = \mb_strlen($itemData['description']);

                    // Check if description exceeds limit
                    if ($descriptionLength > $excerptLimit) {
                        // Truncate description to limit
                        $itemData['description'] = \mb_substr(
                            $itemData['description'],
                            0,
                            $excerptLimit
                        ).'...';
                    }
                }

                // Add item to items array
                $items[] = $itemData;

                // Increment item count
                $itemCount++;
            }
        }

        // Catch any exceptions during feed fetching
        catch (Exception) {
            // Return empty array on exception
            return [];
        } finally {
            // Restore the libxml error mode this method found
            \libxml_use_internal_errors($libxmlPreviousState);
        }

        // Return processed items
        return $items;
    }

    /**
     * Formats an array of author names into a string with intelligent comma handling.
     *
     * Handles commas in author names (e.g., "John Doe, M.D.") by adjusting the
     * separator before "and" based on whether the preceding author name contains
     * a comma.
     *
     * @param  list<string>  $authors  Array of author names
     * @return string Formatted author string
     */
    private function formatAuthors(array $authors): string
    {
        // Check if authors array is empty
        if ($authors === []) {
            // Return empty string if no authors
            return '';
        }

        // Check if only one author
        if (\count($authors) === 1) {
            // Return single author name
            return $authors[0];
        }

        // ---
        // Two or more authors: use comma separation with intelligent 'and' placement.
        // For two, the penultimate author is the first, which gives "A and B" or
        // "A, and B" when A has a comma.
        // ---

        // Calculate index of penultimate (second-to-last) author
        $penultimateIndex = \count($authors) - 2;

        // Get penultimate (second-to-last) author name
        $penultimateAuthor = $authors[$penultimateIndex];

        // Check if penultimate author name contains a comma
        if (
            str_contains(
                $penultimateAuthor,
                ','
            )
        ) {
            // Get all authors except the last one
            $allButLast = \array_slice(
                $authors,
                0,
                -1
            );

            // Get the last author name
            $lastAuthor = end($authors);

            // ---
            // Return formatted string with comma-separated authors and ', and ' before last
            // author
            // ---
            return implode(
                ', ',
                $allButLast
            ).(', and '.$lastAuthor);
        }

        // Get all authors except the last one
        $allButLast = \array_slice(
            $authors,
            0,
            -1
        );

        // Get the last author name
        $lastAuthor = end($authors);

        // ---
        // Return formatted string with comma-separated authors and ' and ' before last
        // author
        // ---
        return implode(
            ', ',
            $allButLast
        ).(' and '.$lastAuthor);
    }
}
