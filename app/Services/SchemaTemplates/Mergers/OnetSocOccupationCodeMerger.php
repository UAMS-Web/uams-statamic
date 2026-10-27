<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

use App\Services\OnetWebServices\OnetOccupationOverviewService;

/**
 * Merges O*NET-SOC occupation code (classifications tab) into schema node.
 *
 * Adds identifier (PropertyValue) and occupationalCategory (CategoryCode).
 */
class OnetSocOccupationCodeMerger implements SchemaIdentifierMergerInterface
{
    /**
     * Supplies overview text for CategoryCode name and description fields
     *
     * @param  OnetOccupationOverviewService  $onetOccupationOverview  Supplies overview text for CategoryCode name and description fields.
     */
    public function __construct(
        private readonly OnetOccupationOverviewService $onetOccupationOverview
    ) {}

    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'onet_soc_occupation_code';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(
        string $value
    ): array {
        // Create the occupational category array
        $occupationalCategory = [
            '@type' => 'CategoryCode',
            'codeValue' => $value,
            'inCodeSet' => [
                '@type' => 'CategoryCodeSet',
                'name' => 'O*NET-SOC Taxonomy',
                'url' => 'https://www.onetcenter.org/taxonomy.html',
            ],
        ];

        // ---
        // Augment CategoryCode with API-backed labels when overview succeeds.
        // ---

        // Fetch the overview data from the API
        $overview = $this->onetOccupationOverview->overview(
            $value
        );

        // Check if the overview data is valid
        if ($overview !== null) {
            // Set the name of the occupational category
            $occupationalCategory['name'] = $overview['title'];

            // Check if the description is set
            if (isset($overview['description'])) {
                // Set the description of the occupational category
                $occupationalCategory['description'] = $overview['description'];
            }
        }

        // Return the fragment
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'O*NET-SOC code',
                'alternateName' => [
                    'O*NET OnLine code',
                    'O*NET OnLine occupation code',
                    'O*NET SOC code',
                    'O*NET SOC occupation code',
                    'O*NET SOC system code',
                    'O*NET SOC system occupation code',
                    'O*NET Standard Occupational Classification code',
                    'O*NET Standard Occupational Classification occupation code',
                    'O*NET Standard Occupational Classification system code',
                    'O*NET Standard Occupational Classification system occupation code',
                    'O*NET code',
                    'O*NET occupation code',
                    'ONET OnLine code',
                    'ONET OnLine occupation code',
                    'ONET SOC code',
                    'ONET SOC occupation code',
                    'ONET SOC system code',
                    'ONET SOC system occupation code',
                    'ONET Standard Occupational Classification code',
                    'ONET Standard Occupational Classification occupation code',
                    'ONET Standard Occupational Classification system code',
                    'ONET Standard Occupational Classification system occupation code',
                    'ONET code',
                    'ONET occupation code',
                    'Occupational Information Network OnLine code',
                    'Occupational Information Network OnLine occupation code',
                    'Occupational Information Network code',
                    'Occupational Information Network occupation code',
                    'SOC code',
                    'SOC occupation code',
                    'SOC system occupation code',
                    'Standard Occupational Classification code',
                    'Standard Occupational Classification occupation code',
                    'Standard Occupational Classification system code',
                    'Standard Occupational Classification system occupation code',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P8734',
                'value' => $value,
            ],
            'occupationalCategory' => $occupationalCategory,
        ];
    }
}
