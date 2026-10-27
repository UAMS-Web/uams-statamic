<?php

declare(strict_types=1);

use App\Events\TriggerActivated;
use App\Listeners\HandlePasteTableDataTrigger;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Facades\User;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withoutMiddleware;

uses(TestCase::class);

/**
 * Coverage for the paste-to-table trigger (UAMS-Web/uams-statamic-archive#226).
 *
 * {@see HandlePasteTableDataTrigger} parses the `table_data_paste` textarea into
 * the `table_data` value when the `table_data_paste_trigger` fires, ignoring
 * other triggers. The activate route returns the first non-empty listener
 * response, so a paste trigger surfaces the parsed table even though the
 * spreadsheet-importer listener runs first.
 *
 * Mutation: the listener guard logic is pinned below; the route's first-non-empty
 * selection lives in a routes/web.php closure (outside the analyzed app/ path),
 * verified here end-to-end.
 *
 * @param  array<string, mixed>  $formValues
 * @return array<string, mixed>
 *
 * @command  php artisan test --compact tests/Feature/Tables/HandlePasteTableDataTriggerTest.php
 */
function handlePasteTrigger(string $handle, array $formValues): array
{
    return app(HandlePasteTableDataTrigger::class)->handle(
        new TriggerActivated(null, $handle, $formValues)
    );
}

// ---
// Listener guards
// ---

it('ignores a trigger it does not own', function (): void {
    expect(handlePasteTrigger('table_data_importer_trigger', ['table_data_paste' => "a,b\n1,2"]))
        ->toBe([]);
});

it('returns no change when the paste field is missing or blank', function (mixed $value): void {
    expect(handlePasteTrigger('table_data_paste_trigger', ['table_data_paste' => $value]))
        ->toBe([]);
})->with([
    'missing' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
]);

it('returns no change when the paste field is absent entirely', function (): void {
    expect(handlePasteTrigger('table_data_paste_trigger', []))->toBe([]);
});

it('returns no change when the paste parses to no rows', function (): void {
    // A non-empty paste that yields no table (an empty table element)
    expect(handlePasteTrigger('table_data_paste_trigger', ['table_data_paste' => '<table></table>']))
        ->toBe([]);
});

// ---
// Listener parsing
// ---

it('returns parsed table_data for a TSV paste', function (): void {
    expect(handlePasteTrigger('table_data_paste_trigger', ['table_data_paste' => "a\tb\n1\t2"]))
        ->toBe([
            'table_data' => [
                ['cells' => ['a', 'b']],
                ['cells' => ['1', '2']],
            ],
        ]);
});

it('returns sanitized table_data for an HTML paste', function (): void {
    $html = '<table><tr><td><a href="https://uams.edu">L</a></td><td>x<script>alert(1)</script></td></tr></table>';

    expect(handlePasteTrigger('table_data_paste_trigger', ['table_data_paste' => $html]))
        ->toBe([
            'table_data' => [
                ['cells' => ['<a href="https://uams.edu">L</a>', 'x']],
            ],
        ]);
});

// ---
// Activate route integration (first non-empty response)
// ---

/** @var FileStatamicUser|null $pasteRouteUser */
$pasteRouteUser = null;

beforeEach(function (): void {
    withoutMiddleware(PreventRequestForgery::class);
});

afterEach(function () use (&$pasteRouteUser): void {
    if ($pasteRouteUser !== null) {
        $pasteRouteUser->delete();
        $pasteRouteUser = null;
    }
});

it('surfaces the parsed paste through the activate route for a cp user', function () use (&$pasteRouteUser): void {
    $user = User::make();

    /** @var FileStatamicUser $user */
    $user->id((string) Str::uuid());
    $user->email('paste-trigger-route-test@example.test');
    $user->password('password');
    // A super user holds the `activate field triggers` permission the route now
    // requires (UAMS-Web/uams-statamic-archive#863); the dedicated auth gating is asserted in
    // tests/Feature/TriggerActivateRouteTest.php.
    $user->makeSuper();
    $user->save();

    $pasteRouteUser = $user;

    actingAs($user);

    $response = postJson('/!/trigger/activate', [
        'trigger_handle' => 'table_data_paste_trigger',
        'form_values' => ['table_data_paste' => "a,b\n1,2"],
    ]);

    $response->assertOk();
    $response->assertJsonPath('updated_values.table_data', [
        ['cells' => ['a', 'b']],
        ['cells' => ['1', '2']],
    ]);
});

it('does not let a guest activate the paste trigger', function (): void {
    postJson('/!/trigger/activate', [
        'trigger_handle' => 'table_data_paste_trigger',
        'form_values' => ['table_data_paste' => "a,b\n1,2"],
    ])->assertUnauthorized();
});
