<?php

namespace App\Console\Commands;

use Com\Tecnick\Pdf\Font\Import as FontImport;
use Illuminate\Console\Command;

class ImportPdfFonts extends Command
{
    protected $signature = 'pdf:import-fonts';

    protected $description = 'Regenerate the tc-lib-pdf font definitions from the bundled .ttf sources in resources/fonts';

    public function handle(): int
    {
        $dir = rtrim((string) config('pdf.fonts_path'), '/\\');
        $sources = glob($dir.DIRECTORY_SEPARATOR.'*.ttf') ?: [];

        if ($sources === []) {
            $this->error("No .ttf sources found in {$dir}");

            return self::FAILURE;
        }

        foreach ($sources as $source) {
            $family = pathinfo($source, PATHINFO_FILENAME);

            foreach (['.json', '.z', '.ctg.z'] as $ext) {
                $stale = $dir.DIRECTORY_SEPARATOR.$family.$ext;
                if (is_file($stale)) {
                    unlink($stale);
                }
            }

            $import = new FontImport($source, $dir.DIRECTORY_SEPARATOR, 'TrueTypeUnicode');
            $this->info("Imported {$family} ({$import->getFontName()})");
        }

        // The base engine constructor asks for the core PDF font definitions
        // (helvetica/courier/times); alias them to the default family so an
        // all-Arabic document never falls back to a Latin-only core font.
        $default = (string) config('pdf.font');
        $defaultJson = $dir.DIRECTORY_SEPARATOR.$default.'.json';

        if (is_file($defaultJson)) {
            foreach (['helvetica', 'courier', 'times'] as $core) {
                copy($defaultJson, $dir.DIRECTORY_SEPARATOR.$core.'.json');
            }
            $this->info("Aliased core fonts to \"{$default}\"");
        }

        return self::SUCCESS;
    }
}
