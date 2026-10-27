<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\YAML;
use Tests\TestCase;

/**
 * Proves no stored form still sends the placeholder `message` UAMS-Web/uams-statamic-archive#3082 wrote (UAMS-Web/uams-statamic-archive#3149).
 *
 * Once a row's `message` became the notification body, a row left holding
 * `A new submission was received for this form.` would send that one sentence and drop every
 * submitted field. The forms repository replaced each placeholder with `{{ all_fields }}`; this
 * reads every stored form so a placeholder cannot come back unnoticed.
 *
 * @command  php artisan test --compact tests/Feature/Forms/StoredFormEmailMessagesTest.php
 */
uses(TestCase::class);

/**
 * Collect the text of every text node in a Bard document, depth first.
 *
 * @param  mixed  $node  A Bard document, node, or node list.
 * @return list<string>
 */
function storedFormEmailMessageTexts(mixed $node): array
{
    // A string message is its own text
    if (is_string($node)) {
        return [$node];
    }

    if (! is_array($node)) {
        return [];
    }

    // Take this node's own text, then every child's
    $texts = isset($node['text']) && is_string($node['text']) ? [$node['text']] : [];

    foreach ($node as $child) {
        if (is_array($child)) {
            $texts = [...$texts, ...storedFormEmailMessageTexts($child)];
        }
    }

    return $texts;
}

it('stores no email row with the placeholder message', function (): void {
    $rows = 0;
    $placeholders = [];

    // Name every email row whose message still holds the placeholder sentence
    foreach (File::glob(resource_path('forms/*.yaml')) as $path) {
        if (! is_string($path)) {
            continue;
        }

        $form = YAML::parse((string) file_get_contents($path));
        $emails = is_array($form['email'] ?? null) ? $form['email'] : [];

        foreach ($emails as $index => $row) {
            $rows++;
            $text = implode(' ', storedFormEmailMessageTexts(is_array($row) ? ($row['message'] ?? null) : null));

            if (str_contains($text, 'A new submission was received for this form.')) {
                $placeholders[] = basename($path).' email row '.$index;
            }
        }
    }

    expect($placeholders)->toBe([]);

    // The forms submodule is present and carries email rows, so the loop above checked something
    expect($rows)->toBeGreaterThan(0);
});
