<?php

declare(strict_types=1);

use App\Listeners\HashVisibilityPassword;
use App\Providers\EventServiceProvider;
use Illuminate\Support\Facades\Hash;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;
use UAMSWeb\WordPressImporter\Services\WordPressImport\Utilities\PasswordVerifier;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#1862: `visibility_password` claimed in its own field
 * instructions that it was hashed on save, and nothing hashed it. An
 * editor-typed password was written to the entry's YAML verbatim and
 * `is_password_hashed` stayed unset, so password-protected pages worked only
 * through the plain-text comparison fallback in the importer's
 * `PasswordVerifier` — the fallback `UAMS-Web/wordpress-importer#5` wants to
 * delete.
 *
 * The listener keys off whether the stored value is *already a hash*, not off
 * the `is_password_hashed` flag. The flag alone gets one case wrong and it is
 * the dangerous one: an editor typing a NEW password over an existing hash
 * leaves the flag `true` from the previous save, so a flag-driven listener
 * would skip the new plain-text value and store it with `is_password_hashed`
 * still `true` — after which `Hash::check()` fails against plain text and
 * nobody can open the entry.
 *
 * @command  php artisan test --compact tests/Unit/Listeners/HashVisibilityPasswordTest.php
 */
/**
 * Build a hermetic entry carrying `$data` and run the listener over it.
 *
 * `Entry::make()` is declared to return the *contract*, which has no `data()`,
 * so the concrete type is narrowed by control flow rather than by an `assert()`
 * or an inline `@var` (UAMS-Web/uams-statamic-archive#1733 / UAMS-Web/uams-statamic-archive#1865).
 *
 * @param  array<string, mixed>  $data  The entry's stored data
 */
function saveEntryWith(array $data): StatamicEntry
{
    $entry = Entry::make();

    if (! $entry instanceof StatamicEntry) {
        throw new RuntimeException('Entry::make() did not return '.StatamicEntry::class.'.');
    }

    $entry->data($data);

    (new HashVisibilityPassword)->handle(new EntrySaving($entry));

    return $entry;
}

/**
 * Read a stored value that must be a string (or absent), failing by name rather
 * than letting a wrong type reach an assertion as `mixed`.
 *
 * @param  StatamicEntry  $entry  The entry to read
 * @param  string  $key  The field handle
 * @return ?string The stored string, or null when the field is unset
 */
function storedString(StatamicEntry $entry, string $key): ?string
{
    $value = $entry->get($key);

    if ($value === null) {
        return null;
    }

    if (! is_string($value)) {
        throw new RuntimeException(sprintf('Expected "%s" to hold a string, got %s.', $key, get_debug_type($value)));
    }

    return $value;
}

/**
 * Read a stored value that must be a bool (or absent).
 *
 * @param  StatamicEntry  $entry  The entry to read
 * @param  string  $key  The field handle
 * @return ?bool The stored flag, or null when the field is unset
 */
function storedFlag(StatamicEntry $entry, string $key): ?bool
{
    $value = $entry->get($key);

    if ($value === null) {
        return null;
    }

    if (! is_bool($value)) {
        throw new RuntimeException(sprintf('Expected "%s" to hold a bool, got %s.', $key, get_debug_type($value)));
    }

    return $value;
}

it('hashes a newly typed password and flags it', function (): void {
    $entry = saveEntryWith([
        'password_protected' => true,
        'visibility_password' => 'correct horse battery staple',
    ]);

    $stored = (string) storedString($entry, 'visibility_password');

    expect($stored)->not->toBe('correct horse battery staple');
    expect(Hash::isHashed($stored))->toBeTrue();
    expect(storedFlag($entry, 'is_password_hashed'))->toBeTrue();
});

it('verifies through PasswordVerifier against the stored hash', function (): void {
    $entry = saveEntryWith([
        'password_protected' => true,
        'visibility_password' => 'chekov-starfleet-1701',
    ]);

    $verifier = new PasswordVerifier;

    expect($verifier->verify($entry, 'chekov-starfleet-1701'))->toBeTrue();
    expect($verifier->verify($entry, 'wrong password'))->toBeFalse();
});

it('leaves an already-hashed password untouched on an unchanged re-save', function (): void {
    $hash = Hash::make('enterprise-ncc-1701');

    $entry = saveEntryWith([
        'password_protected' => true,
        'visibility_password' => $hash,
        'is_password_hashed' => true,
    ]);

    // Byte-identical: a re-hash would both change the value and break
    // verification against the password the editor actually set.
    expect(storedString($entry, 'visibility_password'))->toBe($hash);
    expect(storedFlag($entry, 'is_password_hashed'))->toBeTrue();
    expect((new PasswordVerifier)->verify($entry, 'enterprise-ncc-1701'))->toBeTrue();
});

it('hashes a new password typed over an existing hash', function (): void {
    // The flag is still `true` from the previous save — the case that makes
    // `is_password_hashed` the wrong authority to key off.
    $entry = saveEntryWith([
        'password_protected' => true,
        'visibility_password' => 'shore-leave-omicron',
        'is_password_hashed' => true,
    ]);

    expect(storedString($entry, 'visibility_password'))->not->toBe('shore-leave-omicron');
    expect(Hash::isHashed((string) storedString($entry, 'visibility_password')))->toBeTrue();
    expect((new PasswordVerifier)->verify($entry, 'shore-leave-omicron'))->toBeTrue();
});

it('flags an already-hashed value whose flag was never set', function (): void {
    // Defensive: a hash with no flag would otherwise verify by plain-text
    // comparison, which can never match a bcrypt digest.
    $hash = Hash::make('scotty-transporter');

    $entry = saveEntryWith([
        'password_protected' => true,
        'visibility_password' => $hash,
    ]);

    expect(storedString($entry, 'visibility_password'))->toBe($hash);
    expect(storedFlag($entry, 'is_password_hashed'))->toBeTrue();
});

it('leaves an entry with no password alone', function (): void {
    $entry = saveEntryWith(['title' => 'Unprotected']);

    expect(storedString($entry, 'visibility_password'))->toBeNull();
    expect(storedFlag($entry, 'is_password_hashed'))->toBeNull();
});

it('clears the flag when the password is cleared', function (): void {
    $entry = saveEntryWith([
        'password_protected' => false,
        'visibility_password' => '',
        'is_password_hashed' => true,
    ]);

    // A `true` flag beside an empty password is a state no verifier can satisfy.
    expect(storedFlag($entry, 'is_password_hashed'))->toBeNull();
});

it('does not write the field onto a localization that has no own value', function (): void {
    // `visibility_password` is `localizable: false`, so a localization holds no
    // copy and `get()` returns null. Writing one here would create a divergent
    // per-site password out of thin air.
    $entry = saveEntryWith(['title' => 'Localized']);

    expect($entry->has('visibility_password'))->toBeFalse();
    expect($entry->has('is_password_hashed'))->toBeFalse();
});

it('is registered for EntrySaving', function (): void {
    // Every other test drives the listener directly, which would pass just as
    // happily if it were never wired up. Dispatching the real event instead
    // would run the whole EntrySaving chain — several of whose listeners
    // require a real collection — so assert the registration itself.
    $listen = new ReflectionProperty(EventServiceProvider::class, 'listen');

    /** @var array<class-string, list<class-string>> $map */
    $map = $listen->getValue(new EventServiceProvider(app()));

    expect($map[EntrySaving::class])->toContain(HashVisibilityPassword::class);
});

it('describes what the code actually does in the field instructions', function (): void {
    /** @var array{fields: list<array{handle: string, field: array<string, mixed>}>} $fieldset */
    $fieldset = Yaml::parseFile(resource_path('fieldsets/uamsweb_common_status.yaml'));

    $field = collect($fieldset['fields'])->firstWhere('handle', 'visibility_password');

    if ($field === null) {
        throw new RuntimeException('No `visibility_password` field in uamsweb_common_status.yaml.');
    }

    $instructions = $field['field']['instructions'] ?? null;

    if (! is_string($instructions)) {
        throw new RuntimeException('The `visibility_password` field carries no instructions string.');
    }

    // Hashing is not encryption, and the old text claimed the latter.
    expect($instructions)->not->toContain('encrypted');
    expect($instructions)->toContain('hashed');
});
