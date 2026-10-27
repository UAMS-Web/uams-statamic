<?php

declare(strict_types=1);

use Amp\Websocket\Client\WebsocketConnection;
use Amp\Websocket\WebsocketMessage;
use Pest\Browser\Playwright\Client;

/**
 * Guards `patches/pest-plugin-browser-client-bounded-requests.patch`.
 *
 * Remove this file when the patch is removed; see **`pest-plugin-browser`
 * bounded Playwright requests** in `patches/README.md` for the retirement
 * condition.
 *
 * These are behavioral tests of the patched vendor class rather than a source
 * string match: they drive `Client::execute()` against a stubbed websocket and
 * assert it terminates where the unpatched client loops forever.
 *
 * Because "the unpatched client loops forever" is literally the bug, the closed-
 * connection test opens with a cheap source precondition. Without it an unpatched
 * vendor tree would hang the suite instead of reporting a failure, which is a far
 * worse gate than a red test.
 *
 * There is no backing App\ PHP class, so there is no mutable PHP surface to
 * mutation-test.
 *
 * @command  php artisan test --compact tests/Unit/PestBrowserClientPatchTest.php
 */
it('reports a closed connection instead of looping on it', function (): void {
    $source = file_get_contents((string) new ReflectionClass(Client::class)->getFileName());

    // Fail fast rather than hang: an unpatched fetch() coerces the closed
    // connection's null to '' and spins at 100% CPU below, forever.
    expect($source)->toBeString()
        ->and($source)->toContain('closed the connection unexpectedly');

    $connection = $this->createStub(WebsocketConnection::class);
    $connection->method('receive')->willReturn(null);

    $client = new Client;
    $client->setTimeout(100);

    new ReflectionProperty(Client::class, 'websocketConnection')->setValue($client, $connection);

    expect(fn (): array => iterator_to_array($client->execute('page@1', 'goto')))
        ->toThrow(RuntimeException::class, 'The Playwright server closed the connection unexpectedly.');
});

it('still returns the response matching the request id', function (): void {
    // Guard against the closed-connection check breaking the happy path.
    $connection = $this->createStub(WebsocketConnection::class);

    // Narrowed to an array rather than json_decode()'s mixed, so the offsets read
    // below stay type-checked instead of needing a PHPStan escape hatch.
    $sent = [];
    $connection->method('sendText')->willReturnCallback(function (string $payload) use (&$sent): void {
        $decoded = json_decode($payload, true);
        $sent = is_array($decoded) ? $decoded : [];
    });

    // Must be a by-reference closure, not an arrow function: an arrow function
    // captures $sent by value at definition time, when it is still empty.
    $connection->method('receive')->willReturnCallback(function () use (&$sent): WebsocketMessage {
        return WebsocketMessage::fromText(
            (string) json_encode(['id' => $sent['id'], 'result' => ['value' => 'pong']])
        );
    });

    $client = new Client;

    new ReflectionProperty(Client::class, 'websocketConnection')->setValue($client, $connection);

    $messages = iterator_to_array($client->execute('page@1', 'goto'));

    $result = is_array($messages[0]['result'] ?? null) ? $messages[0]['result'] : [];

    expect($messages)->toHaveCount(1)
        ->and($result['value'])->toBe('pong');
});

it('sends the timeout as metadata so a 1.62.0 server enforces it', function (): void {
    // Playwright reads the timeout from metadata since 1.62.0, where an absent
    // value means NO timeout. This half is inert on the pinned 1.61.1 and becomes
    // live on the next bump, so assert it now rather than discovering it then.
    $connection = $this->createStub(WebsocketConnection::class);

    $sent = [];
    $connection->method('sendText')->willReturnCallback(function (string $payload) use (&$sent): void {
        $decoded = json_decode($payload, true);
        $sent = is_array($decoded) ? $decoded : [];
    });

    // By-reference closure for the same reason as above.
    $connection->method('receive')->willReturnCallback(function () use (&$sent): WebsocketMessage {
        return WebsocketMessage::fromText((string) json_encode(['id' => $sent['id']]));
    });

    $client = new Client;
    $client->setTimeout(100);

    new ReflectionProperty(Client::class, 'websocketConnection')->setValue($client, $connection);

    iterator_to_array($client->execute('page@1', 'click'));

    $metadata = is_array($sent['metadata'] ?? null) ? $sent['metadata'] : [];
    $params = is_array($sent['params'] ?? null) ? $sent['params'] : [];

    expect($metadata['timeout'])->toBe(100)
        ->and($params['timeout'])->toBe(100);
});

it('does not carry the upstream wall-clock request deadline', function (): void {
    // The rest of upstream PR UAMS-Web/uams-statamic-archive#241 — a TimeoutCancellation plus an elapsed-time
    // deadline — is deliberately NOT ported: this repo's CP smokes share one Amp
    // event loop between the browser, the in-process HTTP server and the test, so
    // a busy server starves response handling and the deadline fires with nothing
    // wrong. Measured on CpGpsFieldtypeSmokeTest: 102.6s pass without it, 144.3s
    // fail with it. Re-porting it would turn the browser suite red, so pin the
    // omission rather than leaving it to be "helpfully" restored later.
    $source = file_get_contents((string) new ReflectionClass(Client::class)->getFileName());

    expect($source)->toBeString()
        ->and($source)->not->toContain('new TimeoutCancellation(')
        ->and($source)->not->toContain('requestGraceSeconds');
});
