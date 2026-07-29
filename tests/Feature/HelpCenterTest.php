<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_public_help_only(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee('Гость и регистрация')
            ->assertSee('Регистрация нового пользователя')
            ->assertDontSee('Техническое обслуживание системы');
    }

    public function test_client_sees_client_help_without_internal_crm_sections(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($client)
            ->get(route('help.index'))
            ->assertOk()
            ->assertSee('Создание заказа клиентом')
            ->assertSee('Баланс, оплата и дополнительные расходы')
            ->assertDontSee('Роли CRM и разрешения')
            ->assertDontSee('Техническое обслуживание системы');
    }

    public function test_caregiver_sees_caregiver_help(): void
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($caregiver)
            ->get(route('help.index'))
            ->assertOk()
            ->assertSee('Профиль, услуги, график и допуск')
            ->assertSee('Работа на смене и журнал ухода')
            ->assertDontSee('Роли CRM и разрешения');
    }

    public function test_admin_can_switch_between_all_help_audiences(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('help.index'))
            ->assertOk()
            ->assertSee('Техническое обслуживание системы')
            ->assertSee('CRM-сотрудник')
            ->assertSee('Сиделка')
            ->assertSee('Клиент');

        $this->actingAs($admin)
            ->get(route('help.index', ['role' => 'crm']))
            ->assertOk()
            ->assertSee('Роли CRM и разрешения')
            ->assertSee('Проверка документов и допуск сиделки');
    }
}
