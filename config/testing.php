<?php

declare(strict_types=1);

/**
 * Test-only configuration.
 *
 * Values used exclusively by the test suite, kept out of feature config so that
 * test wiring (env-backed paths, fixtures, toggles) does not leak into runtime
 * configuration. Reading these through `config()` keeps tests off direct
 * `env()` access, which returns null once config is cached.
 */

return [

    // Optional override for the Gravity Forms export fixtures consumed by the
    // importer package's GravityFormsNotificationAuditTest. That audit ships
    // its own committed synthetic corpus and needs nothing here; this key
    // exists only because `.env` is host-side, so an override has to be read
    // here and handed across. Null leaves the committed corpus in place. It
    // cannot point at the full wordpress-importer exports, whose file names
    // never match a sample.
    'gf_audit_fixtures_dir' => env('GF_AUDIT_FIXTURES_DIR'),

    // Optional override for the Formidable Forms XML export fixtures consumed
    // by the importer package's FormidableNotificationAuditTest and
    // FormidableRealImportSpotCheckTest. Both run against that package's
    // committed synthetic corpus by default; point this at the FormidableForms
    // directory of a clone of UAMS-Web/wordpress-importer-exports to audit the
    // real-world corpus instead. Null leaves the committed corpus in place.
    'frm_audit_fixtures_dir' => env('FRM_AUDIT_FIXTURES_DIR'),

];
