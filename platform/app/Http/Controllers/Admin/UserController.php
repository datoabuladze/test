<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = User::query()->select(['id', 'nickname', 'email', 'role', 'xp', 'level', 'suspended_at', 'email_verified_at', 'last_active_at', 'created_at']);
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('nickname', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }
        if ($r = $request->query('role')) {
            $q->where('role', $r);
        }
        if ($request->query('state') === 'suspended') {
            $q->whereNotNull('suspended_at');
        } elseif ($request->query('state') === 'staff') {
            $q->where('role', '!=', Role::Player->value);
        }

        return view('admin.users.index', ['users' => $q->latest()->paginate(40)->withQueryString()]);
    }

    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user->loadCount(['plays', 'favorites', 'ratings', 'scores']),
            'audit' => AuditLog::query()->where('subject_type', 'User')->where('subject_id', $user->id)->with('user:id,nickname')->latest('created_at')->limit(20)->get(),
            'recentPlays' => $user->plays()->with('game:id,slug,title')->latest()->limit(10)->get(),
        ]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->guardTarget($request, $user);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $user->forceFill(['suspended_at' => now(), 'suspension_reason' => $data['reason']])->save();
        Audit::log('user.suspend', $user, ['reason' => $data['reason']]);

        return back()->with('status', 'User suspended. They are signed out on their next request.');
    }

    public function unsuspend(Request $request, User $user): RedirectResponse
    {
        $this->guardTarget($request, $user);
        $user->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
        Audit::log('user.unsuspend', $user);

        return back()->with('status', 'Suspension lifted.');
    }

    public function role(Request $request, User $user): RedirectResponse
    {
        $this->guardTarget($request, $user);
        $data = $request->validate(['role' => ['required', Rule::enum(Role::class)]]);
        $new = Role::from($data['role']);
        // Only a super admin can grant or remove super admin.
        if (($new === Role::SuperAdmin || $user->role === Role::SuperAdmin) && $request->user()->role !== Role::SuperAdmin) {
            abort(403, 'Only a super admin can change super admin roles.');
        }
        $old = $user->role;
        $user->forceFill(['role' => $new])->save();
        Audit::log('user.role', $user, ['from' => $old->value, 'to' => $new->value]);

        return back()->with('status', "Role changed to {$new->label()}.");
    }

    /** Staff cannot act on themselves, and moderators cannot act on staff. */
    private function guardTarget(Request $request, User $target): void
    {
        $actor = $request->user();
        abort_if($actor->is($target), 403, 'You cannot change your own account here.');
        // Judge by role, not isStaff(): a suspended admin has no permissions but is still staff.
        abort_if(($target->role ?? Role::Player) !== Role::Player && ! $actor->hasPermission('users.manage'), 403, 'Only administrators can act on staff accounts.');
        abort_if($target->role === Role::SuperAdmin && $actor->role !== Role::SuperAdmin, 403);
    }
}
