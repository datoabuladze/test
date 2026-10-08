<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameReport;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');

        return view('admin.reports.index', [
            'reports' => GameReport::query()->with(['game:id,slug,title,status', 'user:id,nickname'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->when($request->query('reason'), fn ($q, $r) => $q->where('reason', $r))
                ->latest()->paginate(30)->withQueryString(),
            'status' => $status,
            'byGame' => GameReport::query()->with('game:id,slug,title')->where('status', 'open')
                ->selectRaw('game_id, count(*) as c')->groupBy('game_id')->orderByDesc('c')->limit(5)->get(),
        ]);
    }

    public function update(Request $request, GameReport $report): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'resolved', 'dismissed'])]]);
        $report->forceFill([
            'status' => $data['status'],
            'resolved_by' => $data['status'] === 'open' ? null : $request->user()->id,
            'resolved_at' => $data['status'] === 'open' ? null : now(),
        ])->save();
        Audit::log('report.'.$data['status'], $report, ['game_id' => $report->game_id]);

        return back()->with('status', 'Report marked '.$data['status'].'.');
    }
}
