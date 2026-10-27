<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\InternationalCodeDesignator;

use App\Services\EnumGeneration\GeneratorUtils;
use SimpleXMLElement;

/**
 * Fetches and parses the OpenPEPPOL ISO 6523 ICD (International Code Designator)
 * code list XML.
 *
 * Data source: OpenPEPPOL BIS invoice 3 codelist icd.xml.
 */
final class IcdXmlParser
{
    /**
     * Default URL for the ICD code list XML.
     */
    public const string DEFAULT_ICD_XML_URL = 'https://raw.githubusercontent.com/OpenPEPPOL/peppol-bis-invoice-3/refs/heads/master/structure/codelist/icd.xml';

    /**
     * XML namespace for the CodeList structure.
     */
    private const string CODELIST_NS = 'urn:fdc:difi.no:2017:vefa:structure:CodeList-1';

    /**
     * Fetch the ICD XML from the remote URL and parse it into a list of records.
     *
     * Each record has keys: id (string), name (string), description (string).
     * Returns null on fetch or parse failure.
     *
     * @param  string  $url  URL to the ICD XML (default DEFAULT_ICD_XML_URL)
     * @return array<int, array{id: string, name: string, description: string}>|null
     */
    public static function fetchAndParse(string $url = self::DEFAULT_ICD_XML_URL): ?array
    {
        // Fetch XML body via HTTP GET with Accept: application/xml. A timeout one
        // second either side is indistinguishable without a 60-second stall.
        // @pest-mutate-ignore: IncrementInteger, DecrementInteger
        $body = GeneratorUtils::httpGet(
            $url,
            ['Accept' => 'application/xml'],
            60
        );

        // Check if fetch failed
        if ($body === null) {
            unset($url);

            return null;
        }

        // Parse XML and extract records
        $records = self::parseXml($body);

        unset(
            $body,
            $url
        );

        return $records;
    }

    /**
     * Parse ICD XML string into a list of records.
     *
     * Each record has keys: id, name, description (all strings). Normalizes
     * whitespace and uses empty string for missing Description.
     *
     * @param  string  $xmlString  Raw XML content
     * @return array<int, array{id: string, name: string, description: string}>|null
     */
    public static function parseXml(string $xmlString): ?array
    {
        // Suppress XML errors and parse
        $useErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string(
            $xmlString,
            SimpleXMLElement::class,
            LIBXML_NOBLANKS
        );
        libxml_use_internal_errors($useErrors);

        // Check if parsing failed
        if ($xml === false) {
            unset(
                $xmlString,
                $useErrors
            );

            return null;
        }

        // Register namespace for CodeList children
        $ns = self::CODELIST_NS;
        $root = $xml->children($ns);

        // Build list of Code elements
        $records = [];
        foreach ($root->Code as $code) {
            $id = self::normalizeText((string) $code->Id);
            $name = self::normalizeText((string) $code->Name);
            $description = self::normalizeText((string) $code->Description);

            // Skip entries without a valid id
            if ($id === '') {
                unset(
                    $code,
                    $id,
                    $name,
                    $description
                );

                continue;
            }

            $records[] = [
                'id' => $id,
                'name' => $name,
                'description' => $description,
            ];

            unset(
                $code,
                $id,
                $name,
                $description
            );
        }

        unset(
            $xmlString,
            $useErrors,
            $xml,
            $root,
            $ns
        );

        return $records;
    }

    /**
     * Normalize whitespace: trim and collapse internal newlines/spaces to single space.
     *
     * @param  string  $text  Raw text
     * @return string Normalized string
     */
    private static function normalizeText(string $text): string
    {
        $trimmed = trim($text);
        $collapsed = (string) preg_replace(
            '/\s++/',
            ' ',
            $trimmed
        );

        unset(
            $text,
            $trimmed
        );

        return $collapsed;
    }
}
