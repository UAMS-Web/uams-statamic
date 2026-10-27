<?php

declare(strict_types=1);

use App\Services\Podcasts\HealthTalk\HealthTalkEntryFields;
use App\Services\Podcasts\HealthTalk\HealthTalkFeedItem;
use App\Services\Podcasts\HealthTalk\HealthTalkSourceException;
use App\Services\Podcasts\HealthTalk\HealthTalkSpeaker;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1803 — the feed-owned field builder, called directly against the real
 * `clinical_resources/audio` blueprint, so each conversion rule is pinned by an
 * input shaped for it rather than inferred from a whole sync run.
 *
 * @command php artisan test --compact tests/Feature/Podcasts/HealthTalkEntryFieldsTest.php
 */
const HEALTH_TALK_AUDIO_URL = 'https://support.doctorpodcasting.com/download/1/x/uams007.mp3';

beforeEach(function (): void {
    if (Collection::findByHandle('clinical_resources') === null) {
        ContentSubmodule::reportMissing('the clinical_resources collection');
    }
});

function healthTalkFields(): HealthTalkEntryFields
{
    return HealthTalkEntryFields::for('clinical_resources', 'audio');
}

/**
 * An in-memory entry carrying this data.
 *
 * @param  array<string, mixed>  $data
 */
function healthTalkFieldsEntry(array $data): StatamicEntry
{
    $entry = Entry::make();
    Assert::assertInstanceOf(StatamicEntry::class, $entry);
    $entry->collection('clinical_resources');
    $entry->data($data);

    return $entry;
}

function healthTalkFieldsItem(?string $audioUrl): HealthTalkFeedItem
{
    return new HealthTalkFeedItem('1', 'T', null, '', null, 7, $audioUrl);
}

it('reads stored speakers as name and slug pairs, skipping empty names and treating an empty slug as none', function (): void {
    $speakers = healthTalkFields()->storedSpeakers(healthTalkFieldsEntry([
        'source_speaker_names' => ['First', '', 'Second', 'Third'],
        // Keys as a list field could hold them after an edit: not starting at zero
        'source_speaker_slugs' => [5 => 'first-slug', 6 => '', 7 => '', 8 => 'third-slug'],
    ]));

    expect(array_map(static fn (HealthTalkSpeaker $speaker): array => [$speaker->name, $speaker->providerSlug], $speakers))
        ->toBe([['First', 'first-slug'], ['Second', null], ['Third', 'third-slug']]);
});

it('sets only the first feed audio row, leaving the rows before and after it alone', function (): void {
    $rows = healthTalkFields()->segments([
        'not a row',
        ['id' => 'a', 'type' => 'youtube', 'video_id' => 'v'],
        ['id' => 'b', 'type' => 'remote_audio', 'url' => 'https://support.doctorpodcasting.com/old.mp3'],
        ['id' => 'c', 'type' => 'remote_audio', 'url' => 'https://support.doctorpodcasting.com/second.mp3'],
    ], healthTalkFieldsItem(HEALTH_TALK_AUDIO_URL));

    expect($rows)->toBe([
        ['id' => 'a', 'type' => 'youtube', 'video_id' => 'v'],
        ['id' => 'b', 'type' => 'remote_audio', 'url' => HEALTH_TALK_AUDIO_URL],
        ['id' => 'c', 'type' => 'remote_audio', 'url' => 'https://support.doctorpodcasting.com/second.mp3'],
    ]);
});

it('adds the feed audio row first when there is none, and removes it when the feed has no audio', function (): void {
    $added = healthTalkFields()->segments([['id' => 'a', 'type' => 'youtube']], healthTalkFieldsItem(HEALTH_TALK_AUDIO_URL));
    expect(array_column($added, 'type'))->toBe(['remote_audio', 'youtube'])
        ->and($added[0]['url'] ?? null)->toBe(HEALTH_TALK_AUDIO_URL);

    $removed = healthTalkFields()->segments([['id' => 'a', 'type' => 'youtube'], ['id' => 'b', 'type' => 'remote_audio', 'url' => 'x']], healthTalkFieldsItem(null));
    expect($removed)->toBe([['id' => 'a', 'type' => 'youtube']]);
});

it('reads the stored audio URL from the feed row only', function (): void {
    $entry = healthTalkFieldsEntry(['segments' => [
        'not a row',
        ['type' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=x'],
        ['type' => 'remote_audio', 'url' => HEALTH_TALK_AUDIO_URL],
    ]]);

    expect(healthTalkFields()->storedAudioUrl($entry))->toBe(HEALTH_TALK_AUDIO_URL)
        ->and(healthTalkFields()->storedAudioUrl(healthTalkFieldsEntry(['segments' => [['type' => 'youtube']]])))->toBeNull();
});

it('keeps what follows a dropped image', function (): void {
    expect(healthTalkFields()->prosemirror('show_notes', '<p>Before<img src="https://x.test/a.png">After</p>'))
        ->toBe([['type' => 'paragraph', 'attrs' => ['textAlign' => 'left'], 'content' => [['type' => 'text', 'text' => 'Before'], ['type' => 'text', 'text' => 'After']]]]);
});

it('raises an h1 to h2 and keeps the other heading levels and attributes', function (string $html, int $level): void {
    expect(healthTalkFields()->prosemirror('show_notes', $html))
        ->toBe([['type' => 'heading', 'attrs' => ['level' => $level, 'textAlign' => 'left'], 'content' => [['type' => 'text', 'text' => 'H']]]]);
})->with([
    'h1' => ['<h1>H</h1>', 2],
    'h3' => ['<h3>H</h3>', 3],
    'h6' => ['<h6>H</h6>', 6],
]);

it('keeps an allowed mark beside a link that loses its mark, in either order', function (string $html): void {
    $nodes = healthTalkFields()->prosemirror('show_notes', $html);

    expect(json_encode($nodes))->toContain('"bold"')->not->toContain('tel:');
})->with([
    'link outside' => ['<p><a href="tel:5015550100"><strong>Call</strong></a></p>'],
    'link inside' => ['<p><strong><a href="tel:5015550100">Call</a></strong></p>'],
    'unknown mark first' => ['<p><u><strong>Call</strong></u></p>'],
]);

it('wraps text left at the top of a transcript into a paragraph', function (): void {
    expect(healthTalkFields()->prosemirror('episode_transcript', '<h2>Heading</h2>'))
        ->toBe([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Heading']]]]);
});

it('stores nothing for HTML with no text, and for a field it does not convert', function (): void {
    expect(healthTalkFields()->prosemirror('show_notes', '<p> <img src="https://x.test/a.png"> </p>'))->toBeNull()
        ->and(healthTalkFields()->prosemirror('title', '<p>Text</p>'))->toBeNull();
});

it('refuses a destination whose collection or blueprint does not exist', function (string $collection, string $blueprint): void {
    expect(fn () => HealthTalkEntryFields::for($collection, $blueprint))->toThrow(HealthTalkSourceException::class, "{$collection}/{$blueprint}");
})->with([
    'no collection' => ['no_such_collection', 'audio'],
    'no blueprint' => ['clinical_resources', 'no_such_blueprint'],
]);

it('pairs a stored name with its slug by position, whatever keys the names list carries', function (): void {
    $speakers = healthTalkFields()->storedSpeakers(healthTalkFieldsEntry([
        'source_speaker_names' => [2 => 'First', 3 => 'Second'],
        'source_speaker_slugs' => ['first-slug', 'second-slug'],
    ]));

    expect(array_map(static fn (HealthTalkSpeaker $speaker): ?string => $speaker->providerSlug, $speakers))->toBe(['first-slug', 'second-slug']);
});

it('keeps the rows after a feed audio row it removes', function (): void {
    expect(healthTalkFields()->segments([
        ['id' => 'b', 'type' => 'remote_audio', 'url' => 'x'],
        ['id' => 'a', 'type' => 'youtube'],
    ], healthTalkFieldsItem(null)))->toBe([['id' => 'a', 'type' => 'youtube']]);
});

it('adds a complete, enabled feed audio row', function (): void {
    $row = healthTalkFields()->segments([], healthTalkFieldsItem(HEALTH_TALK_AUDIO_URL))[0] ?? [];

    expect(array_keys($row))->toBe(['id', 'type', 'enabled', 'url'])
        ->and(is_string($row['id']) ? strlen($row['id']) : 0)->toBe(8)
        ->and($row['type'])->toBe('remote_audio')
        ->and($row['enabled'])->toBeTrue();
});

it('keeps the nodes after one it unwraps', function (): void {
    expect(healthTalkFields()->prosemirror('episode_transcript', '<p>One</p><h2>Two</h2><p>Three</p>'))->toBe([
        ['type' => 'paragraph', 'attrs' => ['textAlign' => 'left'], 'content' => [['type' => 'text', 'text' => 'One']]],
        // The unwrapped heading's text, wrapped in a new paragraph
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Two']]],
        ['type' => 'paragraph', 'attrs' => ['textAlign' => 'left'], 'content' => [['type' => 'text', 'text' => 'Three']]],
    ]);
});

it('keeps an allowed mark and a safe link exactly', function (string $href): void {
    expect(healthTalkFields()->prosemirror('show_notes', '<p><strong>B</strong> <a href="'.$href.'">L</a></p>'))->toBe([[
        'type' => 'paragraph',
        'attrs' => ['textAlign' => 'left'],
        'content' => [
            ['type' => 'text', 'text' => 'B', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ' '],
            ['type' => 'text', 'text' => 'L', 'marks' => [['type' => 'link', 'attrs' => ['href' => $href]]]],
        ],
    ]]);
})->with([
    'https' => ['https://uamshealth.com/'],
    'upper-case scheme' => ['HTTPS://uamshealth.com/'],
    'mailto' => ['mailto:a@example.test'],
    'padded' => ['  https://uamshealth.com/'],
]);

it('drops a mark the field cannot edit', function (): void {
    expect(healthTalkFields()->prosemirror('episode_transcript', '<p><s>Struck</s> <a href="https://uamshealth.com/">Linked</a></p>'))->toBe([
        ['type' => 'paragraph', 'attrs' => ['textAlign' => 'left'], 'content' => [['type' => 'text', 'text' => 'Struck'], ['type' => 'text', 'text' => ' '], ['type' => 'text', 'text' => 'Linked']]],
    ]);
});
