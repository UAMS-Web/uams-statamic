<?php

declare(strict_types=1);

namespace App\Services\GoogleBusinessProfile\Categories;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Reads the category list from the Google Business Information API (UAMS-Web/uams-statamic-archive#2617).
 *
 * `categories.list` for `regionCode=US`, `languageCode=en` and `view=BASIC`, a hundred per
 * page, following `nextPageToken` until a page carries none. On 2026-10-07 that was 4,049
 * categories over 41 pages (UAMS-Web/uams-statamic-archive#2616). An access token is minted from the stored refresh token
 * on each read and kept only for that read.
 *
 * Nothing fetched is kept here: the Business Profile APIs storage policy quoted in UAMS-Web/uams-statamic-archive#2091
 * rules that out, so the list lives only as long as the diff that reads it.
 *
 * Any failure to read the whole list (missing credentials, a refused token, a failed page,
 * a page that is not a JSON object, holds no list or holds an empty one, a next-page token
 * that is not a string, or a page token that never ends) throws
 * {@see CategorySourceUnavailable}, and so does a list read in full whose every record is
 * unreadable, so a cut-off or empty read is never compared and reported as terms removed. A complete list that Google
 * itself shortened is read as given. A single record that cannot be read is reported in
 * {@see CategoryList::$malformed}, numbered by its position across all pages.
 */
final readonly class ApiCategorySource implements CategorySource
{
    /**
     * Google's OAuth token endpoint, where a refresh token is exchanged for an access token.
     */
    public const string TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * The Business Information API's `categories.list` endpoint.
     */
    public const string CATEGORIES_URL = 'https://mybusinessbusinessinformation.googleapis.com/v1/categories';

    /**
     * Categories per page, the most `categories.list` returns.
     */
    public const int PAGE_SIZE = 100;

    /**
     * Pages read before giving up on a page token that never ends; 41 were needed on 2026-10-07.
     */
    public const int MAX_PAGES = 200;

    /**
     * Seconds to wait for each request.
     */
    private const int TIMEOUT_SECONDS = 30;

    /**
     * The prefix the API writes before every GCID.
     */
    private const string GCID_PREFIX = 'gcid:';

    /**
     * The resource-name prefix a v1 category name may carry before `gcid:`. Both shapes are
     * read, because the one `categories.list` returns has not been checked against a live
     * response from this code (UAMS-Web/uams-statamic-archive#2617).
     */
    private const string RESOURCE_PREFIX = 'categories/';

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $refreshToken,
    ) {}

    /**
     * Build the source from `services.google_business_profile`.
     */
    public static function fromConfig(): self
    {
        return new self(
            self::configString('client_id'),
            self::configString('client_secret'),
            self::configString('refresh_token'),
        );
    }

    #[\Override]
    public function label(): string
    {
        return 'Google Business Information API (US, en)';
    }

    #[\Override]
    public function read(): CategoryList
    {
        if ($this->clientId === '' || $this->clientSecret === '' || $this->refreshToken === '') {
            throw new CategorySourceUnavailable('The Google Business Profile API credentials are not configured.');
        }

        $accessToken = $this->accessToken();

        $categories = [];
        $malformed = [];
        $unreadable = [];
        $record = 0;
        $pageToken = null;
        $page = 0;

        do {
            $page++;

            if ($page > self::MAX_PAGES) {
                throw new CategorySourceUnavailable(\sprintf('categories.list was still returning a next page after %d pages.', self::MAX_PAGES));
            }

            $body = $this->page($accessToken, $pageToken, $page);

            foreach ($body['categories'] as $category) {
                $record++;
                $problem = $this->parse($category, $categories, $unreadable);

                if (\is_string($problem)) {
                    // A record decoded from JSON always re-encodes, so the cast only
                    // narrows json_encode()'s false for the type checker
                    // @pest-mutate-ignore: RemoveStringCast
                    $malformed[] = ['line' => $record, 'content' => (string) json_encode($category), 'reason' => $problem];
                }
            }

            $pageToken = $body['nextPageToken'];
        } while ($pageToken !== null);

        // An empty list is not a plausible reading of Google's categories, and comparing
        // one would report every term removed
        if ($categories === []) {
            throw new CategorySourceUnavailable('categories.list returned no categories.');
        }

        return new CategoryList($categories, $malformed, array_values($unreadable));
    }

    /**
     * Exchange the refresh token for an access token.
     */
    private function accessToken(): string
    {
        try {
            $response = Http::asForm()->timeout(self::TIMEOUT_SECONDS)->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
            ]);
        } catch (ConnectionException $connectionException) {
            throw new CategorySourceUnavailable('Could not reach Google for an access token: '.$connectionException->getMessage(), $connectionException->getCode(), previous: $connectionException);
        }

        $token = $response->json('access_token');

        if ($response->failed() || ! \is_string($token) || $token === '') {
            // Google's OAuth error code (such as invalid_grant) names the cause without
            // echoing any credential
            throw new CategorySourceUnavailable(\sprintf(
                'Google refused an access token (HTTP %d%s).',
                $response->status(),
                self::errorCode($response->json('error')),
            ));
        }

        return $token;
    }

    /**
     * Fetch one page of categories.
     *
     * @return array{categories: list<mixed>, nextPageToken: string|null}
     */
    private function page(string $accessToken, ?string $pageToken, int $page): array
    {
        $query = [
            'regionCode' => 'US',
            'languageCode' => 'en',
            'view' => 'BASIC',
            'pageSize' => self::PAGE_SIZE,
        ];

        if ($pageToken !== null) {
            $query['pageToken'] = $pageToken;
        }

        try {
            $response = Http::withToken($accessToken)->acceptJson()->timeout(self::TIMEOUT_SECONDS)->get(self::CATEGORIES_URL, $query);
        } catch (ConnectionException $connectionException) {
            throw new CategorySourceUnavailable(\sprintf('Could not reach categories.list for page %d: %s', $page, $connectionException->getMessage()), $connectionException->getCode(), previous: $connectionException);
        }

        if ($response->failed()) {
            throw new CategorySourceUnavailable(\sprintf(
                'categories.list failed on page %d (HTTP %d%s).',
                $page,
                $response->status(),
                self::errorCode($response->json('error.status')),
            ));
        }

        // A 200 whose body is not a JSON object (a proxy's HTML page, an empty or cut-off
        // body) would otherwise read as a last page with no categories, ending the list
        // early and reporting every later category's term as removed
        $body = $response->json();

        if (! \is_array($body)) {
            throw new CategorySourceUnavailable(\sprintf('categories.list page %d was not a JSON object.', $page));
        }

        $categories = $body['categories'] ?? null;
        $next = $body['nextPageToken'] ?? null;

        if (! \is_array($categories) || ! array_is_list($categories)) {
            throw new CategorySourceUnavailable(\sprintf('categories.list page %d did not hold a list of categories.', $page));
        }

        // A page with no categories is never a page of Google's list: on a later page
        // it would end the read as though the list were complete and report every
        // category after it removed. Failing here can only cost a check, never invent
        // a removal (UAMS-Web/uams-statamic-archive#2930)
        if ($categories === []) {
            throw new CategorySourceUnavailable(\sprintf('categories.list page %d held no categories.', $page));
        }

        // Only an absent, null or empty token ends the list. Any other value that is not a
        // string would otherwise collapse to that same end, and the categories on the pages
        // never fetched would read as removed (UAMS-Web/uams-statamic-archive#2930)
        if ($next !== null && ! \is_string($next)) {
            throw new CategorySourceUnavailable(\sprintf('categories.list page %d had a nextPageToken that is not a string.', $page));
        }

        return [
            'categories' => $categories,
            'nextPageToken' => $next === null || $next === '' ? null : $next,
        ];
    }

    /**
     * Add one record's category, or say why it cannot be added.
     *
     * A record rejected after its GCID was read adds that GCID to `$unreadable`: Google
     * listed the category, so its term must not be reported removed (UAMS-Web/uams-statamic-archive#2944).
     *
     * @param  array<string, string>  $categories  The categories read so far.
     * @param  array<string, string>  $unreadable  GCIDs read from records that were rejected, keyed by themselves.
     * @return string|null Why the record was rejected, or null when it was read.
     */
    private function parse(mixed $category, array &$categories, array &$unreadable): ?string
    {
        $name = \is_array($category) ? ($category['name'] ?? null) : null;
        $displayName = \is_array($category) ? ($category['displayName'] ?? null) : null;

        if (\is_string($name) && str_starts_with($name, self::RESOURCE_PREFIX)) {
            $name = substr($name, \strlen(self::RESOURCE_PREFIX));
        }

        if (! \is_string($name) || ! str_starts_with($name, self::GCID_PREFIX)) {
            return 'the name is not a gcid: identifier';
        }

        $gcid = substr($name, \strlen(self::GCID_PREFIX));

        if (preg_match(CategoryList::GCID_PATTERN, $gcid) !== 1) {
            return 'the GCID is not lowercase letters, digits and underscores';
        }

        if (! \is_string($displayName) || trim($displayName) === '') {
            $unreadable[$gcid] = $gcid;

            return 'no display name';
        }

        if (\array_key_exists($gcid, $categories)) {
            $unreadable[$gcid] = $gcid;

            return 'the GCID appears in an earlier record';
        }

        $categories[$gcid] = trim($displayName);

        return null;
    }

    /**
     * An API error code to append to a message, or nothing when there is none.
     */
    private static function errorCode(mixed $code): string
    {
        return \is_string($code) && $code !== '' ? ', '.$code : '';
    }

    /**
     * A `services.google_business_profile` value as a string, empty when unset.
     */
    private static function configString(string $key): string
    {
        $value = config('services.google_business_profile.'.$key);

        return \is_string($value) ? $value : '';
    }
}
