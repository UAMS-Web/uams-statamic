<?php

declare(strict_types=1);

namespace App\Widgets;

use App\Services\GoogleBusinessProfile\Categories\CategoryCheckLog;
use App\Services\GoogleBusinessProfile\Categories\CategoryDiff;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Statamic\Auth\User;
use Statamic\Facades\User as UserFacade;
use Statamic\Widgets\Widget;

/**
 * CP dashboard widget showing the last Google Business Profile category check (UAMS-Web/uams-statamic-archive#2615).
 *
 * It reads the record `gbp:check-categories` leaves and never runs a check itself. The
 * check is flagged overdue after {@see CategoryCheckLog::OVERDUE_AFTER_DAYS} days, so the
 * manual step UAMS-Web/uams-statamic-archive#2091 keeps until the Google API is available is prompted only when it is
 * actually late. Shown only to users who can view the taxonomy the report is about.
 */
class GoogleBusinessProfileCategoryCheck extends Widget
{
    /**
     * Render the widget, or nothing for a user who cannot view the taxonomy.
     */
    #[\Override]
    public function html(): string|View
    {
        /** @var User|null $user */
        $user = UserFacade::current();

        if ($user === null || ! $user->can('view '.CategoryDiff::TAXONOMY.' terms')) {
            return '';
        }

        $log = app(CategoryCheckLog::class);
        $latest = $log->latest();

        return view('widgets.google_business_profile_category_check', [
            'title' => $this->config('title', __('Google Business Profile categories')),
            'latest' => $latest,
            'overdue' => $latest !== null && $log->isOverdue($latest['checked_at'], Carbon::now()),
            'overdue_after_days' => CategoryCheckLog::OVERDUE_AFTER_DAYS,
        ]);
    }
}
