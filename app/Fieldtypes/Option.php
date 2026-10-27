<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * Statamic fieldtype defining a single labelled option with an associated numeric price,
 * exposed as render-ready rows (stable key, label, raw and formatted price).
 */
class Option extends Fieldtype
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
     * The Vue component name used in the Statamic control panel.
     *
     * @var string
     */
    protected $component = 'option';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'shopping-bag-tag';
    }

    /**
     * Defines the configuration fields available for this fieldtype in the blueprint.
     *
     * Mirrors the Gravity Forms Option field, which presents priced choices for a
     * product created by a Product field. Each selection adds to the form Total.
     * The input type matches the Gravity Forms option_field_type setting (drop
     * down, radio buttons, or checkboxes).
     *
     * @return array<int, array<string, mixed>> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        return [
            [
                // Option configuration section for blueprint field options
                'display' => __('Option Settings'),
                'fields' => [
                    'input_type' => [
                        'display' => __('Field Type'),
                        'type' => 'radio',
                        'instructions' => __('How the option choices are presented to the user.'),
                        'options' => [
                            'select' => __('Drop Down'),
                            'radio' => __('Radio Buttons'),
                            'checkbox' => __('Checkboxes'),
                        ],
                        'default' => 'select',
                        'validate' => ['required'],
                    ],
                    'product_field' => [
                        'display' => __('Product Field'),
                        'type' => 'text',
                        'instructions' => __('The handle of the Product field whose price these options modify.'),
                        'validate' => ['required'],
                    ],
                    'options' => [
                        'display' => __('Option Choices'),
                        'type' => 'grid',
                        'mode' => 'stacked',
                        'add_row' => 'Add Option',
                        'fields' => [
                            [
                                'handle' => 'key',
                                'field' => [
                                    'type' => 'text',
                                    'display' => 'Key',
                                    'validate' => ['required'],
                                ],
                            ],
                            [
                                'handle' => 'label',
                                'field' => [
                                    'type' => 'text',
                                    'display' => 'Label (Optional)',
                                ],
                            ],
                            [
                                'handle' => 'price',
                                'field' => [
                                    'type' => 'currency',
                                    'display' => 'Price',
                                    'instructions' => 'Amount added to (or subtracted from) the product price and form Total when this option is selected.',
                                    'validate' => ['required'],
                                ],
                            ],
                        ],
                        'validate' => ['required'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Preload merged config for the Vue control-panel component.
     *
     * Surfaces the resolved field config (input type and priced option rows) on
     * `meta.config` so `OptionFieldtype.vue` can render the drop-down / radio /
     * checkbox chooser and compute the selected subtotal without a server
     * round-trip.
     *
     * @return array<string, mixed> Preload data exposed to the Vue component as `meta`
     */
    #[\Override]
    public function preload(): array
    {
        return [
            'config' => $this->config(),
        ];
    }

    /**
     * Extra data merged into the public-form rendering scope.
     *
     * Pre-formats each option's price as a display string so `option.antlers.html`
     * (and the `option-pricing-engine.js` it drives) never has to format currency
     * in Antlers. Raw numeric prices ride alongside the formatted strings for the
     * client-side line-total math.
     *
     * @return array<string, mixed> Renderable field data
     */
    #[\Override]
    public function extraRenderableFieldData(): array
    {
        // Map each option choice to a render-ready row: stable key, human label
        // (falling back to the key), raw numeric price for math, and a
        // pre-formatted price string for display.
        /** @var list<array<string, mixed>> $rawOptions */
        $rawOptions = $this->config('options') ?? [];

        $options = collect($rawOptions)
            ->map(function (array $option): array {
                $price = $option['price'] ?? 0;

                return [
                    'key' => $option['key'] ?? '',
                    'label' => $option['label'] ?? ($option['key'] ?? ''),
                    'price' => \is_numeric($price) ? (float) $price : 0.0,
                    'formatted_price' => $this->formatPrice($price),
                ];
            })
            ->all();

        return [
            'option_choices' => $options,
        ];
    }

    /**
     * Normalize a submitted option selection into its stored shape.
     *
     * Recomputes the authoritative `line_total` server-side from the field config
     * and the submitted option key(s) — the public form's hidden `line_total`
     * input is a client-side convenience for the Total pipeline (issue UAMS-Web/uams-statamic-archive#488),
     * never a trust boundary. Checkbox fields accept multiple keys; drop-down and
     * radio fields accept a single key. Returns `null` when nothing was selected.
     *
     * Note: Gravity Forms multiplies an option's price by the parent product's
     * quantity. The four affected production forms order a single unit, so the
     * selected option price is the contribution as-is; quantity coupling to the
     * `product_field` is deferred until the Product/Total pipeline (UAMS-Web/uams-statamic-archive#488) needs it.
     *
     * @param  mixed  $data  Raw value from the publish/public form
     * @return array<string, mixed>|null Stored selection, or null when empty
     */
    #[\Override]
    public function process($data): ?array
    {
        // An option value is always an associative array (a single key, or a list
        // of checkbox keys); anything else means nothing was submitted.
        if (! \is_array($data)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        // Checkbox options allow several selections; drop-down and radio options
        // resolve a single key.
        if ($this->config('input_type', 'select') === 'checkbox') {
            return $this->processMultiple($data);
        }

        return $this->processSingle($data);
    }

    /**
     * Build the stored shape for a single-selection (drop-down / radio) option.
     *
     * @param  array<string, mixed>  $data  Submitted key payload
     * @return array<string, mixed>|null Stored selection, or null when empty
     */
    private function processSingle(array $data): ?array
    {
        // No selection means nothing was chosen.
        $key = $data['key'] ?? null;

        if ($key === null || $key === '') {
            return null;
        }

        $key = \is_scalar($key) ? (string) $key : '';

        // An unknown key (tampered submission) resolves to nothing.
        $option = $this->findOption($key);

        if ($option === null) {
            return null;
        }

        // The selected option's own price is its Total contribution.
        $rawPrice = $option['price'] ?? 0;
        $price = \is_numeric($rawPrice) ? (float) $rawPrice : 0.0;

        $inputType = $this->config('input_type', 'select');

        return [
            'input_type' => \is_scalar($inputType) ? (string) $inputType : '',
            'key' => $key,
            'label' => $option['label'] ?? $option['key'] ?? $key,
            'price' => $price,
            'line_total' => round($price, 2),
        ];
    }

    /**
     * Build the stored shape for a multiple-selection (checkbox) option.
     *
     * @param  array<string, mixed>  $data  Submitted keys payload
     * @return array<string, mixed>|null Stored selection, or null when empty
     */
    private function processMultiple(array $data): ?array
    {
        // Checkbox submissions arrive as a list of keys under `keys`.
        $keys = $data['keys'] ?? null;

        if (! \is_array($keys) || $keys === []) {
            return null;
        }

        // Resolve each submitted key to a configured option, skipping unknown
        // (tampered) keys, and sum the selected prices into the line total.
        $selections = [];
        $lineTotal = 0.0;

        foreach ($keys as $rawKey) {
            $key = \is_scalar($rawKey) ? (string) $rawKey : '';
            $option = $this->findOption($key);

            if ($option === null) {
                continue;
            }

            $rawPrice = $option['price'] ?? 0;
            $price = \is_numeric($rawPrice) ? (float) $rawPrice : 0.0;
            $lineTotal += $price;

            $selections[] = [
                'key' => $key,
                'label' => $option['label'] ?? $option['key'] ?? $key,
                'price' => $price,
            ];
        }

        // Every submitted key was unknown — nothing valid was selected.
        if ($selections === []) {
            return null;
        }

        return [
            'input_type' => 'checkbox',
            'keys' => array_column($selections, 'key'),
            'selections' => $selections,
            'line_total' => round($lineTotal, 2),
        ];
    }

    /**
     * Resolve a configured option row by its key.
     *
     * @param  string  $key  The option key to look up
     * @return array<string, mixed>|null The matching option row, or null
     */
    private function findOption(string $key): ?array
    {
        // Scan the configured choices for a row whose key matches.
        $options = $this->config('options') ?? [];
        if (! \is_array($options)) {
            return null;
        }

        foreach ($options as $option) {
            if (\is_array($option) && ($option['key'] ?? null) === $key) {
                /** @var array<string, mixed> $option */
                return $option;
            }
        }

        return null;
    }

    /**
     * Format a numeric price as a US-dollar display string.
     *
     * Keeps the sign outside the currency symbol so negative options (credits /
     * discounts, which Gravity Forms permits) render as `-$25.00` rather than
     * `$-25.00`.
     *
     * @param  mixed  $value  The raw price value
     * @return string The formatted price
     */
    private function formatPrice(mixed $value): string
    {
        // Coerce to a float and split the sign from the magnitude.
        $amount = \is_numeric($value) ? (float) $value : 0.0;
        $sign = $amount < 0 ? '-' : '';

        return $sign.'$'.number_format(abs($amount), 2);
    }
}
