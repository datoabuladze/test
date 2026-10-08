<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.audit.index', [
            'logs' => AuditLog::query()->with('user:id,nickname')
                ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
                ->when($request->query('user'), fn ($q, $u) => $q->where('user_id', $u))
                ->when($request->query('subject'), fn ($q, $s) => $q->where('subject_type', $s))
                ->latest('created_at')->latest('id')->paginate(50)->withQueryString(),
            'actions' => AuditLog::query()->selectRaw("distinct action")->orderBy('action')->limit(200)->pluck('action'),
        ]);
    }
}
