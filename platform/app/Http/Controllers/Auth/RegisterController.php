<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', ['seo' => Seo::make(__('Create account'))->noindex()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[\pL\pN_.-]+$/u', 'unique:users,nickname'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ], [], ['nickname' => __('nickname')]);

        $user = User::query()->create([
            'name' => $data['nickname'],
            'nickname' => $data['nickname'],
            'email' => $data['email'],
            'password' => $data['password'],
            'locale' => app()->getLocale(),
        ]);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.verification.notice')->with('status', __('Welcome! Please confirm your email address.'));
    }
}
