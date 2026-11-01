<?php

declare(strict_types=1);

namespace App\Tags;

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
     * Resolve the `requested` scope, falling back to the current site's default.
     *
     * Anything other than a scope this site offers (an unknown string, or an array
     * from `?scope[]=x`) falls back, so a hand-edited URL cannot fail.
     */
    public function active(): string
    {
        if ($this->onRootSite()) {
            return self::ALL_SITES;
        }

        return $this->parameters()->get('requested') === self::ALL_SITES
            ? self::ALL_SITES
            : self::THIS_SITE;
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
     * The root site's name, as configured.
     */
    public function rootName(): string
    {
        $name = $this->rootSite()->name();

        return is_string($name) ? $name : '';
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
        return $scope === self::ALL_SITES && ! $this->onRootSite() ? self::ALL_SITES : '';
    }

    /**
     * Whether the reader is on the root site.
     */
    private function onRootSite(): bool
    {
        return $this->currentSite()->handle() === $this->rootSite()->handle();
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
    private function rootSite(): SiteObject
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
