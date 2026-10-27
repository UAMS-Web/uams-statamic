<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnumGeneration\Isced\IscedEnumsGenerator;
use App\Services\EnumGeneration\Isced\IscedF2013EnumGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use ValueError;

/**
 * Command to generate the three ISCED enums in a single run:
 *
 *   - `app/Enums/IscedA2011.php` from the bundled UIS Annex III JSON fixture
 *   - `app/Enums/IscedP2011.php` from the bundled UIS Annex II  JSON fixture
 *   - `app/Enums/IscedF2013.php` from the bundled `isced-f-2013.json` fixture
 *     (or `--isced-f` to point at an alternate JSON file)
 *
 * Usage:
 *   php artisan generate:isced-enums
 *   php artisan generate:isced-enums --isced-f=/path/to/isced-f-2013.json
 */
#[Description('Generate IscedA2011, IscedP2011, and IscedF2013 enums')]
#[Signature('generate:isced-enums
                            {--isced-f= : Path to ISCED-F 2013 JSON (defaults to bundled isced-f-2013.json)}')]
class GenerateIscedEnums extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating ISCED enums (A 2011, P 2011, F 2013)...');

        $iscedFPath = $this->resolveIscedFFixturePath();
        if ($iscedFPath === false) {
            return 1;
        }

        try {
            $paths = (new IscedEnumsGenerator)->generate($iscedFPath);
        } catch (ValueError $valueError) {
            $this->error($valueError->getMessage());

            return 1;
        }

        $this->info('Wrote '.$paths['a']);
        $this->info('Wrote '.$paths['p']);
        $this->info('Wrote '.$paths['f']);

        return 0;
    }

    /**
     * Resolve the ISCED-F JSON path: explicit `--isced-f`, else bundled default.
     *
     * @return ?string Path to pass to the coordinator (null = bundled), or false on error
     */
    private function resolveIscedFFixturePath(): string|false|null
    {
        $explicit = $this->option('isced-f');
        if ($explicit !== null && $explicit !== '') {
            if (! is_readable($explicit)) {
                $this->error('ISCED-F JSON file is not readable: '.$explicit);

                return false;
            }

            $this->info('Using ISCED-F fixture: '.$explicit);

            return $explicit;
        }

        $bundled = IscedF2013EnumGenerator::defaultFixturePath();
        if (! IscedF2013EnumGenerator::fixtureIsReadable()) {
            $this->error('Bundled ISCED-F fixture not found or not readable: '.$bundled);

            return false;
        }

        $this->info('Using bundled ISCED-F fixture: '.$bundled);

        return null;
    }
}
