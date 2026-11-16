<?php

declare(strict_types=1);

use App\Support\Redirects\MigrationRedirect;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Tests\TestCase;
use UAMSWeb\Aether\Redirects\Support\MarketingRedirect;

/**
 * The HTTP Response Code options in both redirect blueprints are strings, the
 * same type every writer stores (#326).
 *
 * The Control Panel's radio selects an option only when its key is identical to
 * the stored value. With integer keys and `'301'` stored, an imported entry and a
 * new entry's default both showed no code selected.
 * These cases read the options the way the radio receives them, from the
 * fieldtype's preload, so an unquoted key in either file fails here.
 * `tests/Browser/CpRedirectResponseCodeTest.php` shows the radio itself.
 *
 * @command  php artisan test --compact tests/Feature/Redirects/RedirectResponseOptionKeysTest.php
 */
uses(TestCase::class);

/**
 * The `response` field a blueprint declares, built as Statamic builds it.
 *
 * Each blueprint is the one Statamic serves: the `redirects` global's comes from
 * the `uams-web/aether-redirects` add-on (#398) and `redirect_domain`'s from this
 * site. The global nests its field inside a replicator set, so the blueprint is
 * searched rather than read at a fixed path.
 *
 * @param  string  $handle  The blueprint as Statamic finds it, such as `globals.redirects`.
 */
function redirectResponseField(string $handle): Field
{
    $find = function (mixed $node) use (&$find): ?array {
        if (! \is_array($node)) {
            return null;
        }
        if (($node['handle'] ?? null) === 'response' && \is_array($node['field'] ?? null)) {
            return $node['field'];
        }
        foreach ($node as $child) {
            $found = $find($child);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    };

    $blueprint = Blueprint::find($handle);
    expect($blueprint)->toBeInstanceOf(StatamicBlueprint::class);

    /** @var StatamicBlueprint $blueprint */
    $config = $find($blueprint->contents());
    expect($config)->toBeArray();

    /** @var array<string, mixed> $config */
    return new Field('response', $config);
}

/**
 * The option values the radio receives for a blueprint's `response` field.
 *
 * @param  string  $handle  The blueprint as Statamic finds it, such as `globals.redirects`.
 * @return list<mixed>
 */
function redirectResponseOptionValues(string $handle): array
{
    $fieldtype = redirectResponseField($handle)->fieldtype();
    expect($fieldtype)->toBeInstanceOf(Fieldtype::class);
    /** @var Fieldtype $fieldtype */
    /** @var array{options: list<array{value: mixed, label: mixed}>} $preload */
    $preload = $fieldtype->preload();

    return array_column($preload['options'], 'value');
}

/**
 * Every response code a writer stores.
 *
 * `PeopleMergeService` and `RedirectDomainRules` write the literal `'301'`,
 * which the first entry here stands for.
 *
 * @return list<mixed>
 */
function redirectResponseWrittenCodes(): array
{
    return [
        '301',
        MigrationRedirect::moved('/old/', '/new/')->toRow('row')['response'],
        MigrationRedirect::gone('/old/')->toRow('row')['response'],
        MarketingRedirect::fromInput(['source' => '/old/', 'destination' => '/new/'])->toRow('row')['response'],
    ];
}

it('offers each response code as a string, the type the writers store', function (string $handle) {
    $options = redirectResponseOptionValues($handle);
    expect($options)->not->toBeEmpty();

    $nonStrings = array_values(array_filter($options, fn (mixed $value): bool => ! \is_string($value)));
    expect($nonStrings)->toBe([]);

    // The default must be one of the options exactly, or a new entry opens with none selected
    $default = redirectResponseField($handle)->defaultValue();
    expect(\in_array($default, $options, true))->toBeTrue();
})->with([
    'redirect_domains' => ['collections.redirect_domains.redirect_domain'],
    'the redirects global' => ['globals.redirects'],
]);

it('offers every code a writer stores in the blueprint that serves it', function () {
    $written = redirectResponseWrittenCodes();
    expect($written)->each->toBeString();

    // Migration and marketing redirects are rows in the redirects global, which also offers 410
    $globalOptions = redirectResponseOptionValues('globals.redirects');
    $missing = array_values(array_filter($written, fn (mixed $code): bool => ! \in_array($code, $globalOptions, true)));
    expect($missing)->toBe([]);

    expect(\in_array('301', redirectResponseOptionValues('collections.redirect_domains.redirect_domain'), true))->toBeTrue();
});
