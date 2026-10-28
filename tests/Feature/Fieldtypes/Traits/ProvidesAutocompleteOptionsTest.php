<?php

declare(strict_types=1);

use App\Fieldtypes\Traits\ProvidesAutocompleteOptions;
use Tests\TestCase;

covers(ProvidesAutocompleteOptions::class);

uses(TestCase::class);

/**
 * Behavior of the ProvidesAutocompleteOptions trait
 * (app/Fieldtypes/Traits/ProvidesAutocompleteOptions.php): the full list of HTML
 * `autocomplete` tokens led by a 'null' option, and the list narrowed to the
 * keys a fieldtype allows, in the full list's order.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/Traits/ProvidesAutocompleteOptionsTest.php
 */

/**
 * The trait's options for a fieldtype that allows the given keys.
 *
 * @param  list<string>  $allowed
 * @return array<string, string>
 */
function autocompleteOptions(array $allowed = []): array
{
    $harness = new class($allowed)
    {
        use ProvidesAutocompleteOptions {
            getAutocompleteOptions as public;
        }

        /** @param  list<string>  $allowed */
        public function __construct(private array $allowed) {}

        /** @return list<string> */
        protected function getAllowedAutocompleteKeys(): array
        {
            return $this->allowed;
        }
    };

    return $harness->getAutocompleteOptions();
}

it('offers every token, led by the null option, when no keys are restricted', function (): void {
    expect(array_keys(autocompleteOptions()))->toBe([
        'null',
        'name', 'honorific-prefix', 'given-name', 'additional-name', 'family-name', 'honorific-suffix',
        'nickname', 'bday', 'bday-day', 'bday-month', 'bday-year', 'sex', 'photo',
        'email', 'tel', 'tel-country-code', 'tel-national', 'tel-area-code', 'tel-local',
        'tel-local-prefix', 'tel-local-suffix', 'tel-extension', 'impp',
        'street-address', 'address-line1', 'address-line2', 'address-line3', 'address-level1',
        'address-level2', 'address-level3', 'address-level4', 'postal-code', 'country', 'country-name',
        'organization', 'organization-title',
        'cc-name', 'cc-given-name', 'cc-additional-name', 'cc-family-name', 'cc-number', 'cc-exp',
        'cc-exp-month', 'cc-exp-year', 'cc-csc', 'cc-type',
        'current-password', 'new-password', 'username', 'one-time-code', 'webauthn',
        'url', 'language',
        'transaction-amount', 'transaction-currency',
    ])
        ->and(autocompleteOptions()['null'])->toBe('Not Specified')
        ->and(autocompleteOptions()['email'])->toBe('Email (email)');
});

it('narrows to the allowed keys in the full list\'s order and drops unknown keys', function (): void {
    expect(autocompleteOptions(['tel', 'not-a-token', 'email']))->toBe([
        'null' => 'Not Specified',
        'email' => 'Email (email)',
        'tel' => 'Telephone (tel)',
    ]);
});

it('offers only the null option when no allowed key is a token', function (): void {
    expect(autocompleteOptions(['not-a-token']))->toBe(['null' => 'Not Specified']);
});

it('allows every token by default', function (): void {
    $harness = new class
    {
        use ProvidesAutocompleteOptions {
            getAllowedAutocompleteKeys as public;
        }
    };

    expect($harness->getAllowedAutocompleteKeys())->toBe([]);
});
