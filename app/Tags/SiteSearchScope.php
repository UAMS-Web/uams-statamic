<?php

declare(strict_types=1);

namespace App\Tags;

use App\Http\Middleware\SecurityHeaders;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Sites\Site as SiteObject;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * `{{ site_search_scope:* }}`: how widely `/search` looks (UAMS-Web/uams-statamic#413).
 *
 * A sub-site offers "This site" (the default) and "All of <root site's name>"; the
 * root site offers only the second, so its default searches every site. The scope
 * travels in the URL as `?scope=all`, and only where it differs from the default,
 * so the root site's URLs read as they always have. Values are fixed here and never
 * echo the request, so they are safe in an href.
 *
 * With a Google search engine ID configured (`services.google_search.engine_id`),
 * every site also offers "All UAMS websites", where Google's results replace the
 * site's own (UAMS-Web/uams-statamic#412).
 */
class SiteSearchScope extends Tags
{
    /**
     * The scope that searches only the current site.
     */
    public const string THIS_SITE = 'site';

    /**
     * The scope that searches every site.
     */
    public const string ALL_SITES = 'all';

    /**
     * The scope that shows Google's results for every UAMS website.
     */
    public const string GOOGLE = 'google';

    /**
     * Resolve the `requested` scope, falling back to the current site's default.
     *
     * Anything other than a scope this site offers (an unknown string, or an array
     * from `?scope[]=x`) falls back, so a hand-edited URL cannot fail.
     */
    public function active(): string
    {
        return self::resolve($this->currentSite(), $this->parameters()->get('requested'));
    }

    /**
     * Resolve a requested scope on a site, so the page and the suggestions it asks
     * for (UAMS-Web/uams-statamic#467) cannot disagree about what a site offers.
     *
     * @param  SiteObject  $site  The site the search page belongs to
     * @param  mixed  $requested  The raw `scope` value, of any type
     */
    public static function resolve(SiteObject $site, mixed $requested): string
    {
        if ($requested === self::GOOGLE && self::configuredGoogleEngine() !== '') {
            return self::GOOGLE;
        }

        if ($site->handle() === self::rootSite()->handle()) {
            return self::ALL_SITES;
        }

        return $requested === self::ALL_SITES ? self::ALL_SITES : self::THIS_SITE;
    }

    /**
     * The `scope` query value for the `active` scope, empty when it is the default.
     */
    public function param(): string
    {
        return $this->paramFor($this->parameters()->get('active'));
    }

    /**
     * The current site's search page path, such as `/search` or `/health/search`.
     */
    public function path(): string
    {
        $url = $this->currentSite()->url();

        return rtrim(URL::makeRelative(is_string($url) ? $url : '/'), '/').'/search';
    }

    /**
     * The current site's handle, which the type-ahead sends with each request.
     */
    public function site(): string
    {
        $handle = $this->currentSite()->handle();

        return is_string($handle) ? $handle : '';
    }

    /**
     * The root site's name, as configured.
     */
    public function rootName(): string
    {
        $name = self::rootSite()->name();

        return is_string($name) ? $name : '';
    }

    /**
     * The Google search engine ID for this page's results, and permission for this
     * response alone to load Google's script.
     *
     * Rendering is the moment the script is needed, so no other page gets the wider
     * content security policy.
     */
    public function googleEngine(): string
    {
        SecurityHeaders::allowOnThisResponse(SecurityHeaders::GOOGLE_SEARCH);

        return self::configuredGoogleEngine();
    }

    /**
     * The scopes the current site offers, each marked against the `active` scope.
     *
     * @return list<array{value: string, label: string, param: string, is_active: bool}>
     */
    public function options(): array
    {
        $active = $this->parameters()->get('active');

        $scopes = [self::ALL_SITES => 'All of '.$this->rootName()];

        if (! $this->onRootSite()) {
            $scopes = [self::THIS_SITE => 'This site'] + $scopes;
        }

        if (self::configuredGoogleEngine() !== '') {
            $scopes[self::GOOGLE] = 'All UAMS websites';
        }

        $options = [];
        foreach ($scopes as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
                'param' => $this->paramFor($value),
                'is_active' => $value === $active,
            ];
        }

        return $options;
    }

    /**
     * The `scope` query value that selects a scope on the current site.
     */
    private function paramFor(mixed $scope): string
    {
        if ($scope === self::GOOGLE) {
            return self::GOOGLE;
        }

        return $scope === self::ALL_SITES && ! $this->onRootSite() ? self::ALL_SITES : '';
    }

    /**
     * The configured Google search engine ID, or an empty string for none.
     */
    private static function configuredGoogleEngine(): string
    {
        $engine = config('services.google_search.engine_id');

        return is_string($engine) ? trim($engine) : '';
    }

    /**
     * Whether the reader is on the root site.
     */
    private function onRootSite(): bool
    {
        return $this->currentSite()->handle() === self::rootSite()->handle();
    }

    /**
     * The site the reader is on.
     */
    private function currentSite(): SiteObject
    {
        /** @var SiteObject $site */
        $site = Site::current();

        return $site;
    }

    /**
     * The root site.
     */
    private static function rootSite(): SiteObject
    {
        /** @var SiteObject $site */
        $site = Site::default();

        return $site;
    }

    /**
     * The tag's parameters.
     */
    private function parameters(): Parameters
    {
        /** @var Parameters $parameters */
        $parameters = $this->params;

        return $parameters;
    }
}
