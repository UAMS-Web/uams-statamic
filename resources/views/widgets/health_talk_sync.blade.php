@php use function Statamic\trans as __; @endphp

{{-- Emulates Statamic v6 UI Panel + Header (Panel.vue, Header.vue), as the sibling widgets do.
     `v-pre` on the root: the dashboard compiles widget HTML as a Vue template, and the
     speaker strings below come from a vendor page, so a `{{ ... }}` in one would run. --}}
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
        @if ($sync_url !== '')
            <form method="POST" action="{{ $sync_url }}">
                @csrf
                <button type="submit" class="text-sm text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300" data-health-talk-sync-now>
                    {{ __('Run sync now') }}
                </button>
            </form>
        @endif
    </header>
    {{-- The most recent run failed or was refused. The backlog below is still the last
         completed run's, which is why the failure is named rather than hidden. --}}
    @if ($failure !== null)
        <p class="mx-4.5 mb-2 rounded-lg bg-amber-50 dark:bg-amber-950/40 px-3 py-2 text-sm text-amber-900 dark:text-amber-200" role="status" data-health-talk-state="failed">
            {{ __('The last sync changed nothing: :reason', ['reason' => $failure]) }}
        </p>
    @endif
    <div class="flex-1 overflow-auto min-h-0 px-4.5 pb-3 text-sm text-gray-700 dark:text-gray-300">
        {{-- No run has completed yet: its own state, never "nothing deferred". --}}
        @if ($completed === null)
            <p role="status" data-health-talk-state="not-run">
                {{ __('The UAMS Health Talk sync has not completed a run yet, so nothing has been checked.') }}
            </p>
        @else
            <p data-health-talk-state="deferred">
                {{ __(':episodes episodes deferred on :speakers unmapped speakers.', [
                    'episodes' => $deferred_count,
                    'speakers' => count($unmapped),
                ]) }}
                <span class="text-gray-600 dark:text-gray-400">
                    {{ __('Last completed :ago.', ['ago' => \Carbon\Carbon::parse($completed['ran_at'])->diffForHumans()]) }}
                </span>
            </p>
            @if ($unmapped !== [])
                <ul class="mt-2 list-disc ps-5" data-health-talk-unmapped>
                    @foreach ($unmapped as $speaker)
                        <li>{{ $speaker }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($mapping_url !== null)
                <p class="mt-2">
                    <a href="{{ $mapping_url }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">{{ __('Map speakers') }}</a>
                </p>
            @endif
        @endif
    </div>
</div>
