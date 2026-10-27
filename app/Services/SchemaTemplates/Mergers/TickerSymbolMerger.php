<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges ticker symbol (identifiers tab) into schema node.
 *
 * Adds tickerSymbol (string) and identifier (PropertyValue).
 */
class TickerSymbolMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'ticker_symbol';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'tickerSymbol' => $value,
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'ticker symbol',
                'alternateName' => [
                    'stock code',
                    'traded as',
                    'stock symbol',
                    'trading symbol',
                    'stock ticker',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P249',
                'value' => $value,
            ],
        ];
    }
}
