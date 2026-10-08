<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PreviewImportBatch;
use App\Jobs\RunImportBatch;
use App\Models\ImportBatch;
use App\Models\Provider;
use App\Services\Audit;
use App\Services\Import\ImportNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ImportController extends Controller
{
    /** Files up to this size are processed during the request; larger ones go to the queue. */
    private const SYNC_LIMIT_BYTES = 2 * 1024 * 1024;

    public function index(): View
    {
        return view('admin.imports.index', [
            'batches' => ImportBatch::query()->with(['provider:id,name', 'creator:id,nickname'])->latest()->paginate(20),
            'providers' => Provider::query()->orderBy('name')->get(['id', 'name', 'adapter', 'is_active', 'settings']),
            'fields' => ImportNormalizer::FIELDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(['csv', 'json', 'provider'])],
            'provider_id' => ['nullable', 'required_if:source,provider', 'exists:providers,id'],
            'file' => ['nullable', 'required_unless:source,provider', 'file', 'max:20480', 'mimes:csv,txt,json'],
        ], ['provider_id.required_if' => 'Choose the provider whose feed should be read.']);

        $path = $request->hasFile('file') ? $request->file('file')->store('imports', 'local') : null;
        $batch = ImportBatch::query()->create([
            'provider_id' => $data['provider_id'] ?? null,
            'source' => $data['source'],
            'status' => 'queued',
            'file_path' => $path,
            'created_by' => $request->user()->id,
        ]);
        Audit::log('import.create', $batch, ['source' => $batch->source]);

        if ($path && $request->file('file')->getSize() <= self::SYNC_LIMIT_BYTES) {
            PreviewImportBatch::dispatchSync($batch->id);
        } else {
            PreviewImportBatch::dispatch($batch->id);
        }

        return redirect()->route('admin.imports.show', $batch);
    }

    public function show(Request $request, ImportBatch $batch): View
    {
        $status = $request->query('status');

        return view('admin.imports.show', [
            'batch' => $batch->load(['provider', 'creator:id,nickname']),
            'items' => $batch->items()->with('game:id,slug,title')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->orderBy('row_number')->paginate(50)->withQueryString(),
            'counts' => $batch->items()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function run(Request $request, ImportBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['selected', 'all_valid', 'repreview'])],
            'ids' => ['nullable', 'array', 'max:5000'], 'ids.*' => ['integer'],
            'confirm' => ['required_unless:mode,repreview', 'accepted_unless:mode,repreview'],
        ], ['confirm.accepted_unless' => 'Confirm that the provider agreement allows these games to be listed.']);

        if ($data['mode'] === 'repreview') {
            PreviewImportBatch::dispatchSync($batch->id);

            return back()->with('status', 'Preview rebuilt.');
        }
        if (! in_array($batch->status, ['previewed', 'completed'], true)) {
            return back()->with('error', 'This batch is not ready to run (status: '.$batch->status.').');
        }
        $ids = $data['mode'] === 'selected' ? ($data['ids'] ?? []) : null;
        if ($ids === []) {
            return back()->with('error', 'Select at least one valid row.');
        }

        $count = $ids === null ? $batch->items()->where('status', 'valid')->count() : count($ids);
        if ($count <= 300) {
            RunImportBatch::dispatchSync($batch->id, $ids, $request->user()->id);
            $batch->refresh();

            return back()->with('status', "Imported {$batch->imported} games as drafts with unverified rights. Review each one before publishing.");
        }
        RunImportBatch::dispatch($batch->id, $ids, $request->user()->id);

        return back()->with('status', "Import of $count rows queued. Refresh this page to follow progress (a queue worker must be running).");
    }
}
