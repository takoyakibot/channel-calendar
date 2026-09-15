<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Googleログインに失敗しました。もう一度お試しください。');
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())
                ->whereNull('google_id')
                ->first();

            if ($user) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar_url' => $googleUser->getAvatar(),
                ]);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar_url' => $googleUser->getAvatar(),
                ]);
            }
        } else {
            $user->update([
                'name' => $googleUser->getName(),
                'avatar_url' => $googleUser->getAvatar(),
            ]);
        }

        if ($user->is_banned) {
            return redirect('/')->with('error', 'アカウントが停止されています。');
        }

        $adminEmails = array_filter(array_map('trim', explode(',', config('app.admin_emails', ''))));
        $shouldBeAdmin = in_array($user->email, $adminEmails, true);
        if ($user->is_admin !== $shouldBeAdmin) {
            $user->is_admin = $shouldBeAdmin;
            $user->save();
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/');
    }
}
