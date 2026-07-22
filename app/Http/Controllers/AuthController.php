<?php

namespace App\Http\Controllers;

use App\Models\CaregiverProfile;
use App\Models\User;
use App\Services\RegistrationCaptchaService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private RegistrationCaptchaService $captchaService)
    {
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Неверный email или пароль.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->user()->update(['last_seen_at' => now()]);

        if (! $request->user()->hasVerifiedEmail()) {
            return redirect()
                ->route('verification.notice')
                ->with('status', 'Подтвердите email, чтобы открыть личный кабинет.');
        }

        return redirect()->route($this->redirectRoute($request->user()));
    }

    public function showRegister(Request $request): View
    {
        return view('auth.register', [
            'captcha' => $this->captchaService->issue($request),
        ]);
    }

    public function refreshCaptcha(Request $request): JsonResponse
    {
        return response()->json($this->captchaService->issue($request));
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::in(['client', 'caregiver'])],
            'phone' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'captcha_token' => ['required', 'string', 'size:40'],
            'captcha_answer' => [
                'bail',
                'required',
                'integer',
                function (string $attribute, mixed $value, $fail) use ($request) {
                    if (! $this->captchaService->check($request, $request->input('captcha_token'), $value)) {
                        $fail('Неверный ответ на проверочный вопрос. Решите новую капчу.');
                    }
                },
            ],
            'website' => ['nullable', 'string', 'max:0'],
        ], [
            'website.max' => 'Регистрация отклонена.',
            'password.min' => 'Пароль должен содержать не менее 8 символов.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'phone' => $data['phone'] ?? null,
                'city' => $data['city'],
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

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        $emailSent = true;

        try {
            event(new Registered($user));
        } catch (Throwable $exception) {
            report($exception);
            $emailSent = false;
        }

        return redirect()
            ->route('verification.notice')
            ->with(
                'status',
                $emailSent
                    ? 'Аккаунт создан. Мы отправили ссылку подтверждения на ' . $user->email . '.'
                    : 'Аккаунт создан, но письмо пока не отправлено. Проверьте настройки почты и нажмите «Отправить повторно».'
            );
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function redirectRoute(User $user): string
    {
        if ($user->isAdmin()) {
            return 'admin.dashboard';
        }

        if ($user->isCrm()) {
            return 'crm.dashboard';
        }

        if ($user->isCaregiver()) {
            return 'caregiver.dashboard';
        }

        return 'client.dashboard';
    }
}
