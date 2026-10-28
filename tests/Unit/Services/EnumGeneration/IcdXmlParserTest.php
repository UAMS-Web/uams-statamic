<?php

declare(strict_types=1);

use App\Services\EnumGeneration\InternationalCodeDesignator\IcdXmlParser;

/**
 * Behavior of IcdXmlParser
 * (app/Services/EnumGeneration/InternationalCodeDesignator/IcdXmlParser.php): parse an
 * ICD CodeList XML document into id/name/description records (missing description ->
 * ''), normalize whitespace in name/description, return null for invalid XML without
 * raising a warning or leaving libxml's error mode changed, and fetch the list over HTTP
 * as XML from a local server.
 *
 * Mutation: 25 of 25 killed. Two equivalents are annotated in the class: the timeout
 * one second either side.
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/IcdXmlParserTest.php
 */
it('parses ICD CodeList XML into records, or null when invalid', function (string $xml, ?array $expected) {
    expect(IcdXmlParser::parseXml($xml))->toBe($expected);
})->with([
    'valid XML yields id/name/description records' => [
        <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <CodeList xmlns="urn:fdc:difi.no:2017:vefa:structure:CodeList-1">
        	<Code>
        		<Id>0002</Id>
        		<Name>SIRENE</Name>
        		<Description>French trade register.</Description>
        	</Code>
        	<Code>
        		<Id>0088</Id>
        		<Name>EAN Location Code</Name>
        		<Description>EAN location code.</Description>
        	</Code>
        	<Code>
        		<Id>0096</Id>
        		<Name>DUNS Number</Name>
        	</Code>
        </CodeList>
        XML,
        [
            ['id' => '0002', 'name' => 'SIRENE', 'description' => 'French trade register.'],
            ['id' => '0088', 'name' => 'EAN Location Code', 'description' => 'EAN location code.'],
            ['id' => '0096', 'name' => 'DUNS Number', 'description' => ''],
        ],
    ],
    'invalid XML returns null' => ['<not valid xml', null],
    'a Code without an Id is skipped, not breaking the loop' => [
        <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <CodeList xmlns="urn:fdc:difi.no:2017:vefa:structure:CodeList-1">
        	<Code><Name>No identifier</Name></Code>
        	<Code><Id>0001</Id><Name>Valid</Name></Code>
        </CodeList>
        XML,
        [
            ['id' => '0001', 'name' => 'Valid', 'description' => ''],
        ],
    ],
    'whitespace in name/description is normalized' => [
        <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <CodeList xmlns="urn:fdc:difi.no:2017:vefa:structure:CodeList-1">
        	<Code>
        		<Id>0001</Id>
        		<Name>  Line one
        		line two  </Name>
        		<Description>  Notes here.  </Description>
        	</Code>
        </CodeList>
        XML,
        [
            ['id' => '0001', 'name' => 'Line one line two', 'description' => 'Notes here.'],
        ],
    ],
]);

it('parses invalid XML without raising a warning, and restores the libxml error mode it found', function (bool $mode) {
    $previous = libxml_use_internal_errors($mode);
    $warnings = [];
    set_error_handler(function (int $errno, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    try {
        $records = IcdXmlParser::parseXml('<not valid xml');
        $modeAfter = libxml_use_internal_errors(null);
    } finally {
        restore_error_handler();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    expect($records)->toBeNull()
        ->and($warnings)->toBe([])
        ->and($modeAfter)->toBe($mode);
})->with([
    'found off' => [false],
    'found on' => [true],
]);

/**
 * Describe why proc_open() could not start the server: the warning it raised, or
 * a note that it raised none, and the size of the environment block it was given.
 * Windows refuses to start a process whose environment block is too large, so the
 * size is reported with the reason.
 *
 * @param  string|null  $warning  The message of the warning proc_open() raised, if any
 * @param  array<string, string>  $environment
 */
function icdXmlServerStartFailure(?string $warning, array $environment): string
{
    $bytes = 0;
    foreach ($environment as $name => $value) {
        $bytes += strlen($name) + strlen($value) + 2;
    }

    $reason = $warning ?? 'proc_open() raised no error';

    return sprintf(
        'Could not start the local web server: %s (environment: %d variables, %d bytes).',
        $reason,
        count($environment),
        $bytes
    );
}

/**
 * Serve one file from a throwaway directory with PHP's built-in web server, run
 * the callback against its base URL, and stop the server afterwards. The router
 * answers 406 to any request whose Accept header is not application/xml, so a
 * fetch that stops asking for XML gets nothing.
 *
 * fetchAndParse() reaches the network through cURL, so a real local server is the
 * only way to exercise its success path without changing the class.
 */
function withIcdXmlServer(string $fileName, string $body, Closure $callback): mixed
{
    // Ask the OS for a free port, then hand it to the server. Another process could
    // take it in the moment between, in which case the fetch fails and so does the
    // test; it cannot pass against the wrong listener, since nothing else serves
    // this file.
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    if ($probe === false) {
        throw new RuntimeException('Could not reserve a local port.');
    }
    $port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);

    $root = sys_get_temp_dir().'/icd-xml-'.bin2hex(random_bytes(6));
    mkdir($root);
    $process = null;
    $pipes = [];

    try {
        file_put_contents($root.'/'.$fileName, $body);
        file_put_contents(
            $root.'/router.php',
            '<?php if (($_SERVER["HTTP_ACCEPT"] ?? "") !== "application/xml") { http_response_code(406); return true; } return false;'
        );

        // The server gets this process's environment minus PHP_CLI_SERVER_WORKERS.
        // A booted application loads that from .env, and with it set the server
        // forks workers that proc_terminate() below does not reach. They outlive the
        // test and keep holding every lock descriptor they inherited, including the
        // content mirror's band lock, which test:seed-stache then cannot take. As a
        // single process the server is the child proc_terminate() signals.
        $environment = getenv();
        unset($environment['PHP_CLI_SERVER_WORKERS']);

        // Pipes rather than file descriptors: mutation testing replaces the file
        // stream wrapper, and a file descriptor spec then fails to open. The server
        // logs a line or two per request, far below a pipe's buffer.
        error_clear_last();
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $root, $root.'/router.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $environment
        );
        if (! is_resource($process)) {
            throw new RuntimeException(icdXmlServerStartFailure(error_get_last()['message'] ?? null, $environment));
        }

        // Wait up to five seconds for the server to accept connections. A refused
        // connection while it starts is expected, so its warning is swallowed here.
        $deadline = microtime(true) + 5;
        set_error_handler(fn (): bool => true);
        try {
            while (($socket = fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2)) === false) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('The local web server did not start.');
                }
                usleep(50_000);
            }
        } finally {
            restore_error_handler();
        }
        fclose($socket);

        return $callback('http://127.0.0.1:'.$port);
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
        foreach ([$fileName, 'router.php'] as $file) {
            if (is_file($root.'/'.$file)) {
                unlink($root.'/'.$file);
            }
        }
        rmdir($root);
    }
}

it('fetches the code list over HTTP and parses it', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <CodeList xmlns="urn:fdc:difi.no:2017:vefa:structure:CodeList-1">
    	<Code><Id>0060</Id><Name>DUNS</Name><Description>Data Universal Numbering System.</Description></Code>
    </CodeList>
    XML;

    $records = withIcdXmlServer('icd.xml', $xml, fn (string $base): ?array => IcdXmlParser::fetchAndParse($base.'/icd.xml'));

    expect($records)->toBe([
        ['id' => '0060', 'name' => 'DUNS', 'description' => 'Data Universal Numbering System.'],
    ]);
});

it('returns null when the code list cannot be fetched', function () {
    $records = withIcdXmlServer('icd.xml', '<CodeList/>', fn (string $base): ?array => IcdXmlParser::fetchAndParse($base.'/missing.xml'));

    expect($records)->toBeNull();
});

it('names the reason and the environment size when the server cannot start', function (?string $warning, string $expected) {
    expect(icdXmlServerStartFailure($warning, ['AB' => 'cde', 'F' => '']))->toBe($expected);
})->with([
    'a warning was raised' => [
        'proc_open(): CreateProcess failed, error code: 206',
        'Could not start the local web server: proc_open(): CreateProcess failed, error code: 206 (environment: 2 variables, 10 bytes).',
    ],
    'no warning was raised' => [
        null,
        'Could not start the local web server: proc_open() raised no error (environment: 2 variables, 10 bytes).',
    ],
]);

it('points the default fetch at the OpenPEPPOL ICD code list', function () {
    expect(IcdXmlParser::DEFAULT_ICD_XML_URL)
        ->toBe('https://raw.githubusercontent.com/OpenPEPPOL/peppol-bis-invoice-3/refs/heads/master/structure/codelist/icd.xml');
});

it('collapses text to an empty string when PCRE fails on it', function () {
    // The (string) cast turns preg_replace()'s null on a PCRE error into ''. With
    // the JIT off and a backtrack limit of 1, collapsing whitespace fails on any
    // text that contains some, while text with none passes through. Turning the
    // JIT off at runtime does not reach a pattern an earlier test in this file
    // already compiled with it, so this runs only where PHP starts with the JIT
    // off (Herd on macOS does).
    $jit = ini_get('pcre.jit');
    $limit = ini_get('pcre.backtrack_limit');
    ini_set('pcre.jit', '0');
    ini_set('pcre.backtrack_limit', '1');

    try {
        $records = IcdXmlParser::parseXml(<<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <CodeList xmlns="urn:fdc:difi.no:2017:vefa:structure:CodeList-1">
        	<Code><Id>0060</Id><Name>Data  Universal</Name><Description>DUNS</Description></Code>
        </CodeList>
        XML);
    } finally {
        ini_set('pcre.jit', (string) $jit);
        ini_set('pcre.backtrack_limit', (string) $limit);
    }

    expect($records)->toBe([
        ['id' => '0060', 'name' => '', 'description' => 'DUNS'],
    ]);
})->skip(fn (): bool => ini_get('pcre.jit') === '1', 'needs PHP started with pcre.jit=0');
