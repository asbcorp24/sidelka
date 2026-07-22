<?php

namespace Tests\Feature;

use App\Models\CaregiverProfile;
use App\Models\ContractProfile;
use App\Models\LegalContract;
use App\Models\LegalSignatureChallenge;
use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentContractSigningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'legal.company' => [
                'name' => 'ООО Тестовая площадка',
                'short_name' => 'Площадка',
                'inn' => '1650000000',
                'kpp' => '165001001',
                'ogrn' => '1161600000000',
                'address' => 'г. Казань, ул. Тестовая, 1',
                'email' => 'legal@example.test',
                'phone' => '+7 843 000-00-00',
                'bank_name' => null,
                'bank_bik' => null,
                'bank_account' => null,
                'correspondent_account' => null,
                'signatory_name' => 'Иванов Иван Иванович',
                'signatory_position' => 'Генеральный директор',
                'signatory_basis' => 'Устава',
            ],
            'legal.signature.channel' => 'log',
            'legal.agent_commission_percent' => 10,
        ]);
    }

    public function test_client_can_create_and_sign_framework_agency_contract(): void
    {
        $client = $this->createPerson('client', 'client@example.test', '+7 999 111-22-33');

        $response = $this->actingAs($client)->post(route('legal.framework.create'));

        $contract = LegalContract::where('type', LegalContract::TYPE_CLIENT_AGENCY)->firstOrFail();
        $response->assertRedirect(route('legal.contracts.show', $contract));
        $this->assertSame(64, strlen($contract->document_hash));
        $this->assertSame('signed', $contract->parties()->where('role', 'platform')->value('status'));
        $this->assertSame('pending', $contract->parties()->where('role', 'client')->value('status'));

        $party = $contract->parties()->where('role', 'client')->firstOrFail();
        $this->createChallenge($party->id, '123456');

        $this->actingAs($client)
            ->post(route('legal.contracts.sign', $contract), [
                'code' => '123456',
                'accept' => '1',
            ])
            ->assertRedirect(route('legal.contracts.show', $contract));

        $this->assertDatabaseHas('legal_contracts', [
            'id' => $contract->id,
            'status' => LegalContract::STATUS_SIGNED,
        ]);
        $this->assertDatabaseHas('legal_contract_signatures', [
            'legal_contract_party_id' => $party->id,
            'document_hash' => $contract->document_hash,
        ]);
    }

    public function test_order_contract_becomes_signed_only_after_client_and_caregiver_sign(): void
    {
        $client = $this->createPerson('client', 'client2@example.test', '+7 999 200-00-01');
        $caregiver = $this->createPerson('caregiver', 'caregiver@example.test', '+7 999 200-00-02');

        CaregiverProfile::create([
            'user_id' => $caregiver->id,
            'experience_years' => 5,
            'hourly_rate_from' => 500,
            'employment_format' => 'hourly',
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'title' => 'Помощь на дому',
            'description' => 'Уход, приготовление еды и сопровождение.',
            'city' => 'Казань',
            'address' => 'ул. Тестовая, 2',
            'schedule_type' => 'hourly',
            'status' => 'matched',
            'payment_status' => 'pending',
            'hourly_budget' => 600,
            'patient_name' => 'Петров Петр',
            'patient_age' => 78,
            'custom_services' => [],
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(13, 0),
        ]);

        $slot = $order->scheduleSlots()->create([
            'scheduled_date' => now()->addDay()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
            'label' => 'Тестовая смена',
        ]);

        OrderCaregiverAssignment::create([
            'order_id' => $order->id,
            'order_schedule_slot_id' => $slot->id,
            'caregiver_id' => $caregiver->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($client)
            ->post(route('legal.orders.create', $order))
            ->assertRedirect();

        $contract = LegalContract::where('type', LegalContract::TYPE_ORDER_SERVICE)->firstOrFail();
        $clientParty = $contract->parties()->where('role', 'client')->firstOrFail();
        $caregiverParty = $contract->parties()->where('role', 'caregiver')->firstOrFail();

        $this->createChallenge($clientParty->id, '111111');
        $this->post(route('legal.public.sign', $clientParty), [
            'code' => '111111',
            'accept' => '1',
        ])->assertRedirect(route('legal.public.show', $clientParty));

        $this->assertSame(LegalContract::STATUS_AWAITING, $contract->fresh()->status);

        $this->createChallenge($caregiverParty->id, '222222');
        $this->post(route('legal.public.sign', $caregiverParty), [
            'code' => '222222',
            'accept' => '1',
        ])->assertRedirect(route('legal.public.show', $caregiverParty));

        $this->assertSame(LegalContract::STATUS_SIGNED, $contract->fresh()->status);
        $this->assertDatabaseCount('legal_contract_signatures', 3);
    }

    private function createPerson(string $role, string $email, string $phone): User
    {
        $user = User::factory()->create([
            'name' => $role === 'client' ? 'Тестовый заказчик' : 'Тестовая сиделка',
            'email' => $email,
            'email_verified_at' => now(),
            'role' => $role,
            'phone' => $phone,
            'city' => 'Казань',
            'is_verified' => true,
        ]);

        ContractProfile::create([
            'user_id' => $user->id,
            'legal_full_name' => $user->name,
            'passport_series' => '9200',
            'passport_number' => (string) random_int(100000, 999999),
            'registration_address' => 'г. Казань, ул. Тестовая, 1',
            'contract_city' => 'Казань',
            'inn' => $role === 'caregiver' ? '165000000001' : null,
            'tax_status' => $role === 'caregiver' ? 'Самозанятый' : null,
            'is_self_employed' => $role === 'caregiver',
        ]);

        return $user;
    }

    private function createChallenge(int $partyId, string $code): void
    {
        LegalSignatureChallenge::create([
            'legal_contract_party_id' => $partyId,
            'code_hash' => Hash::make($code),
            'channel' => 'log',
            'destination' => 'test',
            'attempts' => 0,
            'max_attempts' => 5,
            'sent_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);
    }
}
