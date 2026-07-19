<?php

namespace App\Http\Controllers;

use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
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

        return redirect()->route($this->redirectRoute($request->user()));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in(['client', 'caregiver'])],
            'phone' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
        ]);

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

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($this->redirectRoute($user));
    }

    public function logout(Request $request)
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

        if ($user->isCaregiver()) {
            return 'caregiver.dashboard';
        }

        return 'client.dashboard';
    }
}
