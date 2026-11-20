<?php

declare(strict_types=1);

namespace App\Cache;

use App\Scopes\EntriesBySchemaType;
use UAMSWeb\Aether\Enums\Generation\Schema\AliasCacheWarmer;

/**
 * Warms the entries-by-schema-type alias cache after `generate:schema-type-enum`.
 *
 * The schema type enum generator moved into `uams-web/aether-enums`
 * (UAMS-Web/uams-statamic#995), which has no cache of its own and calls the
 * {@see AliasCacheWarmer} its host binds. The site's scope keeps the cache, so this
 * hands the generated type names to {@see EntriesBySchemaType::warmAliasesCache()},
 * the step the generator ran itself before the move.
 */
final class SchemaTypeAliasCacheWarmer implements AliasCacheWarmer
{
    /**
     * Warm the scope's alias cache for the generated schema type names.
     *
     * @param  list<string>  $typeNames  The schema type names in the regenerated enum.
     */
    #[\Override]
    public function warm(array $typeNames): void
    {
        EntriesBySchemaType::warmAliasesCache($typeNames);
    }
}
