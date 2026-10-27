<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Listeners\AppendPublishDependencyBanner;
use App\Services\PublishDependencies\PublishDependencyResolver;
use Statamic\Entries\Entry;
use Statamic\Fields\Fieldtype;

/**
 * Read-only Control-Panel banner — the CP-signal half of the opt-in
 * publish-dependency mechanism (UAMS-Web/uams-statamic-archive#1593), mirroring {@see OrphanBanner}.
 *
 * When the entry being edited is suppressed because its declared
 * `publish_depends_on` source(s) are down (so its live page 302s away and it is
 * excluded from listings), this surfaces a warning naming the down source(s) and
 * the redirect destination; otherwise it renders nothing. The suppressed state is
 * computed server-side from the entry via {@see PublishDependencyResolver} — there
 * is no request-controlled input.
 *
 * The field is injected onto opted-in blueprints at resolve time by
 * {@see AppendPublishDependencyBanner}, so it is not user-pickable.
 */
class PublishDependencyBanner extends Fieldtype
{
    /**
     * The categories this fieldtype belongs to in the Statamic fieldtype selector.
     *
     * @var array<string>
     */
    protected $categories = [];

    /**
     * Not user-pickable — injected onto opted-in blueprints.
     *
     * @var bool
     */
    protected $selectable = false;

    /**
     * Not a form field.
     *
     * @var bool
     */
    protected $selectableInForms = false;

    /**
     * The Vue component (registered as `publish_dependency_banner-fieldtype` in cp.js).
     *
     * @var string
     */
    protected $component = 'publish_dependency_banner';

    /**
     * The icon for this fieldtype.
     */
    #[\Override]
    public function icon(): string
    {
        return 'warning';
    }

    /**
     * This fieldtype has no configuration.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        return [];
    }

    /**
     * Compute the suppressed state of the entry being edited for the Vue banner.
     *
     * @return array{suppressed: bool, redirect_url: string|null, source_titles: list<string>, require_all: bool}
     */
    #[\Override]
    public function preload(): array
    {
        $empty = [
            'suppressed' => false,
            'redirect_url' => null,
            'source_titles' => [],
            'require_all' => true,
        ];

        $parent = $this->field()?->parent();
        if (! $parent instanceof Entry) {
            return $empty;
        }

        $result = app(PublishDependencyResolver::class)->resolve($parent);
        if ($result === null) {
            return $empty;
        }

        return [
            'suppressed' => true,
            'redirect_url' => $result->redirectUrl,
            'source_titles' => $result->downSourceTitles,
            'require_all' => $result->requireAll,
        ];
    }
}
