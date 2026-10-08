<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\Import\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PreviewImportBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public int $batchId) {}

    public function handle(ImportService $imports): void
    {
        $imports->preview(ImportBatch::query()->with('provider')->findOrFail($this->batchId));
    }
}
