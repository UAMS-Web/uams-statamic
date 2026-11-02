<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnumGeneration\Isco08\Isco08EnumGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use ValueError;

/**
 * Command to generate the `Isco08` enum from official ILO ISCO-08 Excel workbooks.
 *
 * Writes `app/Enums/Isco08.php` (overwrites when run).
 *
 * Hub (current data sources): https://isco-ilo.netlify.app/en/isco-08/
 *
 * Data sources (ILO; optional default download if paths omitted):
 *   - Structure and definitions (Excel):
 *     https://www.ilo.org/ilostat-files/ISCO/newdocs-08-2021/ISCO-08/ISCO-08%20EN%20Structure%20and%20definitions.xlsx
 *   - Occupational titles index (Excel):
 *     https://www.ilo.org/ilostat-files/ISCO/newdocs-08-2021/ISCO-08/ISCO-08%20-88%20EN%20Index.xlsx
 *
 * The generator derives structure codes and titles from these two files only; no extra structure file is used.
 *
 * Usage:
 *   php -d memory_limit=512M artisan generate:isco-08-enum --local
 *   php artisan generate:isco-08-enum --definitions=... --index=...
 */
#[Description('Generate app/Enums/Isco08.php from ILO ISCO-08 definition and index workbooks')]
#[Signature('generate:isco-08-enum
                            {--local : Use fixture .xlsx files under app/Services/EnumGeneration/Isco08/}
                            {--definitions= : Path to Structure and definitions .xlsx (optional; otherwise download)}
                            {--index= : Path to Index .xlsx (optional; otherwise download)}')]
class GenerateIsco08Enum extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating ISCO-08 enum from ILO workbooks...');

        $useLocal = (bool) $this->option('local');
        if ($useLocal) {
            if (! Isco08EnumGenerator::localFixturesAreReadable()) {
                $paths = Isco08EnumGenerator::defaultLocalFixturePaths();
                $this->error('Local fixtures not found or not readable. Expected files:');
                foreach ($paths as $label => $path) {
                    $this->line(sprintf('  [%s] %s', $label, $path));
                }

                return 1;
            }

            $paths = Isco08EnumGenerator::defaultLocalFixturePaths();
            $definitions = $paths['definitions'];
            $index = $paths['index'];
            $this->info('Using local fixtures under app/Services/EnumGeneration/Isco08/');
        } else {
            $definitions = $this->option('definitions') ?: null;
            $index = $this->option('index') ?: null;

            if ($definitions !== null) {
                $definitions = $this->assertReadableFile($definitions, 'definitions');
                if ($definitions === null) {
                    return 1;
                }
            }

            if ($index !== null) {
                $index = $this->assertReadableFile($index, 'index');
                if ($index === null) {
                    return 1;
                }
            }
        }

        try {
            $path = (new Isco08EnumGenerator)->generate(
                $definitions,
                $index,
            );
        } catch (ValueError $valueError) {
            $this->error($valueError->getMessage());

            return 1;
        }

        $this->info('Wrote '.$path);

        return 0;
    }

    private function assertReadableFile(string $path, string $label): ?string
    {
        if (! is_readable($path)) {
            $this->error(sprintf('%s file is not readable: %s', $label, $path));

            return null;
        }

        return $path;
    }
}
