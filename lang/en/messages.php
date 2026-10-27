<?php

declare(strict_types=1);

// Proxy app "messages.*" keys to Statamic vendor message overrides.
//
// This file exists as a workaround for Statamic's create label lookup in
// \Statamic\Entries\Collection::createLabel(), which currently resolves:
// "messages.{$this->handle()}_collection_create_entry"
// instead of:
// "statamic::messages.{$this->handle()}_collection_create_entry"
//
// By requiring `lang/vendor/statamic/en/messages.php` here, keys like
// `post_collection_create_entry` are available under the expected
// `messages.*` namespace.
//
// Important: keep this as a compatibility workaround only.
// If a future Statamic release changes `createLabel()` to resolve
// `statamic::messages.*` directly, remove this file-level proxy to avoid
// unnecessary indirection.
return require base_path('lang/vendor/statamic/en/messages.php');
