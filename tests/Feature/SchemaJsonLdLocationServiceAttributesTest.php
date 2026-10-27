<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Location JSON-LD: images, payment, accessibility amenities, specialty and languages (UAMS-Web/uams-statamic-archive#967).
 *
 * One of five suites split from the former single `SchemaJsonLdLocationTest`
 * (UAMS-Web/uams-statamic-archive#1797) — that class ran 1085s of a 1306s parallel band, and paratest
 * distributes by class, so it bounded the band on its own. Shared fixtures and
 * node builders live in `tests/Support/SchemaLocationFixtures.php`.
 *
 * Renders the node directly via
 * `SchemaOutputService::buildStandaloneSchemaObjectNode()` against an in-test
 * fixture location entry built in memory, so no content is saved. The
 * schema-template entries it resolves ship in the `content/` submodule, so this
 * suite is content-dependent: it runs green in CI / locally where `content/` is
 * present.
 *
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdLocationServiceAttributesTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();

    // Bind a request so @id resolution has a host to anchor on.
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));

    // Pin "today" so the fixed future-dated special-hours fixtures below stay
    // deterministic against the UAMS-Web/uams-statamic-archive#1328 auto-expiry filter regardless of the real
    // calendar date (a fixture dated 2026-11-26 must not silently expire once
    // that day passes).
    $this->travelTo(Carbon::parse('2026-07-11 09:00:00'));
});

it('omits image and photo when the location has no images', function (): void {
    // The populated image/photo path resolves real Asset objects through the
    // shared ImageObject template, so it is exercised by the pre-merge
    // real-import smoke test; here we assert the empty path renders cleanly.
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
    ]);

    expect($node)->not->toHaveKey('image');
    expect($node)->not->toHaveKey('photo');
});

it('emits structured acceptedPaymentMethod and paymentAccepted Text from the payment fields', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'payment_query' => true,
        'accepted_payment_methods' => ['cash', 'creditCard', 'check', 'digitalWallet'],
        'accepted_card_brands' => ['visa', 'mastercard'],
    ]);

    expect($node['acceptedPaymentMethod'])->toBe([
        [
            '@type' => 'https://schema.org/PaymentMethod',
            'paymentMethodType' => 'https://schema.org/Cash',
        ],
        [
            '@type' => 'https://schema.org/CreditCard',
            'brand' => ['@type' => 'https://schema.org/Brand', 'name' => 'VISA'],
        ],
        [
            '@type' => 'https://schema.org/CreditCard',
            'brand' => ['@type' => 'https://schema.org/Brand', 'name' => 'MasterCard'],
        ],
    ]);
    expect($node['paymentAccepted'])->toBe('Check, Digital Wallet');
});

it('omits both payment properties when payment_query is off', function (): void {
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'payment_query' => false,
        'accepted_payment_methods' => ['cash', 'creditCard'],
    ]);

    expect($node)->not->toHaveKey('acceptedPaymentMethod');
    expect($node)->not->toHaveKey('paymentAccepted');
});

it('emits amenityFeature LocationFeatureSpecifications from the answered accessibility toggles', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'google_business_profile_has_wheelchair_accessible_entrance' => true,
        'google_business_profile_has_wheelchair_accessible_restroom' => false,
        'google_business_profile_has_hearing_loop' => 'notapplicable',
    ]);

    expect($node['amenityFeature'])->toBe([
        [
            '@type' => 'https://schema.org/LocationFeatureSpecification',
            'name' => 'Wheelchair accessible entrance',
            'value' => true,
        ],
        [
            '@type' => 'https://schema.org/LocationFeatureSpecification',
            'name' => 'Wheelchair accessible restroom',
            'value' => false,
        ],
    ]);
});

it('omits amenityFeature when no accessibility toggle is answered', function (): void {
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
    ]);

    expect($node)->not->toHaveKey('amenityFeature');
});

it('emits medicalSpecialty as schema.org URIs from the specialty field', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'schema_medicalspecialty' => ['Cardiovascular', 'Dermatology'],
    ]);

    expect($node['medicalSpecialty'])->toBe([
        'https://schema.org/Cardiovascular',
        'https://schema.org/Dermatology',
    ]);
});

it('omits medicalSpecialty when no specialty is selected', function (): void {
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
    ]);

    expect($node)->not->toHaveKey('medicalSpecialty');
});

it('emits knowsLanguage as Language nodes when interpreter services are offered', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'interpreter_query' => true,
        'interpreter_language' => ['es', 'vi'],
    ]);

    expect($node['knowsLanguage'])->toBe([
        [
            '@type' => 'https://schema.org/Language',
            'name' => 'Spanish, Castilian',
            'alternateName' => 'es',
        ],
        [
            '@type' => 'https://schema.org/Language',
            'name' => 'Vietnamese',
            'alternateName' => 'vi',
        ],
    ]);
});

it('omits knowsLanguage when interpreter services are not offered', function (): void {
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'interpreter_query' => false,
        'interpreter_language' => ['es'],
    ]);

    expect($node)->not->toHaveKey('knowsLanguage');
});
