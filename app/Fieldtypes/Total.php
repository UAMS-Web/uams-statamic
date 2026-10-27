<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * Statamic analog of the Gravity Forms `total` field (UAMS-Web/uams-statamic-archive#488).
 *
 * A read-only running order total for public forms. Every `product` (UAMS-Web/uams-statamic-archive#300) and
 * `option` (UAMS-Web/uams-statamic-archive#311) field writes its line subtotal to a hidden
 * `[data-product-line-total-input]` and dispatches a bubbling
 * `product:price-changed` on the form; `resources/js/total-pricing-engine.js`
 * listens for that event and sums those inputs into this field's display and
 * hidden input, so the total round-trips on submission. Display-only by design:
 * the per-line totals are recomputed authoritatively by `Product`/`Option`
 * `process()`, so the stored total here is advisory.
 */
class Total extends Fieldtype
{
    /**
     * The categories this fieldtype belongs to in the Statamic fieldtype selector.
     *
     * @var array<string> Categories this fieldtype belongs to in the Statamic fieldtype selector.
     */
    protected $categories = [];

    /**
     * Indicates whether the fieldtype is selectable in collection blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in collection blueprints
     */
    protected $selectable = true;

    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = true;

    /**
     * The name of the Vue component to use for this fieldtype (registered as
     * `total-fieldtype` in resources/js/cp.js).
     *
     * @var string The name of the Vue component to use for this fieldtype
     */
    protected $component = 'total';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'money-bag-dollar';
    }

    /**
     * Defines the configuration fields available for this fieldtype in the blueprint.
     *
     * @return array<string, mixed> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        return [
            'prefix' => [
                'display' => __('Amount Prefix'),
                'instructions' => __('Text shown before the computed total (e.g. "Order Total").'),
                'type' => 'text',
                'default' => __('Total'),
            ],
        ];
    }

    /**
     * Preload data for the CP Vue component.
     *
     * @return array<string, mixed> Preload data
     */
    #[\Override]
    public function preload(): array
    {
        $prefix = $this->config('prefix', __('Total'));

        return [
            'prefix' => \is_scalar($prefix) ? (string) $prefix : '',
        ];
    }

    /**
     * Front-end form-render data (exposes the prefix to the Antlers template).
     *
     * @return array<string, mixed> Front-end render data
     */
    #[\Override]
    public function extraRenderableFieldData(): array
    {
        $prefix = $this->config('prefix', __('Total'));

        return [
            'prefix' => \is_scalar($prefix) ? (string) $prefix : '',
        ];
    }

    /**
     * Normalize the submitted total to a plain `0.00` currency string.
     *
     * The value is client-computed and advisory — the authoritative per-line
     * totals are recomputed server-side by {@see Product::process()} /
     * {@see Option::process()}. A non-numeric or empty submission stores null.
     *
     * @param  mixed  $data  The submitted total.
     */
    #[\Override]
    public function process($data): ?string
    {
        if (! \is_numeric($data)) {
            return null;
        }

        return number_format((float) $data, 2, '.', '');
    }
}
