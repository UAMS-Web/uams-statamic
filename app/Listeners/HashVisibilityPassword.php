<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\Hash;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;

/**
 * Hashes an entry's `visibility_password` before it is persisted (UAMS-Web/uams-statamic-archive#1862).
 *
 * The field's own instructions promised hashing that nothing performed, so a
 * Control-Panel-typed password was written to the entry's YAML in plain text
 * with `is_password_hashed` left unset. The only reason password-protected
 * pages worked was the plain-text comparison fallback in the importer's
 * `PasswordVerifier`, which `UAMS-Web/wordpress-importer#5` removes. This
 * listener brings the Control Panel in line with what the WordPress importer's
 * `BaseEntryCreator` has always done — hash the value and set the flag in the
 * same write.
 *
 * **The stored value, not the flag, decides whether to hash.** Keying off
 * `is_password_hashed` looks equivalent and is not: an editor typing a *new*
 * password over an existing hash leaves the flag `true` from the previous save,
 * so a flag-driven listener would skip the new plain-text value and persist it
 * while the flag still claimed a hash — after which `Hash::check()` can never
 * match and nobody can open the entry. `Hash::isHashed()` inspects the value
 * itself, so a re-save of an unchanged hash is a no-op and a newly typed
 * password is always hashed, whatever the flag says.
 *
 * Runs on `EntrySaving` so the hash persists with the entry and no second save
 * is issued. It needs no `import:wordpress` bypass: the importer already stores
 * a hash, which this listener recognizes and leaves alone.
 */
final class HashVisibilityPassword
{
    /**
     * The field carrying the entry's password-protection password.
     */
    private const string PASSWORD_FIELD = 'visibility_password';

    /**
     * The flag recording that {@see self::PASSWORD_FIELD} holds a hash.
     */
    private const string HASHED_FLAG = 'is_password_hashed';

    /**
     * Hash a plain-text password before the entry is persisted.
     *
     * @param  EntrySaving  $event  The entry-saving event
     */
    public function handle(EntrySaving $event): void
    {
        $entry = $event->entry;

        if (! $entry instanceof Entry) {
            return;
        }

        // `visibility_password` is `localizable: false`, so a localization holds
        // no own copy. Reading with `get()` (not `value()`) keeps this to the
        // origin entry — resolving the origin's value here would write a
        // divergent per-site copy of a field that is deliberately not localizable.
        $password = $entry->get(self::PASSWORD_FIELD);

        if (! is_string($password) || $password === '') {
            // No password on this entry. Drop a stale flag if one survived the
            // password being cleared: `true` beside an empty value is a state no
            // verifier can satisfy.
            if ($entry->has(self::HASHED_FLAG)) {
                $entry->remove(self::HASHED_FLAG);
            }

            return;
        }

        // Already a hash — an unchanged re-save. Re-hashing here would replace a
        // digest that verifies with one derived from the digest itself, locking
        // the editor out of their own entry.
        if (Hash::isHashed($password)) {
            // Repair a hash whose flag was never written; without it
            // `PasswordVerifier` falls back to comparing plain text against a
            // digest, which cannot match.
            $entry->set(self::HASHED_FLAG, true);

            return;
        }

        $entry->set(self::PASSWORD_FIELD, Hash::make($password));
        $entry->set(self::HASHED_FLAG, true);
    }
}
