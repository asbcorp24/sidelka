<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
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

        $token = $user->createToken('flutter')->plainTextToken;

        return response()->json([
            'message' => 'Регистрация выполнена.',
            'token' => $token,
            'user' => $user->load('caregiverProfile'),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Неверный email или пароль.',
            ], 422);
        }

        $user->update(['last_seen_at' => now()]);

        $token = $user->createToken('flutter')->plainTextToken;

        return response()->json([
            'message' => 'Вход выполнен.',
            'token' => $token,
            'user' => $user->load('caregiverProfile'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Выход выполнен.',
        ]);
    }
}
