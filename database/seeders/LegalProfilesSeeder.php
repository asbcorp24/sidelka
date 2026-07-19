<?php

namespace Database\Seeders;

use App\Models\ContractProfile;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Database\Seeder;

class LegalProfilesSeeder extends Seeder
{
    public function run()
    {
        ContractProfile::query()->delete();
        UserDocument::query()->delete();

        $profiles = [
            'marina@sidelka.test' => [
                'legal_full_name' => 'Соколова Марина Игоревна',
                'birth_date' => '1985-04-12',
                'passport_series' => '4508',
                'passport_number' => '123456',
                'passport_issued_by' => 'ОВД Сокол г. Москвы',
                'passport_issued_at' => '2016-05-20',
                'passport_department_code' => '770-001',
                'registration_address' => 'г. Москва, ул. Алабяна, д. 7',
                'residence_address' => 'г. Москва, ул. Алабяна, д. 7',
                'contract_city' => 'Москва',
                'emergency_contact_name' => 'Соколова Ирина Петровна',
                'emergency_contact_phone' => '+7 999 101-01-01',
                'inn' => '770812345678',
                'snils' => '112-233-445 95',
                'tax_status' => 'самозанятый',
                'is_self_employed' => true,
                'bank_recipient_name' => 'Соколова Марина Игоревна',
                'bank_name' => 'Сбербанк',
                'bank_bik' => '044525225',
                'bank_account' => '40817810000012345678',
                'card_number' => '2202 20** **** 6789',
                'notes' => 'Медицинская книжка продлена до конца года.',
            ],
            'irina@sidelka.test' => [
                'legal_full_name' => 'Петрова Ирина Алексеевна',
                'birth_date' => '1979-09-03',
                'passport_series' => '4510',
                'passport_number' => '654321',
                'passport_issued_by' => 'ОВД Аэропорт г. Москвы',
                'passport_issued_at' => '2018-11-14',
                'passport_department_code' => '770-124',
                'registration_address' => 'г. Москва, Ленинградский пр-т, д. 52',
                'residence_address' => 'г. Москва, Ленинградский пр-т, д. 52',
                'contract_city' => 'Москва',
                'emergency_contact_name' => 'Павел Петров',
                'emergency_contact_phone' => '+7 999 403-03-03',
                'inn' => '771012345678',
                'snils' => '223-344-556 78',
                'tax_status' => 'физическое лицо',
                'is_self_employed' => false,
                'bank_recipient_name' => 'Петрова Ирина Алексеевна',
                'bank_name' => 'Т-Банк',
                'bank_bik' => '044525974',
                'bank_account' => '40817810000087654321',
                'card_number' => '5536 91** **** 1122',
                'notes' => 'Основной заказчик по уходу за матерью.',
            ],
        ];

        foreach ($profiles as $email => $profileData) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                continue;
            }

            $user->contractProfile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );
        }

        $documents = [
            ['email' => 'marina@sidelka.test', 'document_type' => 'passport', 'title' => 'Паспорт РФ', 'document_number' => '4508 123456', 'issued_at' => '2016-05-20', 'expires_at' => null, 'verification_status' => 'verified', 'notes' => 'Паспорт проверен администратором.'],
            ['email' => 'marina@sidelka.test', 'document_type' => 'medical_book', 'title' => 'Медицинская книжка', 'document_number' => 'MK-2026-001', 'issued_at' => '2026-01-10', 'expires_at' => '2026-12-31', 'verification_status' => 'verified', 'notes' => 'Допуск к уходу подтвержден.'],
            ['email' => 'marina@sidelka.test', 'document_type' => 'snils', 'title' => 'СНИЛС', 'document_number' => '112-233-445 95', 'issued_at' => null, 'expires_at' => null, 'verification_status' => 'uploaded', 'notes' => 'Ожидает финальной проверки.'],
            ['email' => 'irina@sidelka.test', 'document_type' => 'passport', 'title' => 'Паспорт клиента', 'document_number' => '4510 654321', 'issued_at' => '2018-11-14', 'expires_at' => null, 'verification_status' => 'verified', 'notes' => 'Данные для договора подтверждены.'],
            ['email' => 'irina@sidelka.test', 'document_type' => 'power_of_attorney', 'title' => 'Доверенность от родственников', 'document_number' => 'DV-2026-19', 'issued_at' => '2026-06-01', 'expires_at' => '2027-06-01', 'verification_status' => 'uploaded', 'notes' => 'Добавлена для семейного доступа.'],
        ];

        foreach ($documents as $documentData) {
            $user = User::where('email', $documentData['email'])->first();
            if (! $user) {
                continue;
            }

            $user->documents()->create([
                'document_type' => $documentData['document_type'],
                'title' => $documentData['title'],
                'document_number' => $documentData['document_number'],
                'issued_at' => $documentData['issued_at'],
                'expires_at' => $documentData['expires_at'],
                'verification_status' => $documentData['verification_status'],
                'notes' => $documentData['notes'],
            ]);
        }
    }
}
