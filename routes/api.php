<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\FindADoc\ProviderIndexController;
use App\Http\Controllers\Api\V1\FindADoc\ProviderShowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Registered via bootstrap/app.php `withRouting(api: ...)`, so every route
| here is prefixed with `/api` and runs under the `api` middleware group.
|
| Find-a-Doc read API (UAMS-Web/uams-statamic-archive#1043): a versioned, read-only analog of the WordPress
| UAMSWP-Find-a-Doc `provider_meta` REST payload consumed by the shipped "UAMS
| Health" native app. FindADocProviderRepository enforces the UAMS-Web/uams-statamic-archive#919 eligibility +
| searchable gate, and the payload is an allow-list, so no `people` PII (#803) is
| exposed. Backward compatibility is a hard requirement (uncontrolled consumer),
| hence the explicit `/v1/` version segment.
|
*/

$rateLimit = config('find_a_doc_api.rate_limit_per_minute', 60);
$perMinute = is_int($rateLimit) ? $rateLimit : 60;

Route::middleware(sprintf('throttle:%d,1', $perMinute))
    ->prefix('v1/find-a-doc')
    ->group(function (): void {
        Route::get('providers', ProviderIndexController::class);
        Route::get('providers/{provider}', ProviderShowController::class);
    });
