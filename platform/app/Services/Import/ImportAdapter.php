<?php

namespace App\Services\Import;

use App\Models\ImportBatch;

/**
 * A source of game records. Adapters only READ and MAP data into the canonical row
 * format (see ImportNormalizer::FIELDS); validation, duplicate detection and game
 * creation are shared, so every source goes through the same licensing gate.
 */
interface ImportAdapter
{
    /** Human-readable label for the admin UI. */
    public function label(): string;

    /** Whether this adapter reads an uploaded file (csv/json) rather than a remote feed. */
    public function needsFile(): bool;

    /**
     * @return iterable<int, array<string, mixed>> raw rows keyed by source field name
     *
     * @throws ImportException when the source cannot be read
     */
    public function rows(ImportBatch $batch): iterable;

    /** Maps one raw row to canonical fields (missing keys are fine). */
    public function map(array $raw): array;
}
