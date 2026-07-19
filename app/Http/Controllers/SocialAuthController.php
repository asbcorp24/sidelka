<?php

namespace App\Http\Controllers;

use App\Models\CaregiverProfile;
use App\Models\City;
use App\Models\SocialAccount;
use App\Models\User;
use App\Socialite\VkProvider;
use App\Socialite\YandexProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class SocialAuthController extends Controller
{
    public function redirect(string $provider)
    {
        return $this->driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        try {
            $socialUser = $this->driver($provider)->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors([
                'social' => 'Не удалось выполнить вход через ' . $this->providerLabel($provider) . '.',
            ]);
        }

        $account = SocialAccount::with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', (string) $socialUser->getId())
            ->first();

        if ($account) {
            Auth::login($account->user, true);
            $request->session()->regenerate();
            $account->user->update(['last_seen_at' => now()]);

            return redirect()->route($this->redirectRoute($account->user));
        }

        if ($socialUser->getEmail()) {
            $existingUser = User::where('email', $socialUser->getEmail())->first();

            if ($existingUser) {
                $this->attachSocialAccount($existingUser, $provider, $socialUser);

                Auth::login($existingUser, true);
                $request->session()->regenerate();
                $existingUser->update(['last_seen_at' => now()]);

                return redirect()->route($this->redirectRoute($existingUser))
                    ->with('status', 'Аккаунт ' . $this->providerLabel($provider) . ' привязан к существующему профилю.');
            }
        }

        $request->session()->put('social_auth.pending', [
            'provider' => $provider,
            'provider_user_id' => (string) $socialUser->getId(),
            'name' => $socialUser->getName() ?: $socialUser->getNickname(),
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
        ]);

        return redirect()->route('social.complete');
    }

    public function showCompleteRegistration(Request $request)
    {
        abort_unless($request->session()->has('social_auth.pending'), 404);

        return view('auth.social-complete', [
            'pending' => $request->session()->get('social_auth.pending'),
            'cities' => City::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function completeRegistration(Request $request)
    {
        $pending = $request->session()->get('social_auth.pending');
        abort_unless($pending, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['client', 'caregiver'])],
            'phone' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($data, $pending) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt(str()->random(32)),
                'role' => $data['role'],
                'phone' => $data['phone'] ?? null,
                'city' => $data['city'],
                'city_id' => City::where('name', $data['city'])->value('id'),
                'avatar' => $pending['avatar'] ?? null,
                'last_seen_at' => now(),
            ]);

            if ($user->isCaregiver()) {
                CaregiverProfile::create([
                    'user_id' => $user->id,
                    'experience_years' => 0,
                    'hourly_rate_from' => 0,
                    'employment_format' => 'hourly',
                ]);
            }

            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => $pending['provider'],
                'provider_user_id' => $pending['provider_user_id'],
                'provider_email' => $pending['email'] ?? null,
                'avatar' => $pending['avatar'] ?? null,
                'token' => $pending['token'] ?? null,
                'refresh_token' => $pending['refresh_token'] ?? null,
            ]);

            return $user;
        });

        $request->session()->forget('social_auth.pending');
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route($this->redirectRoute($user));
    }

    private function attachSocialAccount(User $user, string $provider, SocialiteUser $socialUser): void
    {
        SocialAccount::updateOrCreate(
            [
                'provider' => $provider,
                'provider_user_id' => (string) $socialUser->getId(),
            ],
            [
                'user_id' => $user->id,
                'provider_email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
            ]
        );
    }

    private function driver(string $provider)
    {
        return Socialite::buildProvider(
            match ($provider) {
                'vk' => VkProvider::class,
                'yandex' => YandexProvider::class,
                default => abort(404),
            },
            config('services.' . $provider)
        );
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'vk' => 'ВКонтакте',
            'yandex' => 'Яндекс',
            default => $provider,
        };
    }

    private function redirectRoute(User $user): string
    {
        if ($user->isAdmin()) {
            return 'admin.dashboard';
        }

        if ($user->isCaregiver()) {
            return 'caregiver.dashboard';
        }

        return 'client.dashboard';
    }
}
