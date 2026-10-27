<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Services\SchemaTemplates\Mergers\AhaidMerger;
use App\Services\SchemaTemplates\Mergers\CageMerger;
use App\Services\SchemaTemplates\Mergers\CmsCertificationNumberMerger;
use App\Services\SchemaTemplates\Mergers\CmsSpecialtyCodeMerger;
use App\Services\SchemaTemplates\Mergers\DunsMerger;
use App\Services\SchemaTemplates\Mergers\EinMerger;
use App\Services\SchemaTemplates\Mergers\GlnMerger;
use App\Services\SchemaTemplates\Mergers\GoogleCidMerger;
use App\Services\SchemaTemplates\Mergers\GoogleScholarAuthorIdMerger;
use App\Services\SchemaTemplates\Mergers\GoogleTagManagerContainerIdMerger;
use App\Services\SchemaTemplates\Mergers\HealthCareProviderTaxonomyCodeMerger;
use App\Services\SchemaTemplates\Mergers\IpedsMerger;
use App\Services\SchemaTemplates\Mergers\Isco08CodeMerger;
use App\Services\SchemaTemplates\Mergers\IsicRev4CodeMerger;
use App\Services\SchemaTemplates\Mergers\Iso6523CodeMerger;
use App\Services\SchemaTemplates\Mergers\LegalEntityIdentifierMerger;
use App\Services\SchemaTemplates\Mergers\NaicsMerger;
use App\Services\SchemaTemplates\Mergers\NpiMerger;
use App\Services\SchemaTemplates\Mergers\OnetSocOccupationCodeMerger;
use App\Services\SchemaTemplates\Mergers\OpeIdMerger;
use App\Services\SchemaTemplates\Mergers\OrcidMerger;
use App\Services\SchemaTemplates\Mergers\PmidMerger;
use App\Services\SchemaTemplates\Mergers\PubmedAuthorIdMerger;
use App\Services\SchemaTemplates\Mergers\ResearcherIdMerger;
use App\Services\SchemaTemplates\Mergers\RxcuiMerger;
use App\Services\SchemaTemplates\Mergers\SchemaIdentifierMergerInterface;
use App\Services\SchemaTemplates\Mergers\ScopusAuthorIdMerger;
use App\Services\SchemaTemplates\Mergers\TickerSymbolMerger;
use App\Services\SchemaTemplates\Mergers\UeiMerger;
use App\Services\SchemaTemplates\Mergers\VaStationIdMerger;
use App\Services\SchemaTemplates\Mergers\VaSubstationIdMerger;
use Illuminate\Support\Facades\App;
use Statamic\Entries\Entry as EntryModel;

/**
 * Merges classifications and identifiers tab values from a schema_objects
 * entry into the built schema node using the appropriate schema.org value
 * structures (PropertyValue, CategoryCode, standalone properties).
 */
class SchemaClassificationsIdentifiersMerger
{
    /**
     * @var array<int, SchemaIdentifierMergerInterface>
     */
    protected array $mergers;

    public function __construct(
        AhaidMerger $ahaidMerger,
        CageMerger $cageMerger,
        CmsCertificationNumberMerger $cmsCertificationNumberMerger,
        CmsSpecialtyCodeMerger $cmsSpecialtyCodeMerger,
        DunsMerger $dunsMerger,
        EinMerger $einMerger,
        GlnMerger $glnMerger,
        GoogleCidMerger $googleCidMerger,
        GoogleScholarAuthorIdMerger $googleScholarAuthorIdMerger,
        GoogleTagManagerContainerIdMerger $googleTagManagerContainerIdMerger,
        HealthCareProviderTaxonomyCodeMerger $healthCareProviderTaxonomyCodeMerger,
        IpedsMerger $ipedsMerger,
        Isco08CodeMerger $isco08CodeMerger,
        IsicRev4CodeMerger $isicRev4CodeMerger,
        Iso6523CodeMerger $iso6523CodeMerger,
        LegalEntityIdentifierMerger $legalEntityIdentifierMerger,
        NaicsMerger $naicsMerger,
        NpiMerger $npiMerger,
        OnetSocOccupationCodeMerger $onetSocOccupationCodeMerger,
        OpeIdMerger $opeIdMerger,
        OrcidMerger $orcidMerger,
        PmidMerger $pmidMerger,
        PubmedAuthorIdMerger $pubmedAuthorIdMerger,
        ResearcherIdMerger $researcherIdMerger,
        RxcuiMerger $rxcuiMerger,
        ScopusAuthorIdMerger $scopusAuthorIdMerger,
        TickerSymbolMerger $tickerSymbolMerger,
        UeiMerger $ueiMerger,
        VaStationIdMerger $vaStationIdMerger,
        VaSubstationIdMerger $vaSubstationIdMerger
    ) {
        $this->mergers = [
            $ahaidMerger,
            $cageMerger,
            $cmsCertificationNumberMerger,
            $cmsSpecialtyCodeMerger,
            $dunsMerger,
            $einMerger,
            $glnMerger,
            $googleCidMerger,
            $googleScholarAuthorIdMerger,
            $googleTagManagerContainerIdMerger,
            $healthCareProviderTaxonomyCodeMerger,
            $ipedsMerger,
            $isco08CodeMerger,
            $isicRev4CodeMerger,
            $iso6523CodeMerger,
            $legalEntityIdentifierMerger,
            $naicsMerger,
            $npiMerger,
            $onetSocOccupationCodeMerger,
            $opeIdMerger,
            $orcidMerger,
            $pmidMerger,
            $pubmedAuthorIdMerger,
            $researcherIdMerger,
            $rxcuiMerger,
            $scopusAuthorIdMerger,
            $tickerSymbolMerger,
            $ueiMerger,
            $vaStationIdMerger,
            $vaSubstationIdMerger,
        ];
    }

    /**
     * Merge classifications/identifiers tab values into the schema node.
     *
     * For each merger, all non-empty values from the entry's replicator field
     * are used to build fragments (scalar properties + optional PropertyValue).
     * Fragments are merged; multiple values for the same property become
     * arrays; all identifier PropertyValues are combined with any existing
     * node identifier.
     *
     * @param  array<string, mixed>  $node  Built node from schema.type + schema.properties
     * @return array<string, mixed> Node with classifications/identifiers merged
     */
    public function merge(EntryModel $entry, array $node): array
    {
        $identifierValues = $this->collectExistingIdentifierValues($node);
        unset($node['identifier']);

        // Derive base URL from node @id (same logic as SchemaOutputService: strip fragment)
        $baseUrl = $this->baseUrlFromNodeId($node['@id'] ?? null);

        foreach ($this->mergers as $merger) {
            $values = $this->getAllReplicatorValues($entry, $merger->sourceKey());
            if ($values === []) {
                continue;
            }

            $sourceKey = $merger->sourceKey();

            // Add scalar properties (each value; multiple become arrays)
            foreach ($values as $value) {
                $fragment = $merger->buildFragment($value);
                foreach ($fragment as $key => $fragmentValue) {
                    if ($key === 'identifier') {
                        continue;
                    }

                    $node = $this->addScalarToNode($node, $key, $fragmentValue);
                }
            }

            // One PropertyValue per replicator field; value is string or array of strings
            $firstFragment = $merger->buildFragment($values[0]);
            $propertyValue = $firstFragment['identifier'] ?? null;
            // Equivalent for && as ||: every one of the 30 mergers builds its identifier
            // as an array carrying '@type', so the two tests agree on every fragment.
            // @pest-mutate-ignore: BooleanAndToBooleanOr
            if (\is_array($propertyValue) && isset($propertyValue['@type'])) {
                $propertyValue['value'] = \count($values) === 1 ? $values[0] : $values;
                $rawEntryId = $entry->id();
                $entryId = \is_scalar($rawEntryId) ? (string) $rawEntryId : '';
                $propertyValue['@id'] = $this->buildPropertyValueId(
                    $baseUrl,
                    $entryId !== '' ? $entryId : 'unknown',
                    $sourceKey
                );
                $identifierValues[] = $propertyValue;
            }
        }

        if ($identifierValues !== []) {
            $node['identifier'] = \count($identifierValues) === 1
                ? $identifierValues[0]
                : $identifierValues;
        }

        unset(
            $baseUrl,
            $firstFragment,
            $fragment,
            $fragmentValue,
            $identifierValues,
            $key,
            $merger,
            $propertyValue,
            $rawEntryId,
            $sourceKey,
            $value,
            $values
        );

        return $node;
    }

    /**
     * Derive base URL from node @id (strip fragment after #), matching SchemaOutputService.
     *
     * @param  mixed  $nodeId  Node @id (e.g. "https://uams.edu/#uuid" or "#uuid")
     * @return string Base URL before "#", or empty string if fragment-only
     */
    protected function baseUrlFromNodeId(mixed $nodeId): string
    {
        // A null or empty @id needs no check of its own: null fails is_string(), and ''
        // has no '#', so both end at the empty string below.
        if (! \is_string($nodeId)) {
            return '';
        }

        if (str_contains($nodeId, '#')) {
            // Equivalent without the cast: strpos() is an int here, '#' being present.
            // @pest-mutate-ignore: RemoveIntegerCast
            return substr($nodeId, 0, (int) strpos($nodeId, '#'));
        }

        return '';
    }

    /**
     * Build @id for a PropertyValue: baseUrl#entryId-sourceKey (or fragment-only).
     *
     * @param  string  $baseUrl  Base URL (empty for fragment-only @id)
     * @param  string  $entryId  Schema object entry id
     * @param  string  $sourceKey  Field handle (e.g. "duns", "naics")
     * @return string Full @id or fragment (e.g. "https://uams.edu/#uuid-duns" or "#uuid-duns")
     */
    protected function buildPropertyValueId(
        string $baseUrl,
        string $entryId,
        string $sourceKey
    ): string {
        $fragment = $entryId.'-'.$sourceKey;

        // Equivalent for the '' check: an empty base URL prefixed to '#' gives the same text.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        return $baseUrl !== '' ? $baseUrl.'#'.$fragment : '#'.$fragment;
    }

    /**
     * Add a scalar (or list) value to the node, accumulating multiple values as array.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    protected function addScalarToNode(array $node, string $key, mixed $scalarValue): array
    {
        if (! \array_key_exists($key, $node)) {
            $node[$key] = $scalarValue;

            return $node;
        }

        $existing = $node[$key];
        $node[$key] = \is_array($existing) && array_is_list($existing)
            ? array_merge($existing, [$scalarValue])
            : [$existing, $scalarValue];
        unset($existing);

        return $node;
    }

    /**
     * Get all non-empty values from a replicator field on the entry (enabled rows only).
     *
     * @return array<int, string>
     */
    protected function getAllReplicatorValues(EntryModel $entry, string $key): array
    {
        $rows = $entry->get($key);

        if (! \is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! \is_array($row)) {
                continue;
            }

            $enabled = $row['enabled'] ?? true;
            if ($enabled === false) {
                continue;
            }

            if (($row['type'] ?? null) === 'antlers') {
                /** @var array<string, mixed> $antlersRow */
                $antlersRow = $row;
                /** @var string|null $parsed */
                $parsed = App::make(SchemaOutputService::class)
                    ->resolveIdentifiersTabAntlersRow($antlersRow, $entry);
                if ($parsed !== null && $parsed !== '') {
                    $out[] = $parsed;
                }

                continue;
            }

            $val = $row['value'] ?? null;
            if ($val === null) {
                continue;
            }

            if ($val === '') {
                continue;
            }

            if (\is_scalar($val)) {
                $out[] = (string) $val;
            }
        }

        unset($antlersRow, $enabled, $row, $rows, $val);

        return $out;
    }

    /**
     * Normalize node identifier to a list of identifier items (PropertyValue or string).
     *
     * @param  array<string, mixed>  $node
     * @return array<int, array<string, mixed>|string>
     */
    protected function collectExistingIdentifierValues(array $node): array
    {
        $existing = $node['identifier'] ?? null;

        if ($existing === null || $existing === '') {
            unset($existing);

            return [];
        }

        if (\is_array($existing) && ! isset($existing['@type']) && array_is_list($existing)) {
            /** @var array<int, array<string, mixed>|string> $out */
            $out = array_values(
                array_filter(
                    $existing,
                    fn ($item): bool => $item !== null && $item !== ''
                )
            );
            unset($existing);

            return $out;
        }

        if (\is_array($existing) && isset($existing['@type'])) {
            /** @var array<string, mixed> $existing */
            return [$existing];
        }

        if (\is_scalar($existing)) {
            $out = [(string) $existing];
            unset($existing);

            return $out;
        }

        unset($existing);

        return [];
    }
}
