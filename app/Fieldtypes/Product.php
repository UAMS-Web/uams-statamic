<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * Statamic fieldtype for selecting a product — a single base-priced item or a set of
 * priced choices — exposed as render-ready rows for display and math.
 */
class Product extends Fieldtype
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
    protected $component = 'product';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'shopping-store';
    }

    /**
     * Defines the configuration fields available for this fieldtype in the blueprint.
     *
     * @return list<array<string, mixed>> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        return [
            [
                // Product configuration section for blueprint field options
                'display' => __('Product Options'),
                'fields' => [
                    'selection_type' => [
                        'display' => __('Selection Type'),
                        'type' => 'radio',
                        'instructions' => __('Select the type of field from the available form fields.'),
                        'options' => [
                            'single' => __('Single Product'),
                            'multiple' => __('Multiple Product Choices'),
                        ],
                        'default' => 'single',
                        'validate' => ['required'],
                    ],
                    'price' => [
                        'display' => __('Base Price'),
                        'type' => 'currency',
                        'instructions' => 'Enter the base price for this product.',
                        'if' => [
                            'selection_type' => 'single',
                        ],
                        'validate' => ['required_if:selection_type,single', 'exclude_unless:selection_type,single'],
                    ],
                    'disable_quantity' => [
                        'display' => __('Disable Quantity Field'),
                        'type' => 'toggle',
                        'if' => [
                            'selection_type' => 'single',
                        ],
                        'default' => false,
                        'validate' => ['required_if:selection_type,single', 'exclude_unless:selection_type,single'],
                    ],
                    'options' => [
                        'display' => __('Product Choices'),
                        'type' => 'grid',
                        'mode' => 'stacked',
                        'add_row' => 'Add Product',
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
                                    'display' => 'Base Price',
                                    'instructions' => 'Enter the base price for this product.',
                                    'validate' => ['required'],
                                ],
                            ],
                        ],
                        'if' => [
                            'selection_type' => 'multiple',
                        ],
                        'validate' => ['required_if:selection_type,multiple', 'exclude_unless:selection_type,multiple'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Preload merged config for the Vue control-panel component.
     *
     * Surfaces the resolved field config (selection type, base price, quantity
     * toggle, and option rows) on `meta.config` so `ProductFieldtype.vue` can
     * render the single / multiple chooser and compute the line subtotal
     * without a server round-trip.
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
     * Pre-formats the single base price and each multiple-choice option's
     * price as a display string so `product.antlers.html` (and the
     * `product-pricing-engine.js` it drives) never has to format currency in
     * Antlers. Raw numeric prices ride alongside the formatted strings for the
     * client-side line-total math.
     *
     * @return array<string, mixed> Renderable field data
     */
    #[\Override]
    public function extraRenderableFieldData(): array
    {
        // Pre-format the single-product base price for display.
        $formattedPrice = $this->formatPrice($this->config('price', 0));

        // Map each multiple-choice option to a render-ready row: stable key,
        // human label (falling back to the key), raw numeric price for math,
        // and a pre-formatted price string for display.
        /** @var list<array<string, mixed>> $rawOptions */
        $rawOptions = $this->config('options') ?? [];

        $options = collect($rawOptions)
            ->map(function (array $option): array {
                $price = $option['price'] ?? 0;

                return [
                    'key' => $option['key'] ?? '',
                    'label' => $option['label'] ?? ($option['key'] ?? ''),
                    'price' => is_numeric($price) ? (float) $price : 0.0,
                    'formatted_price' => $this->formatPrice($price),
                ];
            })
            ->all();

        return [
            'formatted_price' => $formattedPrice,
            'product_options' => $options,
        ];
    }

    /**
     * Normalize a submitted product selection into its stored shape.
     *
     * Recomputes the authoritative `line_total` server-side from the field
     * config and the submitted quantity / option key — the public form's
     * hidden `line_total` input is a client-side convenience for the Total
     * pipeline (issue UAMS-Web/uams-statamic-archive#488), never a trust boundary. Returns `null` when
     * nothing was ordered (no quantity, or no option selected) so empty
     * products are not persisted.
     *
     * @param  mixed  $data  Raw value from the publish/public form
     * @return array<string, mixed>|null Stored selection, or null when empty
     */
    #[\Override]
    public function process($data): ?array
    {
        // A product value is always an associative array (quantity + optional
        // key); anything else means nothing was submitted.
        if (! \is_array($data)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        // Multiple-choice products store the selected option's key, label, and
        // its own price; single products use the field's base price.
        if ($this->config('selection_type', 'single') === 'multiple') {
            return $this->processMultiple($data);
        }

        return $this->processSingle($data);
    }

    /**
     * Build the stored shape for a single-product field.
     *
     * @param  array<string, mixed>  $data  Submitted quantity payload
     * @return array<string, mixed>|null Stored selection, or null when empty
     */
    private function processSingle(array $data): ?array
    {
        // A disabled quantity field is a fixed order of one; otherwise read the
        // submitted quantity and treat a missing / zero / negative count as
        // "not ordered".
        $disableQuantity = (bool) $this->config('disable_quantity', false);
        $submittedQuantity = $data['quantity'] ?? 0;
        $quantity = $disableQuantity ? 1 : max(0, is_numeric($submittedQuantity) ? (int) $submittedQuantity : 0);

        if ($quantity === 0) {
            return null;
        }

        // Snapshot the configured base price and compute the line subtotal.
        $configuredPrice = $this->config('price', 0);
        $price = is_numeric($configuredPrice) ? (float) $configuredPrice : 0.0;

        return [
            'selection_type' => 'single',
            'key' => $this->field()?->handle(),
            'label' => $this->field()?->display(),
            'quantity' => $quantity,
            'price' => $price,
            'line_total' => round($price * $quantity, 2),
        ];
    }

    /**
     * Build the stored shape for a multiple-choice product field.
     *
     * @param  array<string, mixed>  $data  Submitted key + quantity payload
     * @return array<string, mixed>|null Stored selection, or null when empty
     */
    private function processMultiple(array $data): ?array
    {
        // No selection means nothing was ordered.
        $key = $data['key'] ?? null;

        if ($key === null || $key === '') {
            return null;
        }

        // An unknown key (tampered submission) resolves to nothing.
        $key = is_scalar($key) ? (string) $key : '';
        $option = $this->findOption($key);

        if ($option === null) {
            return null;
        }

        // Default a missing quantity to one so picking an option without
        // touching the quantity field still orders a single unit.
        $submittedQuantity = $data['quantity'] ?? 1;
        $quantity = max(0, is_numeric($submittedQuantity) ? (int) $submittedQuantity : 0);

        if ($quantity === 0) {
            return null;
        }

        // Use the selected option's own price for the line subtotal.
        $optionPrice = $option['price'] ?? 0;
        $price = is_numeric($optionPrice) ? (float) $optionPrice : 0.0;

        return [
            'selection_type' => 'multiple',
            'key' => $key,
            'label' => $option['label'] ?? $option['key'] ?? $key,
            'quantity' => $quantity,
            'price' => $price,
            'line_total' => round($price * $quantity, 2),
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
        /** @var iterable<mixed> $configuredOptions */
        $configuredOptions = $this->config('options') ?? [];

        foreach ($configuredOptions as $option) {
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
     * Keeps the sign outside the currency symbol so negative line items
     * (credits / discounts, which Gravity Forms permits) render as
     * `-$2,500.00` rather than `$-2,500.00`.
     *
     * @param  mixed  $value  The raw price value
     * @return string The formatted price
     */
    private function formatPrice(mixed $value): string
    {
        // Coerce to a float and split the sign from the magnitude.
        $amount = is_numeric($value) ? (float) $value : 0.0;
        $sign = $amount < 0 ? '-' : '';

        return $sign.'$'.number_format(abs($amount), 2);
    }
}
