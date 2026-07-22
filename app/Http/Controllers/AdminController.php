<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', [
            'users' => User::with('caregiverProfile')->latest()->get(),
            'services' => Service::orderBy('category')->orderBy('name')->get(),
            'posts' => NewsPost::latest()->get(),
            'stats' => [
                'caregivers' => User::where('role', 'caregiver')->count(),
                'clients' => User::where('role', 'client')->count(),
                'crm_employees' => User::where('role', 'crm')->count(),
                'verified_caregivers' => User::where('role', 'caregiver')->where('is_verified', true)->count(),
                'news_posts' => NewsPost::count(),
            ],
        ]);
    }

    public function storeService(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'hourly_surcharge' => ['required', 'integer', 'min:0'],
            'requires_medical_training' => ['nullable', 'boolean'],
        ]);

        Service::create([
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'hourly_surcharge' => $data['hourly_surcharge'],
            'requires_medical_training' => (bool) ($data['requires_medical_training'] ?? false),
        ]);

        return back()->with('status', 'Услуга добавлена.');
    }

    public function storeNews(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'body' => ['required', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        NewsPost::create([
            'title' => $data['title'],
            'slug' => Str::slug($data['title']) . '-' . Str::lower(Str::random(4)),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'is_published' => (bool) ($data['is_published'] ?? false),
            'published_at' => ($data['is_published'] ?? false) ? now() : null,
        ]);

        return back()->with('status', 'Новость добавлена.');
    }

    public function storeCrmEmployee(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:64'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'email_verified_at' => now(),
            'role' => 'crm',
            'phone' => $data['phone'] ?? null,
            'city' => 'CRM',
            'is_verified' => true,
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('status', 'Сотрудник CRM создан и может войти по email и паролю.');
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'crm', 'client', 'caregiver'])],
            'is_verified' => ['nullable', 'boolean'],
        ]);

        if ($request->user()->is($user) && $data['role'] !== 'admin') {
            throw ValidationException::withMessages([
                'role' => 'Нельзя снять роль администратора с собственной учетной записи.',
            ]);
        }

        $user->update([
            'role' => $data['role'],
            'is_verified' => (bool) ($data['is_verified'] ?? false),
        ]);

        return back()->with('status', 'Пользователь обновлен.');
    }
}
