@php use function Statamic\trans as __; @endphp

{{-- Emulates Statamic v6 UI Panel + Header + Footer (Panel.vue, Header.vue, Footer.vue) --}}
{{-- `v-pre` on the root (UAMS-Web/uams-statamic-archive#2572): the dashboard compiles widget HTML as a Vue template, and the
     entry titles below are editor text, so a `{{ ... }}` in one would otherwise be evaluated. --}}
<div
    v-pre
    class="@container/panel relative bg-gray-150 dark:bg-gray-950/35 dark:inset-shadow-2xs dark:inset-shadow-black w-full rounded-2xl max-[600px]:p-1.25 p-1.75 focus-none starting-style-transition h-full flex flex-col overflow-hidden [&:has(>[data-ui-panel-header])]:pt-0"
    data-ui-panel
>
    <header class="px-4.5 py-3 flex items-center justify-between" data-ui-panel-header>
        <div data-ui-heading class="font-medium antialiased flex items-center gap-2 text-sm tracking-tight text-gray-700 dark:text-white">
            @cp_svg('icons/warning-diamond', 'size-4 shrink-0 text-gray-500 dark:text-gray-500')
            <span>{{ __($title) }}</span>
        </div>
    </header>
    {{-- No scan has completed yet (UAMS-Web/uams-statamic-archive#2506). This is its own state, never an empty
         list: "not yet checked" rendered as "nothing is broken" is the exact
         failure UAMS-Web/uams-statamic-archive#1905 closed, reached by a different road. --}}
    @if (($computed ?? false) !== true)
        <p class="mx-4.5 mb-2 rounded-lg bg-gray-100 dark:bg-gray-900 px-3 py-2 text-sm text-gray-700 dark:text-gray-300" role="status" data-incomplete-entries-state="not-computed">
            {{ __('This audit runs in the background and has not completed yet, so nothing has been checked. Its result will appear here once the scheduled scan finishes.') }}
        </p>
    @elseif (($content_changed_since ?? false) === true)
        <p class="mx-4.5 mb-2 text-xs text-gray-600 dark:text-gray-400" role="status" data-incomplete-entries-state="content-changed">
            {{ __('Entries have been saved since this audit ran :ago, so it may not reflect the latest edits. It refreshes on a schedule.', [
                'ago' => \Carbon\Carbon::createFromTimestamp($computed_at)->diffForHumans(),
            ]) }}
        </p>
    @endif
    {{-- The audit is uncapped by default (UAMS-Web/uams-statamic-archive#1905). If an explicit `limit` truncated
         it, say so HERE, above the result — a partial audit that looks complete is
         worse than no audit, because "nothing else is broken" and "the rest was
         never looked at" render identically. --}}
    @if (($scan['truncated'] ?? false) === true)
        <p class="mx-4.5 mb-2 rounded-lg bg-amber-50 dark:bg-amber-950/40 px-3 py-2 text-sm text-amber-900 dark:text-amber-200" role="status">
            {{ __('Only :scanned of :total published entries were checked, so this list is incomplete. Raise or remove the widget\'s :option setting to audit them all.', [
                'scanned' => $scan['scanned'],
                'total' => $scan['total'],
                'option' => 'limit',
            ]) }}
        </p>
    @endif
    <div class="flex-1 overflow-auto min-h-0">
        @if (($computed ?? false) !== true)
            {{-- Nothing below the notice above: an empty table here would read as a pass. --}}
        @elseif ($entries->isEmpty())
            {{-- "All published entries pass" is a claim about entries that were
                 never looked at, whenever the scan was truncated (UAMS-Web/uams-statamic-archive#1905). --}}
            <p class="px-4.5 py-3 text-sm text-gray-600 dark:text-gray-400">
                {{ ($scan['truncated'] ?? false) === true
                    ? __('None of the published entries that were checked failed validation.')
                    : __('All published entries pass current validation.') }}
            </p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="group rounded-none">{{ __('Title') }}</th>
                        <th class="group rounded-none">{{ __('Collection') }}</th>
                        <th class="group rounded-none">{{ __('Blueprint') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $item)
                        <tr class="align-top">
                            <td><a href="{{ $item['edit_url'] }}" class="title-index-field inline-flex items-center">{{ $item['title'] }}</a></td>
                            <td><span class="text-gray-600 dark:text-gray-400">{{ $item['collection_title'] }}</span></td>
                            <td><span class="text-gray-600 dark:text-gray-400">{{ $item['blueprint_title'] ?? '' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @if (isset($pagination) && $pagination['last_page'] > 1)
        <footer class="px-4.5 pt-2.5 md:pt-3 pb-1.5 antialiased flex items-center justify-between gap-4 text-sm text-gray-600 dark:text-gray-400 shrink-0" data-ui-panel-footer>
            <span>
                {{ __('Showing :from–:to of :total', [
                    'from' => ($pagination['current_page'] - 1) * $pagination['per_page'] + 1,
                    'to' => min($pagination['current_page'] * $pagination['per_page'], $pagination['total']),
                    'total' => $pagination['total'],
                ]) }}
            </span>
            <nav class="flex items-center gap-2" role="navigation" aria-label="{{ __('Pagination') }}">
                @php
                    $query = $pagination['query'];
                    $key = $pagination['page_query_key'];
                @endphp
                @if ($pagination['current_page'] > 1)
                    <a href="{{ $pagination['base_url'] . '?' . http_build_query(array_merge($query, [$key => $pagination['current_page'] - 1])) }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
                        {{ __('Previous') }}
                    </a>
                @endif
                @if ($pagination['current_page'] < $pagination['last_page'])
                    <a href="{{ $pagination['base_url'] . '?' . http_build_query(array_merge($query, [$key => $pagination['current_page'] + 1])) }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
                        {{ __('Next') }}
                    </a>
                @endif
            </nav>
        </footer>
    @endif
</div>
