@php use function Statamic\trans as __; @endphp

{{-- Emulates Statamic v6 UI Panel + Header (Panel.vue, Header.vue), as the sibling widgets do.
     `v-pre` on the root: the dashboard compiles widget HTML as a Vue template, and category
     names come from Google's list, so a `{{ ... }}` in one would run. --}}
<div
    v-pre
    class="@container/panel relative bg-gray-150 dark:bg-gray-950/35 dark:inset-shadow-2xs dark:inset-shadow-black w-full rounded-2xl max-[600px]:p-1.25 p-1.75 focus-none starting-style-transition h-full flex flex-col overflow-hidden [&:has(>[data-ui-panel-header])]:pt-0"
    data-ui-panel
>
    <header class="px-4.5 py-3 flex items-center justify-between gap-3" data-ui-panel-header>
        <div data-ui-heading class="font-medium antialiased flex items-center gap-2 text-sm tracking-tight text-gray-700 dark:text-white">
            @cp_svg('icons/warning-diamond', 'size-4 shrink-0 text-gray-500 dark:text-gray-500')
            <span>{{ __($title) }}</span>
        </div>
    </header>
    <div class="flex-1 overflow-auto min-h-0 px-4.5 pb-3 text-sm text-gray-700 dark:text-gray-300">
        {{-- No check has completed: its own state, never "nothing changed". --}}
        @if ($latest === null)
            <p role="status" data-gbp-category-check-state="not-run">
                {{ __('No category check has completed yet. Run php artisan gbp:check-categories with a pasted category list.') }}
            </p>
        @else
            @if ($overdue)
                <p class="mb-2 rounded-lg bg-amber-50 dark:bg-amber-950/40 px-3 py-2 text-amber-900 dark:text-amber-200" role="status" data-gbp-category-check-state="overdue">
                    {{ __('The last check is more than :days days old. Run it again against a current category list.', ['days' => $overdue_after_days]) }}
                </p>
            @endif
            <p data-gbp-category-check-state="{{ $overdue ? 'overdue' : 'current' }}">
                {{ __(':new new, :renamed renamed, :removed removed, :unreadable listed but unreadable, :without_gcid with no GCID, :malformed malformed lines.', [
                    'new' => $latest['counts']['new'] ?? 0,
                    'renamed' => $latest['counts']['renamed'] ?? 0,
                    'removed' => $latest['counts']['removed'] ?? 0,
                    'unreadable' => $latest['counts']['unreadable'] ?? 0,
                    'without_gcid' => $latest['counts']['without_gcid'] ?? 0,
                    'malformed' => $latest['counts']['malformed'] ?? 0,
                ]) }}
            </p>
            <p class="mt-1 text-gray-600 dark:text-gray-400" data-gbp-category-check-last>
                {{ __('Last checked :ago from :source.', [
                    'ago' => \Carbon\Carbon::parse($latest['checked_at'])->diffForHumans(),
                    'source' => $latest['source'],
                ]) }}
            </p>
            @foreach (['renamed' => __('Renamed'), 'removed' => __('Removed'), 'unreadable' => __('Listed but unreadable')] as $kind => $label)
                @if (($latest['report'][$kind] ?? []) !== [])
                    <p class="mt-2 font-medium">{{ $label }}</p>
                    <ul class="list-disc ps-5" data-gbp-category-check-list="{{ $kind }}">
                        @foreach ($latest['report'][$kind] as $row)
                            <li>
                                {{ $row['title'] }} <span class="text-gray-600 dark:text-gray-400">({{ $row['gcid'] }})</span>
                                @if ($kind === 'renamed')
                                    &rarr; {{ $row['name'] }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
        @endif
    </div>
</div>
