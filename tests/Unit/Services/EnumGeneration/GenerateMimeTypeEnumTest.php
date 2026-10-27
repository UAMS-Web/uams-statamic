<?php

declare(strict_types=1);

use App\Services\EnumGeneration\MimeType\MimeTypeEnumGenerator;

/**
 * Behavior of the MimeTypeEnumGenerator service's IANA-parsing internals
 * (app/Services/EnumGeneration/MimeType/MimeTypeEnumGenerator.php), exercised via
 * direct service call: parseExtensionsFromIanaPage (extract file extensions from a
 * registration page), parseExtensionText (normalize an extension fragment), and
 * generateCaseId (MIME type -> enum case identifier).
 *
 * Mutation: ~66% over the parser methods (72 of 213 survive). This is
 * a faithful port of the original 177-row data providers, preserving their (extensive,
 * example-based) coverage exactly. The residual survivors are internal branches/helpers
 * of the large IANA-registration parser that example rows do not individually pin (plus
 * some equivalents). This is an ACCEPTED, documented gap: exhaustively killing them would
 * mean substantial new internal-method tests, out of scope for this build-time console
 * command. (Contrast the runtime modifiers in this migration, which are driven to 100%
 * of killable.)
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/GenerateMimeTypeEnumTest.php
 */
it('parses file extensions from an IANA registration page', function (string $content, array $expected) {
    $generator = new MimeTypeEnumGenerator;
    expect($generator->parseExtensionsFromIanaPage($content))->toBe($expected);
})->with([
    'basic extension' => [
        0 => 'File extension(s): abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'extension with single space before' => [
        0 => 'File extension(s):  abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'extension with multiple spaces before' => [
        0 => 'File extension(s):   abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'extension with space before colon' => [
        0 => 'File extension(s): abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'extension with multiple spaces before and after colon' => [
        0 => 'File extension(s)  :  abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'extension with many spaces before and after colon' => [
        0 => 'File extension(s)   :   abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'quoted extension' => [
        0 => 'File extension(s): "abc"',
        1 => [
            0 => 'abc',
        ],
    ],
    'extension with leading dot' => [
        0 => 'File extension(s): .abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'quoted extension with leading dot' => [
        0 => 'File extension(s): ".abc"',
        1 => [
            0 => 'abc',
        ],
    ],
    'wildcard extension' => [
        0 => 'File extension(s): *.abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'quoted wildcard extension' => [
        0 => 'File extension(s): "*.abc"',
        1 => [
            0 => 'abc',
        ],
    ],
    'uppercase extension' => [
        0 => 'File extension(s): ABC',
        1 => [
            0 => 'abc',
        ],
    ],
    'quoted uppercase extension' => [
        0 => 'File extension(s): "ABC"',
        1 => [
            0 => 'abc',
        ],
    ],
    'uppercase extension with leading dot' => [
        0 => 'File extension(s): .ABC',
        1 => [
            0 => 'abc',
        ],
    ],
    'quoted uppercase extension with leading dot' => [
        0 => 'File extension(s): ".ABC"',
        1 => [
            0 => 'abc',
        ],
    ],
    'uppercase wildcard extension' => [
        0 => 'File extension(s): *.ABC',
        1 => [
            0 => 'abc',
        ],
    ],
    'quoted uppercase wildcard extension' => [
        0 => 'File extension(s): "*.ABC"',
        1 => [
            0 => 'abc',
        ],
    ],
    'multiple extensions with leading dots' => [
        0 => 'File extension(s): .abc, .def, .ghi',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'multiple uppercase extensions with leading dots' => [
        0 => 'File extension(s): .ABC, .DEF, .GHI',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'multiple wildcard extensions' => [
        0 => 'File extension(s): *.abc, *.def, *.ghi',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'multiple uppercase wildcard extensions' => [
        0 => 'File extension(s): *.ABC, *.DEF, *.GHI',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'multiple lowercase extensions' => [
        0 => 'File extension(s): abc, def, ghi',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'multiple uppercase extensions' => [
        0 => 'File extension(s): ABC, DEF, GHI',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'multiple quoted extensions with leading dots' => [
        0 => 'File extension(s): ".abc", ".def", ".ghi"',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'multiple quoted uppercase extensions with leading dots' => [
        0 => 'File extension(s): ".ABC", ".DEF", ".GHI"',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'multiple quoted wildcard extensions' => [
        0 => 'File extension(s): "*.abc", "*.def", "*.ghi"',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'multiple quoted uppercase wildcard extensions' => [
        0 => 'File extension(s): "*.ABC", "*.DEF", "*.GHI"',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'multiple quoted extensions' => [
        0 => 'File extension(s): "abc", "def", "ghi"',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'multiple quoted uppercase extensions' => [
        0 => 'File extension(s): "ABC", "DEF", "GHI"',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'extension with dots' => [
        0 => 'File extension(s): 123.4',
        1 => [
            0 => '123.4',
        ],
    ],
    'extension with dots and leading dot' => [
        0 => 'File extension(s): .123.4',
        1 => [
            0 => '123.4',
        ],
    ],
    'extension with dots and wildcard' => [
        0 => 'File extension(s): *.123.4',
        1 => [
            0 => '123.4',
        ],
    ],
    'quoted extension with dots' => [
        0 => 'File extension(s): "123.4"',
        1 => [
            0 => '123.4',
        ],
    ],
    'quoted extension with dots and leading dot' => [
        0 => 'File extension(s): ".123.4"',
        1 => [
            0 => '123.4',
        ],
    ],
    'quoted extension with dots and wildcard' => [
        0 => 'File extension(s): "*.123.4"',
        1 => [
            0 => '123.4',
        ],
    ],
    'extension with or' => [
        0 => 'File extension(s): abc, def, ghi or jkl',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
            3 => 'jkl',
        ],
    ],
    'extension with or and serial comma' => [
        0 => 'File extension(s): abc, def, ghi, or jkl',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
            3 => 'jkl',
        ],
    ],
    'simple extension with or' => [
        0 => 'File extension(s): ghi or jkl',
        1 => [
            0 => 'ghi',
            1 => 'jkl',
        ],
    ],
    'extension with and' => [
        0 => 'File extension(s): abc, def, ghi and jkl',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
            3 => 'jkl',
        ],
    ],
    'extension with and and serial comma' => [
        0 => 'File extension(s): abc, def, ghi, and jkl',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
            3 => 'jkl',
        ],
    ],
    'simple extension with and' => [
        0 => 'File extension(s): ghi and jkl',
        1 => [
            0 => 'ghi',
            1 => 'jkl',
        ],
    ],
    'Not Applicable' => [
        0 => 'File extension(s): Not Applicable',
        1 => [
        ],
    ],
    'Not applicable' => [
        0 => 'File extension(s): Not applicable',
        1 => [
        ],
    ],
    'not applicable' => [
        0 => 'File extension(s): not applicable',
        1 => [
        ],
    ],
    'NOT APPLICABLE' => [
        0 => 'File extension(s): NOT APPLICABLE',
        1 => [
        ],
    ],
    'N/A' => [
        0 => 'File extension(s): N/A',
        1 => [
        ],
    ],
    'n/a' => [
        0 => 'File extension(s): n/a',
        1 => [
        ],
    ],
    'Not Available' => [
        0 => 'File extension(s): Not Available',
        1 => [
        ],
    ],
    'Not available' => [
        0 => 'File extension(s): Not available',
        1 => [
        ],
    ],
    'not available' => [
        0 => 'File extension(s): not available',
        1 => [
        ],
    ],
    'NOT AVAILABLE' => [
        0 => 'File extension(s): NOT AVAILABLE',
        1 => [
        ],
    ],
    'None' => [
        0 => 'File extension(s): None',
        1 => [
        ],
    ],
    'none' => [
        0 => 'File extension(s): none',
        1 => [
        ],
    ],
    'NONE' => [
        0 => 'File extension(s): NONE',
        1 => [
        ],
    ],
    'Unknown' => [
        0 => 'File extension(s): Unknown',
        1 => [
        ],
    ],
    'UNKNOWN' => [
        0 => 'File extension(s): UNKNOWN',
        1 => [
        ],
    ],
    'Undefined' => [
        0 => 'File extension(s): Undefined',
        1 => [
        ],
    ],
    'undefined' => [
        0 => 'File extension(s): undefined',
        1 => [
        ],
    ],
    'UNDEFINED' => [
        0 => 'File extension(s): UNDEFINED',
        1 => [
        ],
    ],
    'empty extension' => [
        0 => 'File extension(s): ',
        1 => [
        ],
    ],
    'whitespace only' => [
        0 => 'File extension(s):   ',
        1 => [
        ],
    ],
    'with parenthetical content' => [
        0 => 'File extension(s): .abc (for both EMF and EMF+ content)',
        1 => [
            0 => 'abc',
        ],
    ],
    'extension with parenthetical content' => [
        0 => 'File extension(s): abc (for both EMF and EMF+ content)',
        1 => [
            0 => 'abc',
        ],
    ],
    'wildcard extension with parenthetical content' => [
        0 => 'File extension(s): *.abc (for both EMF and EMF+ content)',
        1 => [
            0 => 'abc',
        ],
    ],
    'quoted extension with parenthetical content' => [
        0 => 'File extension(s): ".abc" (for both EMF and EMF+ content)',
        1 => [
            0 => 'abc',
        ],
    ],
    'quoted extension without dot with parenthetical content' => [
        0 => 'File extension(s): "abc" (for both EMF and EMF+ content)',
        1 => [
            0 => 'abc',
        ],
    ],
    'quoted wildcard extension with parenthetical content' => [
        0 => 'File extension(s): "*.abc" (for both EMF and EMF+ content)',
        1 => [
            0 => 'abc',
        ],
    ],
    'multiple extensions with subtype parenthetical content' => [
        0 => 'File extension(s): abc (for subtype abc), def (for subtype def)',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'multiple extensions with leading dots and subtype parenthetical content' => [
        0 => 'File extension(s): .abc (for subtype abc), .def (for subtype def)',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'multiple wildcard extensions with subtype parenthetical content' => [
        0 => 'File extension(s): *.abc (for subtype abc), *.def (for subtype def)',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'multiple quoted extensions with subtype parenthetical content' => [
        0 => 'File extension(s): "abc" (for subtype abc), "def" (for subtype def)',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'multiple quoted extensions with leading dots and subtype parenthetical content' => [
        0 => 'File extension(s): ".abc" (for subtype abc), ".def" (for subtype def)',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'multiple quoted wildcard extensions with subtype parenthetical content' => [
        0 => 'File extension(s): "*.abc" (for subtype abc), "*.def" (for subtype def)',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'non-standard structure without delimiters should be skipped' => [
        0 => 'File extension(s): abc (for subtype abc) def (for subtype def)',
        1 => [
        ],
    ],
    'mixed extension formats' => [
        0 => 'File extension(s): abc, .def, *.ghi, "jkl", ".mno", "*.pqr"',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
            3 => 'jkl',
            4 => 'mno',
            5 => 'pqr',
        ],
    ],
    'mixed extension formats with case variations' => [
        0 => 'File extension(s): abc, .def, *.ghi, "jkl", ".mno", "*.pqr", ABC, .DEF, *.GHI, "JKL", ".MNO", "*.PQR"',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
            3 => 'jkl',
            4 => 'mno',
            5 => 'pqr',
        ],
    ],
    'non-standard structure with quotes should be skipped' => [
        0 => 'File extension(s): ".dcm" is recommended for files saved to disk',
        1 => [
        ],
    ],
    'line number should not be captured' => [
        0 => '2. File extension(s): abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'line number with empty extension' => [
        0 => '2. File extension(s): ',
        1 => [
        ],
    ],
    'line number with N/A' => [
        0 => '3. File extension(s): N/A',
        1 => [
        ],
    ],
    'extension with newline after' => [
        0 => 'File extension(s): .abc

Person & email address to contact for further information:',
        1 => [
            0 => 'abc',
        ],
    ],
    'extension with newline in middle' => [
        0 => 'File extension(s): .abc
person',
        1 => [
            0 => 'abc',
        ],
    ],
    'extension with newline after colon should be skipped' => [
        0 => 'File extension(s):
ASN.1 SOAP messages are not required or expected to be stored as files.',
        1 => [
        ],
    ],
    'extension with double newline after colon should be skipped' => [
        0 => 'File extension(s):

ASN.1 SOAP messages are not required or expected to be stored as files.',
        1 => [
        ],
    ],
    'extension with space and newline after colon should be skipped' => [
        0 => 'File extension(s): 
ASN.1 SOAP messages are not required or expected to be stored as files.',
        1 => [
        ],
    ],
    'extension with space and double newline after colon should be skipped' => [
        0 => 'File extension(s): 

ASN.1 SOAP messages are not required or expected to be stored as files.',
        1 => [
        ],
    ],
    'extension with space before colon and newline should be skipped' => [
        0 => 'File extension(s) : 
ASN.1 SOAP messages are not required or expected to be stored as files.',
        1 => [
        ],
    ],
    'extension with space before colon and double newline should be skipped' => [
        0 => 'File extension(s) : 

ASN.1 SOAP messages are not required or expected to be stored as files.',
        1 => [
        ],
    ],
    'extension with trailing comma and newline' => [
        0 => 'File extension(s): .abc,
Macintosh File Type Code(s): TEXT.',
        1 => [
            0 => 'abc',
        ],
    ],
    'RFC description with no file extension should be skipped' => [
        0 => 'File extension(s): RFC 9569 uses the media type to refer to protocol messages; thus, it does not require a file extension.',
        1 => [
        ],
    ],
    'extension with colon before File extension should be skipped' => [
        0 => '1. Magic number(s) :  2. File extension(s) :  3. Macintosh file type',
        1 => [
        ],
    ],
    'extensions without spaces in series' => [
        0 => 'File extension(s): abc,def,ghi',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'extensions without spaces in series with non-series content after' => [
        0 => 'File extension(s): abc,def,ghi foo bar baz',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'extensions with spaces and non-series content after' => [
        0 => 'File extension(s): abc, def, ghi foo bar baz',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'extensions with or and non-series content after' => [
        0 => 'File extension(s): abc, def, or ghi foo bar baz',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'extensions with and and non-series content after' => [
        0 => 'File extension(s): abc, def, and ghi foo bar baz',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'extensions with or without serial comma and non-series content after' => [
        0 => 'File extension(s): abc, def or ghi foo bar baz',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'extensions with and without serial comma and non-series content after' => [
        0 => 'File extension(s): abc, def and ghi foo bar baz',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'extensions without spaces and hash symbol at end' => [
        0 => 'File extension(s): abc,def,ghi#',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'extensions with spaces and hash symbol at end' => [
        0 => 'File extension(s): abc, def, ghi#',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'extensions without spaces and hash symbol with non-series content after' => [
        0 => 'File extension(s): abc,def,ghi# foo bar baz',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'extensions with spaces and hash symbol with non-series content after' => [
        0 => 'File extension(s): abc, def, ghi# foo bar baz',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'extensions with hash symbol in middle and non-series content after' => [
        0 => 'File extension(s): abc, def#, ghi foo bar baz',
        1 => [
            0 => 'abc',
            1 => 'ghi',
        ],
    ],
    'extensions with hash symbol at beginning and non-series content after' => [
        0 => 'File extension(s): abc#, def, ghi foo bar baz',
        1 => [
            0 => 'def',
            1 => 'ghi',
        ],
    ],
    'descriptive text with quoted extension should be skipped' => [
        0 => 'File extension(s):  The extension ".abc" is suggested.',
        1 => [
        ],
    ],
    'descriptive text about file saving should be skipped' => [
        0 => 'File extension(s):  In the event this is saved to a file, the',
        1 => [
        ],
    ],
    'extensions with parenthetical status indicators' => [
        0 => 'File extension(s): .X_T (recommended), .xmt_txt (deprecated)',
        1 => [
            0 => 'x_t',
            1 => 'xmt_txt',
        ],
    ],
    'extensions with underscores and leading dots' => [
        0 => 'File extension(s): .X_T, .xmt_txt',
        1 => [
            0 => 'x_t',
            1 => 'xmt_txt',
        ],
    ],
    'descriptive text stating no specific extensions recommended should be skipped' => [
        0 => 'File extension(s): No specific file extensions are recommended for ',
        1 => [
        ],
    ],
    'quoted extensions with and and descriptive text' => [
        0 => 'File extension(s): \'abc\' and \'def\' are known to be used.',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'quoted extensions with serial comma and and descriptive text' => [
        0 => 'File extension(s): \'abc\', \'def\', and \'ghi\' are known to be used.',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'quoted extensions with and without serial comma and descriptive text' => [
        0 => 'File extension(s): \'abc\', \'def\' and \'ghi\' are known to be used.',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'quoted extensions with or and descriptive text' => [
        0 => 'File extension(s): \'abc\' or \'def\' are known to be used.',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'quoted extensions with serial comma and or and descriptive text' => [
        0 => 'File extension(s): \'abc\', \'def\', or \'ghi\' are known to be used.',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'quoted extensions with or without serial comma and descriptive text' => [
        0 => 'File extension(s): \'abc\', \'def\' or \'ghi\' are known to be used.',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'quoted extensions with commas and descriptive text' => [
        0 => 'File extension(s): \'abc\', \'def\', \'ghi\' are known to be used.',
        1 => [
            0 => 'abc',
            1 => 'def',
            2 => 'ghi',
        ],
    ],
    'descriptive text with generally quoted extension should be skipped' => [
        0 => 'File extension(s): Generally "abc", but documents describing',
        1 => [
        ],
    ],
    'descriptive text about files not requiring specific extension should be skipped' => [
        0 => 'File extension(s): Files do not require any specific "extension".',
        1 => [
        ],
    ],
    'descriptive text about file extension usage should be skipped' => [
        0 => 'File extension(s):  The file extension of "ics" is to be used to',
        1 => [
        ],
    ],
    'extension with dash only should be skipped' => [
        0 => 'File extension(s): -',
        1 => [
        ],
    ],
    'extension with dash only and newline should be skipped' => [
        0 => 'File extension(s): -\\n',
        1 => [
        ],
    ],
    'extension with any should be skipped' => [
        0 => 'File extension(s): any',
        1 => [
        ],
    ],
    'extension with numbered pattern should expand to all digits' => [
        0 => 'File extension(s): abc<n>',
        1 => [
            0 => 'abc0',
            1 => 'abc1',
            2 => 'abc2',
            3 => 'abc3',
            4 => 'abc4',
            5 => 'abc5',
            6 => 'abc6',
            7 => 'abc7',
            8 => 'abc8',
            9 => 'abc9',
        ],
    ],
    'extension with numbered pattern and leading dot should expand to all digits' => [
        0 => 'File extension(s): .abc<n>',
        1 => [
            0 => 'abc0',
            1 => 'abc1',
            2 => 'abc2',
            3 => 'abc3',
            4 => 'abc4',
            5 => 'abc5',
            6 => 'abc6',
            7 => 'abc7',
            8 => 'abc8',
            9 => 'abc9',
        ],
    ],
    'extension with numbered pattern and wildcard should expand to all digits' => [
        0 => 'File extension(s): *.abc<n>',
        1 => [
            0 => 'abc0',
            1 => 'abc1',
            2 => 'abc2',
            3 => 'abc3',
            4 => 'abc4',
            5 => 'abc5',
            6 => 'abc6',
            7 => 'abc7',
            8 => 'abc8',
            9 => 'abc9',
        ],
    ],
    'quoted extension with numbered pattern should expand to all digits' => [
        0 => 'File extension(s): "abc<n>"',
        1 => [
            0 => 'abc0',
            1 => 'abc1',
            2 => 'abc2',
            3 => 'abc3',
            4 => 'abc4',
            5 => 'abc5',
            6 => 'abc6',
            7 => 'abc7',
            8 => 'abc8',
            9 => 'abc9',
        ],
    ],
    'quoted extension with numbered pattern and leading dot should expand to all digits' => [
        0 => 'File extension(s): ".abc<n>"',
        1 => [
            0 => 'abc0',
            1 => 'abc1',
            2 => 'abc2',
            3 => 'abc3',
            4 => 'abc4',
            5 => 'abc5',
            6 => 'abc6',
            7 => 'abc7',
            8 => 'abc8',
            9 => 'abc9',
        ],
    ],
    'quoted extension with numbered pattern and wildcard should expand to all digits' => [
        0 => 'File extension(s): "*.abc<n>"',
        1 => [
            0 => 'abc0',
            1 => 'abc1',
            2 => 'abc2',
            3 => 'abc3',
            4 => 'abc4',
            5 => 'abc5',
            6 => 'abc6',
            7 => 'abc7',
            8 => 'abc8',
            9 => 'abc9',
        ],
    ],
    'extension with dash none should be skipped' => [
        0 => 'File extension(s): -none-',
        1 => [
        ],
    ],
    'reference to another registration should be skipped' => [
        0 => 'File extension(s): See registration of application/abc.',
        1 => [
        ],
    ],
    'descriptive text about commonly used extension should be skipped' => [
        0 => 'File extension(s): The extension ".abc" is commonly used.',
        1 => [
        ],
    ],
    'descriptive text about media type not proposing specific should be skipped' => [
        0 => 'File extension(s): This media type does not propose a specific',
        1 => [
        ],
    ],
    'descriptive text about not being stored in files should be skipped' => [
        0 => 'File extension(s): not expected to be stored in files',
        1 => [
        ],
    ],
    'extensions with semicolon and typo' => [
        0 => 'File extension(s): .hbci and .hbc; .kom, .upa, .pkd an .bpd for special purpose',
        1 => [
            0 => 'hbci',
            1 => 'hbc',
            2 => 'kom',
            3 => 'upa',
            4 => 'pkd',
        ],
    ],
    'dash with space before colon' => [
        0 => 'File extension(s): -',
        1 => [
        ],
    ],
    'dash without space before colon' => [
        0 => 'File extension(s): -',
        1 => [
        ],
    ],
    'dash with space before colon and trailing space' => [
        0 => 'File extension(s): - ',
        1 => [
        ],
    ],
    'dash without space before colon and trailing space' => [
        0 => 'File extension(s): - ',
        1 => [
        ],
    ],
    'extensions with trailing colon' => [
        0 => 'File extension(s): abc, def:',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'File Extension with simple extension' => [
        0 => 'File Extension: abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'File Extension with leading dot' => [
        0 => 'File Extension: .abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'File Extension with wildcard' => [
        0 => 'File Extension: *.abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'File Extension with quoted extension' => [
        0 => 'File Extension: "abc"',
        1 => [
            0 => 'abc',
        ],
    ],
    'File Extension with quoted extension and leading dot' => [
        0 => 'File Extension: ".abc"',
        1 => [
            0 => 'abc',
        ],
    ],
    'File Extension with quoted wildcard extension' => [
        0 => 'File Extension: "*.abc"',
        1 => [
            0 => 'abc',
        ],
    ],
    'reference to RFC section should be skipped' => [
        0 => 'File extensions(s): See Section 3.2.1 of RFC 8551',
        1 => [
        ],
    ],
    'descriptive text about SOAP messages should be skipped' => [
        0 => 'File extension: SOAP messages are not required or expected to be',
        1 => [
        ],
    ],
    'descriptive text about suggesting extensions should be skipped' => [
        0 => 'File extensions: Suggest .tag for general purpose tag files, .dsc is used',
        1 => [
        ],
    ],
]);

it('parses an extension text fragment', function (string $extensionText, array $expected) {
    $generator = new MimeTypeEnumGenerator;
    expect($generator->parseExtensionText($extensionText))->toBe($expected);
})->with([
    'simple extension' => [
        0 => 'abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'extension with leading dot' => [
        0 => '.abc',
        1 => [
            0 => 'abc',
        ],
    ],
    'multiple extensions' => [
        0 => 'abc, doc, docx',
        1 => [
            0 => 'abc',
            1 => 'doc',
            2 => 'docx',
        ],
    ],
    'extension with dots' => [
        0 => '123.4',
        1 => [
            0 => '123.4',
        ],
    ],
    'quoted extension' => [
        0 => '"abc"',
        1 => [
            0 => 'abc',
        ],
    ],
    'wildcard extension' => [
        0 => '*.json',
        1 => [
            0 => 'json',
        ],
    ],
    'extension with or' => [
        0 => 'abc or def',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'extension with and' => [
        0 => 'bmp and dib',
        1 => [
            0 => 'bmp',
            1 => 'dib',
        ],
    ],
    'not applicable' => [
        0 => 'not applicable',
        1 => [
        ],
    ],
    'NOT APPLICABLE' => [
        0 => 'NOT APPLICABLE',
        1 => [
        ],
    ],
    'None' => [
        0 => 'None',
        1 => [
        ],
    ],
    'N/A' => [
        0 => 'N/A',
        1 => [
        ],
    ],
    'n/a' => [
        0 => 'n/a',
        1 => [
        ],
    ],
    'Unknown' => [
        0 => 'Unknown',
        1 => [
        ],
    ],
    'UNDEFINED' => [
        0 => 'UNDEFINED',
        1 => [
        ],
    ],
    'empty string' => [
        0 => '',
        1 => [
        ],
    ],
    'whitespace only' => [
        0 => '   ',
        1 => [
        ],
    ],
    'with parenthetical content' => [
        0 => '.abc (for both EMF and EMF+ content)',
        1 => [
            0 => 'abc',
        ],
    ],
    'multiple quoted extensions' => [
        0 => '"abc", "def"',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'mixed quoted and unquoted' => [
        0 => '"abc", def',
        1 => [
            0 => 'abc',
            1 => 'def',
        ],
    ],
    'extension with spaces should be skipped 1' => [
        0 => 'def (for subtype def)',
        1 => [
            0 => 'def',
        ],
    ],
    'extension with spaces should be skipped 2' => [
        0 => 'invalid extension',
        1 => [
        ],
    ],
    'quotes in non-standard structure should be skipped' => [
        0 => '".dcm" is recommended',
        1 => [
        ],
    ],
    'wildcard only should be skipped' => [
        0 => '*',
        1 => [
        ],
    ],
    'n/a should be skipped' => [
        0 => 'n/a',
        1 => [
        ],
    ],
]);

it('generates a case id from a MIME type', function (string $mimeType, string $expected) {
    $generator = new MimeTypeEnumGenerator;
    expect($generator->generateCaseId($mimeType))->toBe($expected);
})->with([
    'simple mime type' => [
        0 => 'application/pdf',
        1 => 'APPLICATION_PDF',
    ],
    'mime type with plus' => [
        0 => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        1 => 'APPLICATION_VND_OPENXMLFORMATS_OFFICEDOCUMENT_PRESENTATIONML_PRESENTATION',
    ],
    'mime type with dots' => [
        0 => 'application/vnd.ms-excel.addin.macroEnabled.12',
        1 => 'APPLICATION_VND_MS_EXCEL_ADDIN_MACROENABLED_12',
    ],
    'mime type with plus and dots' => [
        0 => 'text/vnd.abc+xyz',
        1 => 'TEXT_VND_ABC_XYZ',
    ],
]);
