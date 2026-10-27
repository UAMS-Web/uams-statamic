<script setup>
import { Head, router } from '@statamic/cms/inertia';
import {
    Header,
    Button,
    Panel,
    PanelHeader,
    Heading,
    Card,
    Description,
    Badge,
    CommandPaletteItem,
    Input,
} from '@statamic/cms/ui';

// Accept JSON-LD cache state and CP action URLs from the Inertia controller
const props = defineProps({
    enabled: Boolean,
    ttlSeconds: Number,
    bust: Number,
    lastBump: Object,
    lastWarm: Object,
    clearUrl: String,
    warmUrl: String,
    displayTimezone: String,
});

// Fixed Artisan command for clearing JSON-LD cache from a terminal (matches CP Clear)
const clearTerminalCommand = 'php artisan schema-templates:clear-json-ld-cache';

const warmTerminalCommand = 'php artisan schema-templates:warm-json-ld-cache';

// Format ISO-8601 cache timestamps in Statamic `display_timezone` (`STATAMIC_DISPLAY_TIMEZONE`)
function formatCacheTimestamp(isoString) {
    // Reject empty or non-string inputs from optional metadata fields
    if (typeof isoString !== 'string' || isoString === '') {
        return '';
    }

    // Parse the stored instant (PHP writes ISO-8601 via Carbon::toIso8601String)
    const instant = new Date(isoString);

    // Return the raw string when the value is not a valid date
    if (Number.isNaN(instant.getTime())) {
        return isoString;
    }

    // Resolve the IANA zone name from Statamic config (falls back when prop is absent)
    const timeZone = props.displayTimezone || 'UTC';

    // Format using the Statamic display timezone so CP matches `config/statamic/system.php`
    try {
        return new Intl.DateTimeFormat(undefined, {
            timeZone,
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(instant);
    } catch {
        // Fall back to UTC when the configured zone is unknown to the runtime
        return new Intl.DateTimeFormat(undefined, {
            timeZone: 'UTC',
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(instant);
    }
}

function clearCache() {
    // Post the clear route so the server bumps the bust counter and flashes success
    router.post(props.clearUrl);
}

function warmCache() {
    router.post(props.warmUrl);
}
</script>

<template>
    <Head :title="__('JSON-LD cache')" />
    <div class="max-w-page mx-auto">
        <Header :title="__('JSON-LD cache')" icon="cache" />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <Panel class="h-full flex flex-col">
                <PanelHeader class="flex items-center justify-between min-h-10">
                    <Heading>{{ __('Schema JSON-LD output') }}</Heading>
                    <div class="flex gap-2">
                        <CommandPaletteItem
                            category="Actions"
                            :text="[__('Warm'), __('Schema JSON-LD output')]"
                            icon="fire-flame-burn-hot"
                            :action="warmCache"
                        >
                            <Button :text="__('Warm')" size="sm" @click="warmCache" />
                        </CommandPaletteItem>
                        <CommandPaletteItem
                            category="Actions"
                            :text="[__('Clear'), __('Schema JSON-LD output')]"
                            icon="live-preview"
                            :action="clearCache"
                        >
                            <Button :text="__('Clear')" size="sm" @click="clearCache" />
                        </CommandPaletteItem>
                    </div>
                </PanelHeader>
                <Card class="flex-1">
                    <Description>
                        {{
                            __(
                                'Caches the rendered script tag from the schema_ld_json Antlers tag per URL. Clear bumps a generation counter so new requests rebuild. Warm issues HTTP GETs to published entry URLs to repopulate the cache.'
                            )
                        }}
                    </Description>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <Badge :prepend="__('Enabled')">{{ enabled ? __('Yes') : __('No') }}</Badge>
                        <Badge :prepend="__('TTL (seconds)')">{{ ttlSeconds }}</Badge>
                        <Badge :prepend="__('Bust generation')">{{ bust }}</Badge>
                        <Badge v-if="lastBump?.at" :prepend="__('Last clear')">{{ formatCacheTimestamp(lastBump.at) }}</Badge>
                    </div>
                    <div class="mt-6 space-y-4">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ __('Clear via terminal') }}
                            </p>
                            <Input
                                :read-only="true"
                                copyable
                                :model-value="clearTerminalCommand"
                                class="dark"
                                input-class="font-mono text-sm"
                            />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ __('Warm via terminal') }}
                            </p>
                            <Input
                                :read-only="true"
                                copyable
                                :model-value="warmTerminalCommand"
                                class="dark"
                                input-class="font-mono text-sm"
                            />
                            <p
                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                v-html="
                                    __(
                                        'Optional: append <code>--site=handle</code> to warm one site only.'
                                    )
                                "
                            />
                        </div>
                    </div>
                </Card>
            </Panel>

            <Panel class="h-full flex flex-col">
                <PanelHeader class="flex items-center justify-between min-h-10">
                    <Heading>{{ __('Warm settings') }}</Heading>
                </PanelHeader>
                <Card class="flex-1">
                    <Description>
                        <span
                            v-html="
                                __(
                                    'Warm scans every Statamic site (or <code>--site</code>): homepage plus entry URLs from <code>global_entry_fields_by_collection</code> (dotted <code>global_set.field</code> refs per collection) and <code>global_taxonomy_entry_traversals</code>. See <code>json_ld_cache.warm</code> in <code>config/schema_templates.php</code>.'
                                )
                            "
                        />
                    </Description>
                    <div v-if="lastWarm?.at" class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                        <p class="font-medium">{{ __('Last warm') }}: {{ formatCacheTimestamp(lastWarm.at) }}</p>
                        <p>{{ __('URLs requested') }}: {{ lastWarm.requested }} — {{ __('Failures') }}: {{ lastWarm.failures }}</p>
                    </div>
                </Card>
            </Panel>
        </div>
    </div>
</template>
