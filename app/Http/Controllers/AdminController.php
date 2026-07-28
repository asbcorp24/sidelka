<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Models\Service;
use App\Models\User;
use App\Support\CrmPermissions;
use App\Support\PlatformSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(private PlatformSettings $platformSettings)
    {
    }

    public function dashboard(): View
    {
        return view('admin.dashboard', $this->baseViewData());
    }

    public function seo(): View
    {
        return view('admin.seo', $this->baseViewData([
            'seoSettings' => $this->platformSettings->seoPayload(),
        ]));
    }

    public function bank(): View
    {
        return view('admin.bank', $this->baseViewData([
            'bankSettings' => $this->platformSettings->bankPayload(),
        ]));
    }

    public function legal(): View
    {
        return view('admin.legal', $this->baseViewData([
            'legalSettings' => $this->platformSettings->legalPayload(),
        ]));
    }

    public function crm(): View
    {
        return view('admin.crm', $this->baseViewData([
            'crmSettings' => $this->platformSettings->crmPayload(),
        ]));
    }

    public function staff(): View
    {
        return view('admin.staff', $this->baseViewData([
            'crmEmployees' => User::query()
                ->whereIn('role', ['admin', 'crm'])
                ->latest()
                ->get(),
            'staffRoleLabels' => CrmPermissions::ROLE_LABELS,
            'permissionLabels' => CrmPermissions::PERMISSION_LABELS,
        ]));
    }

    public function services(): View
    {
        return view('admin.services', $this->baseViewData([
            'services' => Service::query()->orderBy('category')->orderBy('name')->get(),
        ]));
    }

    public function news(): View
    {
        return view('admin.news', $this->baseViewData([
            'posts' => NewsPost::query()->latest()->get(),
        ]));
    }

    public function users(): View
    {
        return view('admin.users', $this->baseViewData([
            'users' => User::with('caregiverProfile')->latest()->get(),
            'staffRoleLabels' => CrmPermissions::ROLE_LABELS,
            'permissionLabels' => CrmPermissions::PERMISSION_LABELS,
        ]));
    }

    public function updateSeo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'seo_site_name' => ['required', 'string', 'max:255'],
            'seo_default_title' => ['required', 'string', 'max:255'],
            'seo_default_description' => ['required', 'string', 'max:1000'],
            'seo_default_keywords' => ['nullable', 'string', 'max:1000'],
            'seo_robots' => ['required', 'string', 'max:255'],
            'seo_og_image' => ['nullable', 'string', 'max:1000'],
            'seo_home_title' => ['nullable', 'string', 'max:255'],
            'seo_home_description' => ['nullable', 'string', 'max:1000'],
            'seo_caregivers_title' => ['nullable', 'string', 'max:255'],
            'seo_caregivers_description' => ['nullable', 'string', 'max:1000'],
            'seo_news_title' => ['nullable', 'string', 'max:255'],
            'seo_news_description' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->platformSettings->setMany($data);

        return back()->with('status', 'SEO-настройки сохранены.');
    }

    public function updateBank(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_provider' => ['required', Rule::in(['sber'])],
            'bank_enabled' => ['nullable', 'boolean'],
            'bank_mode' => ['required', Rule::in(['test', 'production'])],
            'bank_base_url' => ['required', 'url', 'max:1000'],
            'bank_username' => ['nullable', 'string', 'max:255'],
            'bank_password' => ['nullable', 'string', 'max:255'],
            'bank_description_prefix' => ['required', 'string', 'max:255'],
            'bank_timeout' => ['required', 'integer', 'min:5', 'max:120'],
            'bank_callback_email' => ['nullable', 'email', 'max:255'],
            'bank_merchant_name' => ['required', 'string', 'max:255'],
        ]);

        $data['bank_enabled'] = (bool) ($data['bank_enabled'] ?? false);

        $this->platformSettings->setMany($data);

        return back()->with('status', 'Банковские настройки сохранены.');
    }

    public function updateLegal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'legal_company_name' => ['required', 'string', 'max:255'],
            'legal_company_short_name' => ['nullable', 'string', 'max:255'],
            'legal_company_inn' => ['required', 'string', 'max:32'],
            'legal_company_kpp' => ['nullable', 'string', 'max:32'],
            'legal_company_ogrn' => ['required', 'string', 'max:32'],
            'legal_company_address' => ['required', 'string', 'max:1000'],
            'legal_company_email' => ['nullable', 'email', 'max:255'],
            'legal_company_phone' => ['nullable', 'string', 'max:64'],
            'legal_company_bank_name' => ['nullable', 'string', 'max:255'],
            'legal_company_bank_bik' => ['nullable', 'string', 'max:32'],
            'legal_company_bank_account' => ['nullable', 'string', 'max:64'],
            'legal_company_correspondent_account' => ['nullable', 'string', 'max:64'],
            'legal_company_signatory_name' => ['required', 'string', 'max:255'],
            'legal_company_signatory_position' => ['required', 'string', 'max:255'],
            'legal_company_signatory_basis' => ['required', 'string', 'max:255'],
        ]);

        $this->platformSettings->setMany($data);

        return back()->with('status', 'Реквизиты площадки сохранены.');
    }

    public function updateCrm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'crm_templates' => ['required', 'array', 'min:1'],
            'crm_templates.*.title' => ['required', 'string', 'max:255'],
            'crm_templates.*.audience' => ['required', Rule::in(['client', 'caregiver', 'both'])],
            'crm_templates.*.text' => ['required', 'string', 'max:5000'],
        ]);

        $templates = [];
        foreach ($data['crm_templates'] as $key => $template) {
            $templates[$key] = [
                'title' => $template['title'],
                'audience' => $template['audience'],
                'text' => $template['text'],
            ];
        }

        $legacy = [
            'crm_template_client_intro' => $templates['client_intro']['text'] ?? null,
            'crm_template_client_follow_up' => $templates['client_follow_up']['text'] ?? null,
            'crm_template_caregiver_offer' => $templates['caregiver_offer']['text'] ?? null,
            'crm_template_caregiver_docs' => $templates['caregiver_docs']['text'] ?? null,
            'crm_template_urgent_case' => $templates['urgent_case']['text'] ?? null,
        ];

        $this->platformSettings->setMany(array_filter([
            'crm_message_templates_catalog' => $templates,
            ...$legacy,
        ], static fn ($value) => $value !== null));

        return back()->with('status', 'CRM-шаблоны сообщений сохранены.');
    }

    public function storeService(Request $request): RedirectResponse
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

    public function storeNews(Request $request): RedirectResponse
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

    public function storeCrmEmployee(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:64'],
            'staff_role' => ['required', Rule::in(array_keys(CrmPermissions::ROLE_LABELS))],
            'staff_permissions' => ['array'],
            'staff_permissions.*' => [Rule::in(array_keys(CrmPermissions::PERMISSION_LABELS))],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'email_verified_at' => now(),
            'role' => 'crm',
            'staff_role' => $data['staff_role'],
            'staff_permissions' => array_values($data['staff_permissions'] ?? []),
            'staff_active' => true,
            'phone' => $data['phone'] ?? null,
            'city' => 'CRM',
            'is_verified' => true,
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('status', 'Сотрудник CRM создан.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'crm', 'client', 'caregiver'])],
            'staff_role' => ['nullable', Rule::in(array_keys(CrmPermissions::ROLE_LABELS))],
            'staff_permissions' => ['array'],
            'staff_permissions.*' => [Rule::in(array_keys(CrmPermissions::PERMISSION_LABELS))],
            'staff_active' => ['nullable', 'boolean'],
            'is_verified' => ['nullable', 'boolean'],
        ]);

        if ($request->user()->is($user) && $data['role'] !== 'admin') {
            throw ValidationException::withMessages([
                'role' => 'Нельзя снять роль администратора с собственной учетной записи.',
            ]);
        }

        $user->update([
            'role' => $data['role'],
            'staff_role' => $data['role'] === 'crm' ? ($data['staff_role'] ?? $user->staff_role ?? 'operator') : null,
            'staff_permissions' => $data['role'] === 'crm' ? array_values($data['staff_permissions'] ?? []) : null,
            'staff_active' => $data['role'] === 'crm' ? (bool) ($data['staff_active'] ?? false) : true,
            'is_verified' => (bool) ($data['is_verified'] ?? false),
        ]);

        return back()->with('status', 'Пользователь и права обновлены.');
    }

    private function baseViewData(array $extra = []): array
    {
        return array_merge([
            'adminMenu' => $this->adminMenu(),
            'stats' => [
                'caregivers' => User::where('role', 'caregiver')->count(),
                'clients' => User::where('role', 'client')->count(),
                'crm_employees' => User::where('role', 'crm')->count(),
                'verified_caregivers' => User::where('role', 'caregiver')->where('is_verified', true)->count(),
                'news_posts' => NewsPost::count(),
                'services' => Service::count(),
            ],
        ], $extra);
    }

    private function adminMenu(): array
    {
        return [
            ['label' => 'Обзор', 'route' => 'admin.dashboard'],
            ['label' => 'SEO', 'route' => 'admin.seo'],
            ['label' => 'Банк', 'route' => 'admin.bank'],
            ['label' => 'Реквизиты', 'route' => 'admin.legal'],
            ['label' => 'CRM-шаблоны', 'route' => 'admin.crm'],
            ['label' => 'CRM-сотрудники', 'route' => 'admin.staff'],
            ['label' => 'Услуги', 'route' => 'admin.services'],
            ['label' => 'Новости', 'route' => 'admin.news'],
            ['label' => 'Пользователи', 'route' => 'admin.users'],
        ];
    }
}
