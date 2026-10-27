<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaObjectFragmentBuilder;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: emit a location's accepted-payment data as JSON-LD.
 *
 * The location blueprint stores two checkbox fields — `accepted_payment_methods`
 * (cash / check / creditCard / debitCard / digitalWallet) and, when a card method
 * is selected, `accepted_card_brands` (visa / mastercard / …). schema.org has no
 * single property that expresses all of these, so per the UAMS-Web/uams-statamic-archive#967 design they split
 * across two output modes selected by the `output` parameter:
 *
 *   - output="accepted" (default) → a JSON array for `acceptedPaymentMethod`:
 *       cash       → {"@type":"PaymentMethod","paymentMethodType":"https://schema.org/Cash"}
 *       creditCard → one {"@type":"CreditCard", brand?} per selected card brand
 *       debitCard  → one {"@type":"PaymentCard", brand?} per selected card brand
 *     (check / digitalWallet contribute nothing here — they have no exact
 *     PaymentMethodType member.)
 *
 *   - output="text" → comma-joined Text for `paymentAccepted`, covering only the
 *     methods with no structured representation:
 *       check → "Check", digitalWallet → "Digital Wallet".
 *
 * Output is empty when the relevant mode has nothing to emit (e.g. `payment_query`
 * off, so `accepted_payment_methods` is empty).
 *
 * Parameters:
 *   - methods: bound value of `accepted_payment_methods` (array of option keys).
 *   - card_brands: bound value of `accepted_card_brands` (array of option keys).
 *   - output: "accepted" (default) or "text".
 *
 * Example:
 *   {{ schema_payment_methods :methods="accepted_payment_methods" :card_brands="accepted_card_brands" }}
 *   {{ schema_payment_methods :methods="accepted_payment_methods" output="text" }}
 *
 * @property Parameters $params
 */
class SchemaPaymentMethods extends Tags
{
    /**
     * Registered Antlers handle `{{ schema_payment_methods }}`.
     *
     * @var string
     */
    protected static $handle = 'schema_payment_methods';

    /**
     * Card-brand option key => human-readable `Brand` name (blueprint labels).
     *
     * @var array<string, string>
     */
    private const array CARD_BRAND_LABELS = [
        'visa' => 'VISA',
        'mastercard' => 'MasterCard',
        'americanExpress' => 'American Express',
        'discover' => 'Discover',
        'dinersClub' => 'Diners Club',
        'jcb' => 'JCB',
        'chinaUnionPay' => 'China Union Pay',
    ];

    /**
     * Payment-method option key => `paymentAccepted` Text label, for the methods
     * with no exact structured representation.
     *
     * @var array<string, string>
     */
    private const array TEXT_METHOD_LABELS = [
        'check' => 'Check',
        'digitalWallet' => 'Digital Wallet',
    ];

    /**
     * Memoized `SchemaObjectFragmentBuilder::normalizedType()` results by short name.
     *
     * @var array<string, string|null>
     */
    private static array $normalizedTypeByShortName = [];

    /**
     * Build the JSON-LD text for the selected output mode.
     *
     * @return string JSON array, plain Text, or empty string when nothing emits
     */
    public function index(): string
    {
        // Resolve the output mode (default to the structured acceptedPaymentMethod)
        $output = $this->normalizedOutputMode();

        // Read the selected payment-method option keys
        $methods = $this->selectedOptionKeys('methods');

        // Branch on the requested output mode
        if ($output === 'text') {
            // Build the paymentAccepted Text from the no-structured-form methods
            return $this->buildPaymentAcceptedText($methods);
        }

        // Build the structured acceptedPaymentMethod array
        return $this->buildAcceptedPaymentMethodJson(
            $methods,
            $this->selectedOptionKeys('card_brands')
        );
    }

    /**
     * Resolve the `output` parameter to `text` or `accepted` (default).
     */
    private function normalizedOutputMode(): string
    {
        // Read the raw output parameter
        $raw = $this->params->get('output');

        // Normalize a string output mode to lowercase
        $mode = \is_string($raw) ? strtolower(trim($raw)) : '';

        // Only `text` selects the paymentAccepted branch; everything else is accepted
        return $mode === 'text' ? 'text' : 'accepted';
    }

    /**
     * Build the comma-joined `paymentAccepted` Text for check / digitalWallet.
     *
     * @param  array<int, string>  $methods  Selected payment-method option keys
     * @return string Comma-joined labels, or empty string when none apply
     */
    private function buildPaymentAcceptedText(array $methods): string
    {
        // Collect the Text labels for the selected no-structured-form methods
        $labels = [];

        // Preserve the canonical option order from the label map
        foreach (self::TEXT_METHOD_LABELS as $key => $label) {
            // Include the label only when the method was selected
            if (\in_array($key, $methods, true)) {
                // Append the human-readable label
                $labels[] = $label;
            }
        }

        // Join the labels for the paymentAccepted Text value
        return implode(', ', $labels);
    }

    /**
     * Build the `acceptedPaymentMethod` JSON array for cash / credit / debit.
     *
     * @param  array<int, string>  $methods  Selected payment-method option keys
     * @param  array<int, string>  $cardBrands  Selected card-brand option keys
     * @return string JSON array, or empty string when nothing structured applies
     */
    private function buildAcceptedPaymentMethodJson(
        array $methods,
        array $cardBrands
    ): string {
        // Accumulate the payment-method nodes in canonical option order
        $nodes = [];

        // Cash maps to a PaymentMethod with the Cash paymentMethodType member
        if (\in_array('cash', $methods, true)) {
            // Resolve the PaymentMethod type and the Cash member URI
            $paymentMethodType = $this->cachedNormalizedType('PaymentMethod');
            $cashMember = $this->cachedNormalizedType('Cash');

            // Emit the cash node only when both vocabulary URIs resolve
            if ($paymentMethodType !== null && $cashMember !== null) {
                // Append the cash PaymentMethod node
                $nodes[] = [
                    '@type' => $paymentMethodType,
                    'paymentMethodType' => $cashMember,
                ];
            }
        }

        // Credit cards map to CreditCard nodes, one per selected brand
        if (\in_array('creditCard', $methods, true)) {
            // Merge the resolved CreditCard nodes into the accumulator
            $nodes = array_merge(
                $nodes,
                $this->cardNodes('CreditCard', $cardBrands)
            );
        }

        // Debit cards map to PaymentCard nodes, one per selected brand
        if (\in_array('debitCard', $methods, true)) {
            // Merge the resolved PaymentCard nodes into the accumulator
            $nodes = array_merge(
                $nodes,
                $this->cardNodes('PaymentCard', $cardBrands)
            );
        }

        // Emit nothing when no structured method was selected
        if ($nodes === []) {
            // Return an empty string to omit the property
            return '';
        }

        // Serialize the accepted-payment-method array for JSON-LD embedding
        $json = json_encode($nodes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Return the JSON array, or an empty string when encoding fails
        return $json === false ? '' : $json;
    }

    /**
     * Build the card nodes for a card type, one per selected brand.
     *
     * Falls back to a single bare-type node when no card brand is selected, so a
     * card method still emits an acceptedPaymentMethod entry.
     *
     * @param  string  $shortType  `CreditCard` or `PaymentCard`
     * @param  array<int, string>  $cardBrands  Selected card-brand option keys
     * @return array<int, array<string, mixed>> Card payment-method nodes
     */
    private function cardNodes(string $shortType, array $cardBrands): array
    {
        // Resolve the card type's vocabulary URI
        $cardType = $this->cachedNormalizedType($shortType);

        // Emit nothing when the card type is unusable
        if ($cardType === null) {
            // Return an empty list to skip this card type
            return [];
        }

        // Collect the known brand labels in canonical option order
        $brandLabels = [];

        // Preserve the canonical brand order from the label map
        foreach (self::CARD_BRAND_LABELS as $key => $label) {
            // Include the label only when the brand was selected
            if (\in_array($key, $cardBrands, true)) {
                // Append the human-readable brand label
                $brandLabels[] = $label;
            }
        }

        // Without any known brand, emit a single bare card-type node
        if ($brandLabels === []) {
            // Return one node carrying only the card @type
            return [['@type' => $cardType]];
        }

        // Resolve the Brand type once for the nested brand nodes
        $brandType = $this->cachedNormalizedType('Brand');

        // Build one card node per selected brand
        $nodes = [];

        // Walk each resolved brand label
        foreach ($brandLabels as $label) {
            // Start the card node with its @type
            $node = ['@type' => $cardType];

            // Attach a nested Brand node when the Brand type resolved
            if ($brandType !== null) {
                // Add the brand as a nested Brand node
                $node['brand'] = [
                    '@type' => $brandType,
                    'name' => $label,
                ];
            }

            // Append the completed card node
            $nodes[] = $node;
        }

        // Return the per-brand card nodes
        return $nodes;
    }

    /**
     * Read a bound checkbox parameter as a list of string option keys.
     *
     * Handles both the raw stored shape (a plain list of option-key strings) and
     * the augmented Checkboxes shape the schema engine passes — a list of
     * `['key' => …, 'value' => …, 'label' => …]` rows (or `LabeledValue`
     * objects). The option key is read from `value`, then `key`.
     *
     * @param  string  $param  Tag parameter name (`methods` or `card_brands`)
     * @return array<int, string> Selected option keys (empty when unset)
     */
    private function selectedOptionKeys(string $param): array
    {
        // Read the raw bound value
        $raw = $this->params->get($param);

        // Unwrap a Statamic field value
        if ($raw instanceof Value) {
            // Read the inner value for coercion
            $raw = $raw->value();
        }

        // Treat a non-array value as no selection
        if (! \is_array($raw)) {
            // Return an empty list
            return [];
        }

        // Normalize each element to its option-key string
        $keys = [];

        // Walk every selected element
        foreach ($raw as $element) {
            // Resolve the option key for this element (string or labeled row)
            $key = $this->optionKeyFromElement($element);

            // Keep only resolvable string keys
            if ($key !== null) {
                // Append the resolved option key
                $keys[] = $key;
            }
        }

        // Return the resolved option keys
        return $keys;
    }

    /**
     * Resolve a single selected element to its option-key string.
     *
     * @param  mixed  $element  A plain string key, an augmented `{key,value,label}`
     *                          row, or a `LabeledValue`
     * @return string|null The option key, or null when it cannot be resolved
     */
    private function optionKeyFromElement(mixed $element): ?string
    {
        // Unwrap a Statamic field value
        if ($element instanceof Value) {
            // Read the inner value for coercion
            $element = $element->value();
        }

        // Read the underlying value of an augmented labeled option
        if ($element instanceof LabeledValue) {
            // Use the option's stored value as the key
            $element = $element->value();
        }

        // A plain string is already the option key
        if (\is_string($element)) {
            // Return the trimmed key when non-empty
            return $element === '' ? null : $element;
        }

        // The augmented Checkboxes shape exposes the key under `value`/`key`
        if (\is_array($element)) {
            // Prefer the stored value, then the option key
            $key = $element['value'] ?? $element['key'] ?? null;

            // Return the key only when it is a non-empty string
            return \is_string($key) && $key !== '' ? $key : null;
        }

        // Anything else is not a resolvable option key
        return null;
    }

    /**
     * Resolved HTTPS schema.org URI for a short type/member name (memoized).
     *
     * @param  string  $shortName  e.g. `PaymentMethod`, `CreditCard`, `Cash`
     * @return string|null Same as `SchemaObjectFragmentBuilder::normalizedType()`
     */
    private function cachedNormalizedType(string $shortName): ?string
    {
        // Resolve and memoize the normalized URI on first use
        if (! \array_key_exists($shortName, self::$normalizedTypeByShortName)) {
            self::$normalizedTypeByShortName[$shortName] = SchemaObjectFragmentBuilder::normalizedType(
                $shortName
            );
        }

        // Return the cached normalized URI
        return self::$normalizedTypeByShortName[$shortName];
    }
}
