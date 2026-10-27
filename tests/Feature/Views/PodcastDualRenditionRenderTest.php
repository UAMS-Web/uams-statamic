<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * #1808 — a podcast episode is ONE entry whose `segments` replicator may carry a
 * video rendition beside its audio, per UAMS-Web/uams-statamic-archive#1807's decision. The audio blueprints
 * therefore register the whole `new_video_segment_group` (`youtube`, `vimeo`,
 * `box`), and the templates choose the player PER ROW from the set `type` rather
 * than per template: a template that picked audio for the whole entry would feed
 * a YouTube row into the audio player.
 *
 * Audio sets nest their fields under `file` and video sets expose them directly,
 * so the rows also differ in where `title` comes from. The mixed-order cases
 * below exist to catch a value assigned on one row leaking into the next.
 *
 * Hermetic Antlers renders — no content, no network.
 *
 * @command php artisan test --compact tests/Feature/Views/PodcastDualRenditionRenderTest.php
 */

/**
 * An audio segment row, shaped as the `local_file` set stores it.
 *
 * @return array<string, mixed>
 */
function podcastAudioRow(string $title): array
{
    return [
        'type' => 'local_file',
        'file' => [
            'url' => '/assets/audio/episode.mp3',
            'title' => $title,
            'mime_type' => 'audio/mpeg',
            'extension' => 'mp3',
        ],
    ];
}

/**
 * A YouTube video segment row, shaped as the `youtube` set stores it.
 *
 * @return array<string, mixed>
 */
function podcastYoutubeRow(string $title): array
{
    return [
        'type' => 'youtube',
        'title' => $title,
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ];
}

/**
 * Render a view file from `resources/views` against the given scope.
 *
 * @param  array<string, mixed>  $data
 */
function renderPodcastView(string $view, array $data): string
{
    return (string) Antlers::parse(
        (string) file_get_contents(resource_path("views/{$view}.antlers.html")),
        $data,
        true,
    );
}

/**
 * The set handles registered under each group of a blueprint's `segments` field.
 *
 * @return array<string, list<string>>
 */
function podcastSegmentSets(string $blueprintPath): array
{
    $find = static function (mixed $node) use (&$find): ?array {
        if (is_array($node)) {
            if (($node['handle'] ?? null) === 'segments') {
                return $node;
            }
            foreach ($node as $child) {
                if (($found = $find($child)) !== null) {
                    return $found;
                }
            }
        }

        return null;
    };

    $segments = $find(Yaml::parseFile(resource_path("blueprints/collections/{$blueprintPath}.yaml")));
    $field = is_array($segments) ? ($segments['field'] ?? null) : null;
    $groupConfigs = is_array($field) ? ($field['sets'] ?? null) : null;
    expect($groupConfigs)->toBeArray();

    $groups = [];
    foreach (is_array($groupConfigs) ? $groupConfigs : [] as $group => $config) {
        $sets = is_array($config) ? ($config['sets'] ?? null) : null;
        if (is_string($group) && is_array($sets)) {
            $groups[$group] = array_values(array_filter(array_keys($sets), is_string(...)));
        }
    }

    return $groups;
}

/**
 * The segment titles a render emits, in order. `widont` joins a title's last two
 * words with `&nbsp;`, which is normalized back to a space here.
 *
 * @return list<string>
 */
function podcastSegmentTitles(string $html): array
{
    preg_match_all('#<h3[^>]*>(.*?)</h3>#s', $html, $matches);

    return array_map(
        static fn (string $title): string => trim(str_replace('&nbsp;', ' ', $title)),
        $matches[1],
    );
}

dataset('audio blueprints', ['posts/audio', 'clinical_resources/audio']);

it('registers the whole video segment group on the audio segments field', function (string $blueprint) {
    // One field, two groups: the video rendition is a set in the episode's own
    // replicator, never a second field or a second entry.
    // `clinical_resources/audio` also holds the vendor's remote MP3 for synced episodes (UAMS-Web/uams-statamic-archive#1803)
    expect(podcastSegmentSets($blueprint))->toBe([
        'new_audio_segment_group' => $blueprint === 'clinical_resources/audio' ? ['local_file', 'remote_audio'] : ['local_file'],
        'new_video_segment_group' => ['youtube', 'vimeo', 'box'],
    ]);
})->with('audio blueprints');

it('keeps the audio and video posts blueprints registering the same video sets', function () {
    // The decision rests on the video sets being the shared ones the video
    // blueprint already uses, so the two must not drift.
    $video = podcastSegmentSets('posts/video')['new_video_segment_group'];

    expect(podcastSegmentSets('posts/audio')['new_video_segment_group'])->toBe($video)
        ->and(podcastSegmentSets('clinical_resources/audio')['new_video_segment_group'])->toBe($video);
});

it('renders an audio-only episode with the audio player and no video affordance', function () {
    $out = renderPodcastView('posts/audio', ['segments' => [podcastAudioRow('Episode 114')]]);

    expect($out)
        ->toContain('Episode 114')
        ->toContain('<audio')
        ->toContain('entry-segment-audio')
        ->not->toContain('entry-segment-video')
        ->not->toContain('<iframe')
        ->not->toContain('youtube');
});

it('renders both renditions of a dual-rendition episode, each with its own player', function () {
    $out = renderPodcastView('posts/audio', ['segments' => [
        podcastAudioRow('Audio rendition'),
        podcastYoutubeRow('Video rendition'),
    ]]);

    expect(substr_count($out, 'entry-segment-audio'))->toBe(1)
        ->and(substr_count($out, 'entry-segment-video'))->toBe(1)
        ->and(substr_count($out, '<audio'))->toBe(1);

    expect($out)->toContain('youtube')
        ->and(podcastSegmentTitles($out))->toBe(['Audio rendition', 'Video rendition']);
});

it('does not leak a row title into the next row, in either order', function (array $rows, array $titles) {
    $out = renderPodcastView('posts/audio', ['segments' => $rows]);

    expect(podcastSegmentTitles($out))->toBe($titles);
})->with([
    'audio then video' => [[podcastAudioRow('First audio'), podcastYoutubeRow('Second video')], ['First audio', 'Second video']],
    'video then audio' => [[podcastYoutubeRow('First video'), podcastAudioRow('Second audio')], ['First video', 'Second audio']],
]);

it('dispatches a clinical resource audio episode per row, not per media_type', function () {
    $out = renderPodcastView('clinical_resources/_segments', [
        'media_type' => 'audio',
        'segments' => [podcastAudioRow('Clinical audio'), podcastYoutubeRow('Clinical video')],
    ]);

    expect(substr_count($out, 'entry-segment-audio'))->toBe(1)
        ->and(substr_count($out, 'entry-segment-video'))->toBe(1);

    expect(podcastSegmentTitles($out))->toBe(['Clinical audio', 'Clinical video']);
});

it('still honors media_type for a row that carries no set type', function (string $mediaType, array $row, string $expected, string $absent) {
    // Pre-existing callers pass rows with no set type and select the player by media_type.
    // The audio arm is the one that discriminates: a check reading `type` alone
    // sends a no-type row down the video path, which the video arm cannot see.
    $out = renderPodcastView('clinical_resources/_segments', [
        'media_type' => $mediaType,
        'segments' => [$row],
    ]);

    expect($out)->toContain($expected)->not->toContain($absent);
})->with([
    'audio' => ['audio', array_diff_key(podcastAudioRow('Untagged audio'), ['type' => true]), 'entry-segment-audio', 'entry-segment-video'],
    'video' => ['video', array_diff_key(podcastYoutubeRow('Untagged video'), ['type' => true]), 'entry-segment-video', 'entry-segment-audio'],
]);

it('adds no frame host: the video sets need only what the video posts already allow', function () {
    $frameSrc = config('security-headers.csp.directives.frame-src')
        ?? config('security-headers.csp.frame-src')
        ?? [];

    expect($frameSrc)
        ->toContain('https://www.youtube.com')
        ->toContain('https://player.vimeo.com');
})->skip(fn () => config('security-headers.csp.directives.frame-src') === null
    && config('security-headers.csp.frame-src') === null, 'frame-src not at an expected config path');
