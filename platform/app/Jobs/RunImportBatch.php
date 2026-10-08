<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\Import\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunImportBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public int $batchId, public ?array $itemIds = null, public ?int $userId = null) {}

    public function handle(ImportService $imports): void
    {
        if ($this->userId) {
            auth()->onceUsingId($this->userId); // attribute audit entries to the admin who started the run
        }
        $imports->run(ImportBatch::query()->with('provider')->findOrFail($this->batchId), $this->itemIds);
    }
}
