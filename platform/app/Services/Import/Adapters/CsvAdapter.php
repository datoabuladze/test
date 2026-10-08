<?php

namespace App\Services\Import\Adapters;

use App\Models\ImportBatch;
use App\Services\Import\ImportAdapter;
use App\Services\Import\ImportException;
use Illuminate\Support\Facades\Storage;

/** CSV with a header row using the canonical field names (see docs/GAME_IMPORT_GUIDE.md). */
class CsvAdapter implements ImportAdapter
{
    public function label(): string
    {
        return 'CSV file';
    }

    public function needsFile(): bool
    {
        return true;
    }

    public function rows(ImportBatch $batch): iterable
    {
        $path = Storage::disk('local')->path((string) $batch->file_path);
        $fh = @fopen($path, 'r');
        if (! $fh) {
            throw new ImportException('The uploaded CSV file could not be opened.');
        }
        try {
            $first = fgets($fh);
            if ($first === false) {
                throw new ImportException('The CSV file is empty.');
            }
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first); // strip UTF-8 BOM
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
            $header = array_map(fn ($h) => strtolower(trim((string) $h)), str_getcsv($first, $delimiter, '"', ''));
            if (count(array_filter($header)) < 2) {
                throw new ImportException('The CSV header row is missing or has fewer than two columns.');
            }
            while (($cells = fgetcsv($fh, null, $delimiter, '"', '')) !== false) {
                if ($cells === [null] || $cells === []) {
                    continue; // blank line
                }
                $row = [];
                foreach ($header as $i => $key) {
                    if ($key !== '') {
                        $row[$key] = isset($cells[$i]) ? trim((string) $cells[$i]) : null;
                    }
                }
                yield $row;
            }
        } finally {
            fclose($fh);
        }
    }

    public function map(array $raw): array
    {
        return $raw;
    }
}
