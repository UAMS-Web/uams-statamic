<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Widgets\IncompletePublishedEntries;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs the Incomplete Published Entries audit out of band, so the dashboard widget
 * only ever reads its result (UAMS-Web/uams-statamic-archive#2506).
 *
 * The scan validates every published entry against its blueprint: about 90s in a
 * request-shaped run and about 163s against a cold Stache, both past the 60s
 * request limit. Scheduled in routes/console.php; can also be run on demand.
 *
 * It scans once per distinct widget configuration in `statamic.cp.widgets`, plus the
 * default (no options) configuration, so the key each dashboard widget reads is one
 * this command wrote. A widget enabled only through a saved `widgets` preference
 * with options of its own reads a key this command does not write; it renders its
 * "not yet computed" state rather than scanning, which is the safe direction.
 */
#[Description('Run the Incomplete Published Entries audit and store its result for the dashboard widget')]
#[Signature('cp:scan-incomplete-published-entries')]
final class ScanIncompletePublishedEntries extends Command
{
    /**
     * The widget handle as registered in the Control Panel.
     */
    private const string WIDGET_HANDLE = 'incomplete_published_entries';

    public function handle(): int
    {
        // The cold-Stache scan runs past a minute; never let a php.ini limit cut it short
        set_time_limit(0);

        foreach ($this->widgetConfigs() as $config) {
            // Build the widget exactly as the dashboard would, then run and store its scan
            $widget = new IncompletePublishedEntries;
            $widget->setConfig($config);

            $payload = $widget->refresh();

            $this->info(sprintf(
                'Scanned %d of %d published entries; %d failing. Stored under %s.',
                $payload['scan']['scanned'],
                $payload['scan']['total'],
                \count($payload['rows']),
                $widget->cacheKey(),
            ));
        }

        return self::SUCCESS;
    }

    /**
     * The distinct widget configurations to scan: the default, plus every
     * array-form entry for this widget in `statamic.cp.widgets`.
     *
     * @return list<array<string, mixed>>
     */
    private function widgetConfigs(): array
    {
        // Always cover the default configuration, which a bare string entry resolves to
        $configs = [[]];

        $widgets = config('statamic.cp.widgets');

        foreach (\is_array($widgets) ? $widgets : [] as $widget) {
            // A string entry is the default configuration, already covered
            if (! \is_array($widget)) {
                continue;
            }

            if (($widget['type'] ?? null) !== self::WIDGET_HANDLE) {
                continue;
            }

            // The string-keyed options other than the type are the widget's configuration
            $config = [];
            foreach ($widget as $option => $value) {
                if (\is_string($option) && $option !== 'type') {
                    $config[$option] = $value;
                }
            }

            if (! \in_array($config, $configs, true)) {
                $configs[] = $config;
            }
        }

        return $configs;
    }
}
