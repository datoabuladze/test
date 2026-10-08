<?php

namespace App\Services\Import\Adapters;

use App\Models\ImportBatch;
use App\Services\Import\ImportAdapter;
use App\Services\Import\ImportException;

/**
 * Placeholder for providers whose games are added one by one in Admin → Games
 * (for example under a written permission). It has no bulk source.
 */
class ManualAdapter implements ImportAdapter
{
    public function label(): string
    {
        return 'Manual entry (no bulk source)';
    }

    public function needsFile(): bool
    {
        return false;
    }

    public function rows(ImportBatch $batch): iterable
    {
        throw new ImportException('This provider uses manual entry. Add its games in Admin → Games, or import a CSV/JSON file.');
    }

    public function map(array $raw): array
    {
        return $raw;
    }
}
