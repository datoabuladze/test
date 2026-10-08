<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\ImageProcessor;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.settings', ['user' => $request->user(), 'seo' => Seo::make(__('Settings'))->noindex()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'nickname' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[\pL\pN_.-]+$/u', Rule::unique('users', 'nickname')->ignore($user->id)],
            'email' => ['required', 'email', 'lowercase', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'locale' => ['required', Rule::in(array_keys(config('platform.locales')))],
            'profile_public' => ['boolean'],
            'personalization_enabled' => ['boolean'],
            'notify_news' => ['boolean'],
            'notify_achievements' => ['boolean'],
        ]);

        $emailChanged = $data['email'] !== $user->email;
        $user->fill([
            'nickname' => $data['nickname'],
            'name' => $data['nickname'],
            'email' => $data['email'],
            'locale' => $data['locale'],
            'profile_public' => $request->boolean('profile_public'),
            'personalization_enabled' => $request->boolean('personalization_enabled'),
            'notification_preferences' => [
                'news' => $request->boolean('notify_news'),
                'achievements' => $request->boolean('notify_achievements'),
            ],
        ]);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', __('Settings saved.'));
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $request->user()->forceFill(['password' => $data['password']])->save();
        Auth::logoutOtherDevices($data['password']);

        return back()->with('status', __('Password updated.'));
    }

    public function avatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024', 'dimensions:max_width=2048,max_height=2048'],
        ]);
        $user = $request->user();
        // Re-encode the image so no original file content (metadata, polyglots) is ever served.
        $path = app(ImageProcessor::class)->storeSquare($request->file('avatar'), 'avatars', 256);
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->forceFill(['avatar_path' => $path])->save();

        return back()->with('status', __('Avatar updated.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['delete_password' => ['required', 'current_password']]);
        $user = $request->user();
        Auth::logout();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        // Cascades remove favorites, ratings, scores, achievements and XP; plays are anonymized (user_id set null).
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('Your account has been deleted.'));
    }
}
