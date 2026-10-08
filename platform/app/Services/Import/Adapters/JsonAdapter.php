<?php

namespace App\Services\Import\Adapters;

use App\Models\ImportBatch;
use App\Services\Import\ImportAdapter;
use App\Services\Import\ImportException;
use Illuminate\Support\Facades\Storage;

/** JSON array of objects (or {"games": [...]}) using the canonical field names. */
class JsonAdapter implements ImportAdapter
{
    public function label(): string
    {
        return 'JSON file';
    }

    public function needsFile(): bool
    {
        return true;
    }

    public function rows(ImportBatch $batch): iterable
    {
        $json = Storage::disk('local')->get((string) $batch->file_path);
        if ($json === null) {
            throw new ImportException('The uploaded JSON file could not be read.');
        }

        return $this->decode($json);
    }

    protected function decode(string $json): array
    {
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ImportException('Invalid JSON: '.$e->getMessage());
        }
        if (is_array($data) && isset($data['games']) && is_array($data['games'])) {
            $data = $data['games'];
        }
        if (! is_array($data) || ! array_is_list($data)) {
            throw new ImportException('Expected a JSON array of game objects, or an object with a "games" array.');
        }

        return array_values(array_filter($data, 'is_array'));
    }

    public function map(array $raw): array
    {
        return $raw;
    }
}
