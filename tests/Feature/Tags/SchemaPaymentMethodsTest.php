<?php

declare(strict_types=1);

use App\Tags\SchemaPaymentMethods;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_payment_methods` Antlers tag.
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaPaymentMethodsTest.php
 */
/**
 * Render the tag for the given methods / card brands / output mode.
 *
 * @param  array<int, string>  $methods
 * @param  array<int, string>  $cardBrands
 * @return mixed Decoded JSON for the accepted mode, raw string otherwise
 */
function renderPayment(array $methods, array $cardBrands = [], string $output = 'accepted'): mixed
{
    $template = $output === 'text'
        ? '{{ schema_payment_methods :methods="methods" output="text" }}'
        : '{{ schema_payment_methods :methods="methods" :card_brands="card_brands" }}';

    $out = trim((string) Antlers::parse(
        $template,
        ['methods' => $methods, 'card_brands' => $cardBrands],
        true
    ));

    return $output === 'text' ? $out : json_decode($out, true);
}

it('exposes schema_payment_methods as the tag handle', function (): void {
    expect(SchemaPaymentMethods::handle())->toBe('schema_payment_methods');
});

it('maps cash to a PaymentMethod with the Cash payment method type', function (): void {
    expect(renderPayment(['cash']))->toBe([
        [
            '@type' => 'https://schema.org/PaymentMethod',
            'paymentMethodType' => 'https://schema.org/Cash',
        ],
    ]);
});

it('maps creditCard with brands to CreditCard nodes carrying a Brand', function (): void {
    expect(renderPayment(['creditCard'], ['visa', 'mastercard']))->toBe([
        [
            '@type' => 'https://schema.org/CreditCard',
            'brand' => ['@type' => 'https://schema.org/Brand', 'name' => 'VISA'],
        ],
        [
            '@type' => 'https://schema.org/CreditCard',
            'brand' => ['@type' => 'https://schema.org/Brand', 'name' => 'MasterCard'],
        ],
    ]);
});

it('maps debitCard to PaymentCard nodes', function (): void {
    expect(renderPayment(['debitCard'], ['discover']))->toBe([
        [
            '@type' => 'https://schema.org/PaymentCard',
            'brand' => ['@type' => 'https://schema.org/Brand', 'name' => 'Discover'],
        ],
    ]);
});

it('emits a bare CreditCard node when no card brand is selected', function (): void {
    expect(renderPayment(['creditCard']))->toBe([
        ['@type' => 'https://schema.org/CreditCard'],
    ]);
});

it('orders cash, then credit, then debit nodes', function (): void {
    /** @var array<int, array<string, mixed>> $nodes */
    $nodes = renderPayment(['debitCard', 'cash', 'creditCard'], ['visa']);

    expect(array_column($nodes, '@type'))->toBe([
        'https://schema.org/PaymentMethod',
        'https://schema.org/CreditCard',
        'https://schema.org/PaymentCard',
    ]);
});

it('preserves the canonical card-brand order regardless of selection order', function (): void {
    /** @var array<int, array{brand: array{name: string}}> $nodes */
    $nodes = renderPayment(['creditCard'], ['jcb', 'visa', 'americanExpress']);

    $brandNames = array_map(
        /** @param array{brand: array{name: string}} $n */
        static fn (array $n): string => $n['brand']['name'],
        $nodes,
    );

    expect($brandNames)->toBe(['VISA', 'American Express', 'JCB']);
});

it('ignores check and digitalWallet in the structured accepted output', function (): void {
    expect(renderPayment(['check', 'digitalWallet']))->toBeNull();
});

it('joins check and digitalWallet into the paymentAccepted Text', function (): void {
    expect(renderPayment(['cash', 'check', 'digitalWallet'], [], 'text'))
        ->toBe('Check, Digital Wallet');
});

it('emits empty paymentAccepted Text when only structured methods are selected', function (): void {
    expect(renderPayment(['cash', 'creditCard'], [], 'text'))->toBe('');
});

it('emits nothing for the accepted output when no methods are selected', function (): void {
    expect(trim((string) Antlers::parse(
        '{{ schema_payment_methods :methods="methods" }}',
        ['methods' => []],
        true
    )))->toBe('');
});

it('emits nothing for the text output when no methods are selected', function (): void {
    expect(trim((string) Antlers::parse(
        '{{ schema_payment_methods :methods="methods" output="text" }}',
        ['methods' => []],
        true
    )))->toBe('');
});

it('emits both credit and debit card sets when both are selected', function (): void {
    /** @var array<int, array<string, mixed>> $nodes */
    $nodes = renderPayment(['creditCard', 'debitCard'], ['visa']);

    expect(array_column($nodes, '@type'))->toBe([
        'https://schema.org/CreditCard',
        'https://schema.org/PaymentCard',
    ]);
});

it('reads option keys from the augmented Checkboxes {key,value,label} shape', function (): void {
    // The schema engine passes the augmented Checkboxes value, a list of rows,
    // not a plain list of key strings — the tag must read each row's `value`.
    $methods = [
        ['key' => 'cash', 'value' => 'cash', 'label' => 'Cash'],
        ['key' => 'creditCard', 'value' => 'creditCard', 'label' => 'Credit Card'],
        ['key' => 'check', 'value' => 'check', 'label' => 'Check'],
    ];
    $brands = [
        ['key' => 'visa', 'value' => 'visa', 'label' => 'VISA'],
    ];

    $out = trim((string) Antlers::parse(
        '{{ schema_payment_methods :methods="methods" :card_brands="brands" }}',
        ['methods' => $methods, 'brands' => $brands],
        true
    ));

    expect(json_decode($out, true))->toBe([
        [
            '@type' => 'https://schema.org/PaymentMethod',
            'paymentMethodType' => 'https://schema.org/Cash',
        ],
        [
            '@type' => 'https://schema.org/CreditCard',
            'brand' => ['@type' => 'https://schema.org/Brand', 'name' => 'VISA'],
        ],
    ]);
});

it('reads the text output from the augmented Checkboxes shape', function (): void {
    $methods = [
        ['key' => 'check', 'value' => 'check', 'label' => 'Check'],
        ['key' => 'digitalWallet', 'value' => 'digitalWallet', 'label' => 'Digital Wallet'],
    ];

    $out = trim((string) Antlers::parse(
        '{{ schema_payment_methods :methods="methods" output="text" }}',
        ['methods' => $methods],
        true
    ));

    expect($out)->toBe('Check, Digital Wallet');
});
