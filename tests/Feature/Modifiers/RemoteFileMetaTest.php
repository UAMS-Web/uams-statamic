<?php

declare(strict_types=1);

use App\Modifiers\RemoteFileMeta;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @covers \App\Modifiers\RemoteFileMeta
 *
 * What the modifier reads from a HEAD response. Every URL uses a public IP literal, so the
 * outbound guard allows it without a DNS lookup, and every request is answered by Http::fake.
 * Refusing internal hosts is tests/Feature/Rules/FeedUrlSsrfTest.php.
 *
 * @command  php artisan test --compact tests/Feature/Modifiers/RemoteFileMetaTest.php
 */
const REMOTE_FILE_META_URL = 'https://93.184.216.34/files/Annual-Report.PDF';

/**
 * Run the modifier against a faked HEAD response carrying the given headers.
 *
 * @param  array<string, string>  $headers
 * @return array<string, mixed>
 */
function remoteFileMetaWithHeaders(array $headers, int $status = 200, string $url = REMOTE_FILE_META_URL): array
{
    Http::fake(['*' => Http::response('', $status, $headers)]);

    return (new RemoteFileMeta)->index($url, [], []);
}

/**
 * The meta array with every key null except the ones given.
 *
 * @param  array<string, mixed>  $set
 * @return array<string, mixed>
 */
function remoteFileMetaExpected(array $set): array
{
    return array_merge([
        'duration' => null,
        'height' => null,
        'width' => null,
        'created_at' => null,
        'updated_at' => null,
        'size' => null,
        'mime_type' => null,
        'extension' => null,
        'url' => null,
        'title' => null,
        'description' => null,
    ], $set);
}

it('returns every key empty for a value that is not a URL', function (mixed $value): void {
    Http::fake();

    expect((new RemoteFileMeta)->index($value, [], []))->toBe(remoteFileMetaExpected([]));

    Http::assertNothingSent();
})->with([
    'null' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
    'a list' => [[REMOTE_FILE_META_URL]],
]);

it('reads every header it knows from a successful HEAD', function (): void {
    $meta = remoteFileMetaWithHeaders([
        'Content-Length' => '1536',
        'Content-Type' => 'application/pdf; charset=binary',
        'Content-Disposition' => "attachment; filename*=UTF-8''Annual%20Report%202024.pdf",
        'Content-Description' => '  The 2024 annual report  ',
        'Last-Modified' => 'Tue, 01 Oct 2024 12:00:00 GMT',
    ]);

    $iso = date('c', (int) strtotime('Tue, 01 Oct 2024 12:00:00 GMT'));

    expect($meta)->toBe(remoteFileMetaExpected([
        'created_at' => $iso,
        'updated_at' => $iso,
        'size' => '1.50 KB',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'url' => REMOTE_FILE_META_URL,
        'title' => 'Annual Report 2024.pdf',
        'description' => 'The 2024 annual report',
    ]));
});

it('sends one HEAD request, with short timeouts', function (): void {
    $sent = [];

    Http::fake(function (Request $request, array $options) use (&$sent) {
        $sent[] = [$request->method(), $options['timeout'] ?? null, $options['connect_timeout'] ?? null];

        return Http::response('', 200);
    });

    (new RemoteFileMeta)->index(REMOTE_FILE_META_URL, [], []);

    expect($sent)->toBe([['HEAD', 5, 3]]);
});

it('trims the URL it is given', function (): void {
    expect(remoteFileMetaWithHeaders([], url: '  '.REMOTE_FILE_META_URL."\n"))
        ->toBe(remoteFileMetaExpected(['url' => REMOTE_FILE_META_URL, 'extension' => 'pdf']));
});

it('leaves the extension empty when the URL path has none', function (string $url): void {
    expect(remoteFileMetaWithHeaders([], url: $url)['extension'])->toBeNull();
})->with([
    'no path' => ['https://93.184.216.34'],
    'a bare slash' => ['https://93.184.216.34/'],
    'a path with no extension' => ['https://93.184.216.34/files/report'],
]);

it('keeps only the URL and extension when the HEAD request fails', function (int $status): void {
    expect(remoteFileMetaWithHeaders(['Content-Length' => '1536', 'Content-Type' => 'application/pdf'], $status))
        ->toBe(remoteFileMetaExpected(['url' => REMOTE_FILE_META_URL, 'extension' => 'pdf']));
})->with([404, 500]);

it('keeps only the URL and extension when the request throws', function (): void {
    Http::fake(fn () => throw new ConnectionException('connection refused'));

    expect((new RemoteFileMeta)->index(REMOTE_FILE_META_URL, [], []))
        ->toBe(remoteFileMetaExpected(['url' => REMOTE_FILE_META_URL, 'extension' => 'pdf']));
});

it('follows up to five redirects, and gives up after that', function (int $redirects, ?string $size): void {
    $hops = 0;

    Http::fake(function () use (&$hops, $redirects) {
        if ($hops++ < $redirects) {
            return Http::response('', 302, ['Location' => 'https://93.184.216.34/files/hop-'.$hops.'.pdf']);
        }

        return Http::response('', 200, ['Content-Length' => '2048']);
    });

    expect((new RemoteFileMeta)->index(REMOTE_FILE_META_URL, [], [])['size'])->toBe($size);
})->with([
    'five redirects' => [5, '2.00 KB'],
    'six redirects' => [6, null],
]);

it('formats the content length in the largest whole unit', function (string $length, ?string $size): void {
    expect(remoteFileMetaWithHeaders(['Content-Length' => $length])['size'])->toBe($size);
})->with([
    'zero' => ['0', null],
    'not a number' => ['many', null],
    'one byte' => ['1', '1 B'],
    'bytes' => ['1023', '1023 B'],
    'one kilobyte' => ['1024', '1.00 KB'],
    'two decimals below ten' => ['1536', '1.50 KB'],
    'two decimals just below ten' => ['9728', '9.50 KB'],
    'one decimal at exactly ten' => ['10240', '10.0 KB'],
    'one decimal from ten' => ['15360', '15.0 KB'],
    'one decimal just below a hundred' => ['101888', '99.5 KB'],
    'no decimals at exactly a hundred' => ['102400', '100 KB'],
    'no decimals from a hundred' => ['158720', '155 KB'],
    'megabytes' => ['5242880', '5.00 MB'],
    'gigabytes' => ['3221225472', '3.00 GB'],
    'terabytes, the largest unit' => ['5629499534213120', '5,120 TB'],
]);

it('reads the title from each Content-Disposition form', function (string $disposition, ?string $title): void {
    expect(remoteFileMetaWithHeaders(['Content-Disposition' => $disposition])['title'])->toBe($title);
})->with([
    'encoded filename*' => ["attachment; filename*=UTF-8''Caf%C3%A9%20Menu.pdf", 'Café Menu.pdf'],
    'filename* without a charset' => ['attachment; FILENAME*=Plain%20Name.pdf', 'Plain Name.pdf'],
    'quoted filename with escapes' => ['attachment; filename="The \\"Best\\" Report.pdf"', 'The "Best" Report.pdf'],
    'bare filename' => ['attachment; filename=report.pdf; size=10', 'report.pdf'],
    'bare filename in upper case' => ['attachment; FILENAME=REPORT.PDF', 'REPORT.PDF'],
    'an empty quoted filename' => ['attachment; filename=""', null],
    'no filename' => ['inline', null],
]);

it('reads only the media type from Content-Type', function (string $type, string $mime): void {
    expect(remoteFileMetaWithHeaders(['Content-Type' => $type])['mime_type'])->toBe($mime);
})->with([
    'with parameters' => ['text/csv ; charset=utf-8; header=present', 'text/csv'],
    'bare' => ['image/png', 'image/png'],
]);

it('leaves a blank description empty', function (): void {
    expect(remoteFileMetaWithHeaders(['Content-Description' => '   '])['description'])->toBeNull();
});

it('leaves the dates empty when Last-Modified cannot be read', function (): void {
    $meta = remoteFileMetaWithHeaders(['Last-Modified' => 'not a date']);

    expect([$meta['created_at'], $meta['updated_at']])->toBe([null, null]);
});

it('leaves the extension empty for a URL it cannot parse', function (): void {
    // parse_url() returns false here; the extension check runs before the outbound guard,
    // which then refuses the URL, so nothing is sent.
    Http::fake();

    expect((new RemoteFileMeta)->index('http:///files/report.pdf', [], []))
        ->toBe(remoteFileMetaExpected(['url' => 'http:///files/report.pdf']));

    Http::assertNothingSent();
});
