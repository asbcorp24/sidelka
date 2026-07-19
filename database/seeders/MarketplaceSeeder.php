<?php

namespace Database\Seeders;

use App\Models\AvailabilitySlot;
use App\Models\CaregiverProfile;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\NewsPost;
use App\Models\Order;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MarketplaceSeeder extends Seeder
{
    public function run()
    {
        DB::table('messages')->delete();
        DB::table('conversations')->delete();
        DB::table('reviews')->delete();
        DB::table('order_service')->delete();
        DB::table('orders')->delete();
        DB::table('availability_slots')->delete();
        DB::table('caregiver_profile_service')->delete();
        DB::table('caregiver_profiles')->delete();
        DB::table('news_posts')->delete();
        DB::table('services')->delete();
        DB::table('users')->delete();

        $password = Hash::make('password');

        $admin = User::create([
            'name' => 'Администратор',
            'email' => 'admin@sidelka.test',
            'role' => 'admin',
            'phone' => '+7 999 000-00-00',
            'city' => 'Москва',
            'about' => 'Управляет модерацией сиделок, заявок, отзывов и новостей.',
            'rating' => 0,
            'reviews_count' => 0,
            'is_verified' => true,
            'last_seen_at' => now(),
            'password' => $password,
        ]);

        $services = collect([
            ['name' => 'Уколы и инъекции', 'category' => 'Медицинский уход', 'description' => 'Внутримышечные и подкожные инъекции по назначению врача.', 'requires_medical_training' => true, 'hourly_surcharge' => 250],
            ['name' => 'Контроль лекарств', 'category' => 'Медицинский уход', 'description' => 'Напоминание и контроль приема лекарств.', 'requires_medical_training' => true, 'hourly_surcharge' => 100],
            ['name' => 'Измерение давления и сахара', 'category' => 'Медицинский уход', 'description' => 'Базовый контроль показателей и фиксация изменений.', 'requires_medical_training' => true, 'hourly_surcharge' => 150],
            ['name' => 'Гигиенический уход', 'category' => 'Бытовой уход', 'description' => 'Смена белья, уход за телом, помощь с туалетом.', 'requires_medical_training' => false, 'hourly_surcharge' => 220],
            ['name' => 'Уборка комнаты', 'category' => 'Бытовой уход', 'description' => 'Поддерживающая уборка и дезинфекция.', 'requires_medical_training' => false, 'hourly_surcharge' => 120],
            ['name' => 'Вынос отходов и фекалий', 'category' => 'Бытовой уход', 'description' => 'Аккуратный уход за лежачими пациентами.', 'requires_medical_training' => false, 'hourly_surcharge' => 180],
            ['name' => 'Приготовление еды', 'category' => 'Домашняя помощь', 'description' => 'Домашняя еда с учетом ограничений по питанию.', 'requires_medical_training' => false, 'hourly_surcharge' => 110],
            ['name' => 'Покупка продуктов и лекарств', 'category' => 'Домашняя помощь', 'description' => 'Закупка необходимого и контроль запасов дома.', 'requires_medical_training' => false, 'hourly_surcharge' => 90],
            ['name' => 'Сопровождение на прогулке', 'category' => 'Сопровождение', 'description' => 'Прогулки, поликлиника, поездки на процедуры.', 'requires_medical_training' => false, 'hourly_surcharge' => 150],
            ['name' => 'Сопровождение в больницу', 'category' => 'Сопровождение', 'description' => 'Сопровождение на обследования и приемы врача.', 'requires_medical_training' => false, 'hourly_surcharge' => 180],
            ['name' => 'Ночной присмотр', 'category' => 'Смена', 'description' => 'Ночное дежурство и контроль состояния.', 'requires_medical_training' => false, 'hourly_surcharge' => 300],
            ['name' => 'Сиделка с проживанием', 'category' => 'Смена', 'description' => 'Длительное проживание и постоянный контроль состояния.', 'requires_medical_training' => false, 'hourly_surcharge' => 350],
        ])->map(fn ($service) => Service::create($service));

        $caregiverDefinitions = [
            [
                'user' => ['name' => 'Марина Соколова', 'email' => 'marina@sidelka.test', 'role' => 'caregiver', 'phone' => '+7 999 100-10-10', 'city' => 'Москва', 'about' => 'Опытная сиделка после инсульта и для лежачих пациентов.', 'rating' => 4.9, 'reviews_count' => 18, 'is_verified' => true, 'last_seen_at' => now()->subMinutes(12), 'password' => $password],
                'profile' => ['experience_years' => 8, 'hourly_rate_from' => 650, 'shift_rate_from' => 5400, 'employment_format' => 'hourly_and_shift', 'education' => 'Медицинская сестра', 'bio' => 'Работаю с пожилыми, послеоперационными пациентами и людьми с деменцией.', 'medical_skills' => 'Инъекции, контроль давления, перевязки, контроль лекарств.', 'household_skills' => 'Уборка, питание, гигиена, смена белья.', 'ready_for_night' => true, 'ready_for_live_in' => false, 'documents_verified' => true],
                'services' => [
                    'can_do' => ['Уколы и инъекции', 'Контроль лекарств', 'Измерение давления и сахара', 'Гигиенический уход', 'Вынос отходов и фекалий', 'Ночной присмотр'],
                    'cannot_do' => ['Приготовление еды'],
                ],
                'slots' => [[1, '08:00', '18:00'], [3, '08:00', '18:00'], [5, '10:00', '22:00'], [6, '20:00', '08:00']],
            ],
            [
                'user' => ['name' => 'Елена Романова', 'email' => 'elena@sidelka.test', 'role' => 'caregiver', 'phone' => '+7 999 200-20-20', 'city' => 'Москва', 'about' => 'Сиделка-компаньонка с хорошим бытовым уходом.', 'rating' => 4.8, 'reviews_count' => 24, 'is_verified' => true, 'last_seen_at' => now()->subHour(), 'password' => $password],
                'profile' => ['experience_years' => 6, 'hourly_rate_from' => 550, 'shift_rate_from' => 4900, 'employment_format' => 'hourly', 'education' => 'Курсы патронажного ухода', 'bio' => 'Сильна в уходе по дому, приготовлении еды и сопровождении.', 'medical_skills' => 'Контроль приема препаратов, базовый мониторинг состояния.', 'household_skills' => 'Готовка, уборка, прогулки, досуг.', 'ready_for_night' => false, 'ready_for_live_in' => true, 'documents_verified' => true],
                'services' => [
                    'can_do' => ['Гигиенический уход', 'Уборка комнаты', 'Приготовление еды', 'Покупка продуктов и лекарств', 'Сопровождение на прогулке', 'Сопровождение в больницу', 'Сиделка с проживанием'],
                    'cannot_do' => ['Уколы и инъекции', 'Контроль лекарств', 'Измерение давления и сахара'],
                ],
                'slots' => [[1, '09:00', '17:00'], [2, '09:00', '17:00'], [4, '09:00', '17:00'], [6, '09:00', '15:00']],
            ],
            [
                'user' => ['name' => 'Оксана Белова', 'email' => 'oksana@sidelka.test', 'role' => 'caregiver', 'phone' => '+7 999 300-30-30', 'city' => 'Москва', 'about' => 'Ночная сиделка для пациентов после операций.', 'rating' => 4.7, 'reviews_count' => 11, 'is_verified' => false, 'last_seen_at' => now()->subMinutes(35), 'password' => $password],
                'profile' => ['experience_years' => 4, 'hourly_rate_from' => 500, 'shift_rate_from' => 4700, 'employment_format' => 'night_shift', 'education' => 'Курсы первой помощи', 'bio' => 'Часто беру ночные смены и выходные.', 'medical_skills' => 'Контроль сна, состояния, базовый уход.', 'household_skills' => 'Помощь с гигиеной, смена белья.', 'ready_for_night' => true, 'ready_for_live_in' => false, 'documents_verified' => false],
                'services' => [
                    'can_do' => ['Гигиенический уход', 'Вынос отходов и фекалий', 'Ночной присмотр'],
                    'cannot_do' => ['Уколы и инъекции', 'Контроль лекарств', 'Измерение давления и сахара', 'Приготовление еды'],
                ],
                'slots' => [[5, '20:00', '08:00'], [6, '20:00', '08:00'], [0, '20:00', '08:00']],
            ],
            [
                'user' => ['name' => 'Наталья Громова', 'email' => 'natalya@sidelka.test', 'role' => 'caregiver', 'phone' => '+7 999 310-31-31', 'city' => 'Москва', 'about' => 'Специализируюсь на длительном уходе с проживанием.', 'rating' => 4.95, 'reviews_count' => 31, 'is_verified' => true, 'last_seen_at' => now()->subMinutes(7), 'password' => $password],
                'profile' => ['experience_years' => 12, 'hourly_rate_from' => 750, 'shift_rate_from' => 6800, 'employment_format' => 'live_in', 'education' => 'Фельдшер', 'bio' => 'Беру сложные случаи, лежачих пациентов и уход после инсульта.', 'medical_skills' => 'Инъекции, перевязки, контроль сахара и давления.', 'household_skills' => 'Полный уход по дому, питание, режим дня.', 'ready_for_night' => true, 'ready_for_live_in' => true, 'documents_verified' => true],
                'services' => [
                    'can_do' => ['Уколы и инъекции', 'Контроль лекарств', 'Измерение давления и сахара', 'Гигиенический уход', 'Вынос отходов и фекалий', 'Приготовление еды', 'Покупка продуктов и лекарств', 'Сиделка с проживанием'],
                    'cannot_do' => [],
                ],
                'slots' => [[0, '00:00', '23:59'], [1, '00:00', '23:59'], [2, '00:00', '23:59']],
            ],
            [
                'user' => ['name' => 'Татьяна Лебедева', 'email' => 'tatyana@sidelka.test', 'role' => 'caregiver', 'phone' => '+7 999 320-32-32', 'city' => 'Москва', 'about' => 'Аккуратный дневной уход и сопровождение по городу.', 'rating' => 4.6, 'reviews_count' => 9, 'is_verified' => true, 'last_seen_at' => now()->subMinutes(55), 'password' => $password],
                'profile' => ['experience_years' => 5, 'hourly_rate_from' => 520, 'shift_rate_from' => 4500, 'employment_format' => 'day_shift', 'education' => 'Курсы сиделок', 'bio' => 'Хорошо подхожу для активных пожилых клиентов и реабилитации дома.', 'medical_skills' => 'Напоминание о лекарствах, контроль базовых показателей.', 'household_skills' => 'Прогулки, уборка, помощь с едой и покупками.', 'ready_for_night' => false, 'ready_for_live_in' => false, 'documents_verified' => true],
                'services' => [
                    'can_do' => ['Гигиенический уход', 'Уборка комнаты', 'Приготовление еды', 'Покупка продуктов и лекарств', 'Сопровождение на прогулке', 'Сопровождение в больницу'],
                    'cannot_do' => ['Уколы и инъекции'],
                ],
                'slots' => [[1, '07:00', '15:00'], [2, '07:00', '15:00'], [3, '07:00', '15:00'], [4, '07:00', '15:00']],
            ],
            [
                'user' => ['name' => 'Светлана Орлова', 'email' => 'svetlana@sidelka.test', 'role' => 'caregiver', 'phone' => '+7 999 330-33-33', 'city' => 'Химки', 'about' => 'Работаю в Химках и на севере Москвы.', 'rating' => 4.5, 'reviews_count' => 14, 'is_verified' => true, 'last_seen_at' => now()->subMinutes(16), 'password' => $password],
                'profile' => ['experience_years' => 7, 'hourly_rate_from' => 560, 'shift_rate_from' => 5000, 'employment_format' => 'hourly', 'education' => 'Медицинские курсы', 'bio' => 'Уход после операций и помощь пожилым дома.', 'medical_skills' => 'Контроль лекарств, давление, сахар.', 'household_skills' => 'Гигиена, уборка, питание.', 'ready_for_night' => true, 'ready_for_live_in' => false, 'documents_verified' => true],
                'services' => [
                    'can_do' => ['Контроль лекарств', 'Измерение давления и сахара', 'Гигиенический уход', 'Уборка комнаты', 'Приготовление еды', 'Ночной присмотр'],
                    'cannot_do' => ['Уколы и инъекции'],
                ],
                'slots' => [[1, '12:00', '22:00'], [3, '12:00', '22:00'], [5, '12:00', '22:00']],
            ],
        ];

        $caregivers = collect();
        foreach ($caregiverDefinitions as $definition) {
            $caregivers->push($this->createCaregiver($definition, $services));
        }

        $clients = collect([
            User::create(['name' => 'Ирина Петрова', 'email' => 'irina@sidelka.test', 'role' => 'client', 'phone' => '+7 999 400-40-40', 'city' => 'Москва', 'about' => 'Ищу сиделку для мамы после перелома шейки бедра.', 'rating' => 4.8, 'reviews_count' => 5, 'is_verified' => true, 'last_seen_at' => now()->subMinutes(5), 'password' => $password]),
            User::create(['name' => 'Дмитрий Волков', 'email' => 'dmitry@sidelka.test', 'role' => 'client', 'phone' => '+7 999 500-50-50', 'city' => 'Москва', 'about' => 'Нужен ночной присмотр для отца после операции.', 'rating' => 4.6, 'reviews_count' => 3, 'is_verified' => true, 'last_seen_at' => now()->subMinutes(20), 'password' => $password]),
            User::create(['name' => 'Ольга Матвеева', 'email' => 'olga@sidelka.test', 'role' => 'client', 'phone' => '+7 999 600-60-60', 'city' => 'Химки', 'about' => 'Ищу сиделку с проживанием для бабушки.', 'rating' => 4.9, 'reviews_count' => 7, 'is_verified' => true, 'last_seen_at' => now()->subMinutes(42), 'password' => $password]),
            User::create(['name' => 'Сергей Демин', 'email' => 'sergey@sidelka.test', 'role' => 'client', 'phone' => '+7 999 700-70-70', 'city' => 'Москва', 'about' => 'Нужна дневная помощь после выписки из больницы.', 'rating' => 4.4, 'reviews_count' => 2, 'is_verified' => false, 'last_seen_at' => now()->subHours(2), 'password' => $password]),
        ]);

        $orders = collect([
            $this->createOrder($clients[0], $caregivers[0], [
                'title' => 'Дневная сиделка после перелома',
                'description' => 'Нужна помощь маме 78 лет: гигиена, лекарства, легкая уборка, помощь в передвижении.',
                'city' => 'Москва',
                'address' => 'м. Сокол',
                'schedule_type' => 'daily',
                'status' => 'in_chat',
                'payment_status' => 'held',
                'hourly_budget' => 700,
                'patient_age' => 78,
                'patient_name' => 'Тамара Ивановна',
                'special_requirements' => 'Важно умение аккуратно пересаживать с кровати на кресло.',
                'starts_at' => Carbon::parse('2026-07-21 09:00:00'),
                'ends_at' => Carbon::parse('2026-07-21 17:00:00'),
            ], ['Гигиенический уход', 'Контроль лекарств', 'Уборка комнаты'], $services),
            $this->createOrder($clients[1], null, [
                'title' => 'Ночной присмотр после операции',
                'description' => 'Нужна сиделка на 3 ночи подряд с контролем лекарств и общим присмотром.',
                'city' => 'Москва',
                'address' => 'м. Октябрьское поле',
                'schedule_type' => 'hourly',
                'status' => 'published',
                'payment_status' => 'pending',
                'hourly_budget' => 650,
                'patient_age' => 81,
                'patient_name' => 'Владимир Сергеевич',
                'special_requirements' => 'Желателен опыт ночных смен.',
                'starts_at' => Carbon::parse('2026-07-22 20:00:00'),
                'ends_at' => Carbon::parse('2026-07-23 08:00:00'),
            ], ['Ночной присмотр', 'Контроль лекарств', 'Вынос отходов и фекалий'], $services),
            $this->createOrder($clients[0], $caregivers[1], [
                'title' => 'Компаньонка с готовкой и прогулками',
                'description' => 'Нужно проводить по 4 часа трижды в неделю, готовить и выводить на прогулку.',
                'city' => 'Москва',
                'address' => 'м. Аэропорт',
                'schedule_type' => 'hourly',
                'status' => 'completed',
                'payment_status' => 'released',
                'hourly_budget' => 600,
                'patient_age' => 74,
                'patient_name' => 'Галина Павловна',
                'special_requirements' => 'Спокойный характер и умение поддержать разговор.',
                'starts_at' => Carbon::parse('2026-07-15 10:00:00'),
                'ends_at' => Carbon::parse('2026-07-15 14:00:00'),
                'confirmed_at' => Carbon::parse('2026-07-15 15:00:00'),
            ], ['Приготовление еды', 'Сопровождение на прогулке', 'Уборка комнаты'], $services),
            $this->createOrder($clients[2], $caregivers[3], [
                'title' => 'Сиделка с проживанием на 2 недели',
                'description' => 'Нужен полный уход за бабушкой после инсульта, включая режим лекарств и питание.',
                'city' => 'Химки',
                'address' => 'ул. Молодежная',
                'schedule_type' => 'daily',
                'status' => 'matched',
                'payment_status' => 'held',
                'hourly_budget' => 820,
                'patient_age' => 86,
                'patient_name' => 'Нина Петровна',
                'special_requirements' => 'Нужен опыт с лежачими пациентами и проживанием.',
                'starts_at' => Carbon::parse('2026-07-24 09:00:00'),
                'ends_at' => Carbon::parse('2026-08-07 09:00:00'),
            ], ['Контроль лекарств', 'Измерение давления и сахара', 'Гигиенический уход', 'Сиделка с проживанием'], $services),
            $this->createOrder($clients[3], null, [
                'title' => 'Помощь после выписки из стационара',
                'description' => 'Нужна дневная сиделка на 5 дней: лекарства, прогулка по квартире, контроль состояния.',
                'city' => 'Москва',
                'address' => 'м. Щукинская',
                'schedule_type' => 'daily',
                'status' => 'published',
                'payment_status' => 'pending',
                'hourly_budget' => 580,
                'patient_age' => 67,
                'patient_name' => 'Алексей Петрович',
                'special_requirements' => 'Желателен опыт реабилитации после операции.',
                'starts_at' => Carbon::parse('2026-07-23 10:00:00'),
                'ends_at' => Carbon::parse('2026-07-23 18:00:00'),
            ], ['Контроль лекарств', 'Гигиенический уход', 'Сопровождение на прогулке'], $services),
            $this->createOrder($clients[1], $caregivers[2], [
                'title' => 'Ночная сиделка на выходные',
                'description' => 'Нужен присмотр в пятницу и субботу ночью, помощь с туалетом и контроль самочувствия.',
                'city' => 'Москва',
                'address' => 'м. Полежаевская',
                'schedule_type' => 'night',
                'status' => 'in_progress',
                'payment_status' => 'held',
                'hourly_budget' => 620,
                'patient_age' => 79,
                'patient_name' => 'Николай Федорович',
                'special_requirements' => 'Спокойная ночная сиделка без лишней суеты.',
                'starts_at' => Carbon::parse('2026-07-19 21:00:00'),
                'ends_at' => Carbon::parse('2026-07-20 08:00:00'),
            ], ['Ночной присмотр', 'Вынос отходов и фекалий', 'Гигиенический уход'], $services),
        ]);

        $conversationA = Conversation::create([
            'order_id' => $orders[0]->id,
            'client_id' => $clients[0]->id,
            'caregiver_id' => $caregivers[0]->id,
            'status' => 'active',
        ]);

        $conversationB = Conversation::create([
            'order_id' => $orders[3]->id,
            'client_id' => $clients[2]->id,
            'caregiver_id' => $caregivers[3]->id,
            'status' => 'requested',
        ]);

        $conversationC = Conversation::create([
            'order_id' => $orders[5]->id,
            'client_id' => $clients[1]->id,
            'caregiver_id' => $caregivers[2]->id,
            'status' => 'active',
        ]);

        $messages = [
            [$conversationA, $clients[0], 'Здравствуйте. Нам важно, чтобы вы могли помочь утром с гигиеной и лекарствами.', now()->subMinutes(18)],
            [$conversationA, $caregivers[0], 'Да, это мой профиль работы. Могу приехать на пробную смену 21 июля к 9:00.', now()->subMinutes(14)],
            [$conversationA, $clients[0], 'Отлично. Тогда резервирую оплату на первую смену через сайт.', now()->subMinutes(11)],
            [$conversationB, $clients[2], 'Добрый день. Ищем сиделку с проживанием минимум на две недели.', now()->subHours(5)],
            [$conversationB, $caregivers[3], 'Здравствуйте. Такой формат мне подходит, могу начать с 24 июля.', now()->subHours(4)],
            [$conversationC, $clients[1], 'Нужен спокойный ночной присмотр без медицинских манипуляций.', now()->subHours(7)],
            [$conversationC, $caregivers[2], 'Поняла. Буду рядом всю ночь и помогу при необходимости.', now()->subHours(6)],
        ];

        foreach ($messages as [$conversation, $sender, $body, $readAt]) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $body,
                'read_at' => $readAt,
            ]);
        }

        $reviews = [
            ['order_id' => $orders[2]->id, 'author_id' => $clients[0]->id, 'subject_id' => $caregivers[1]->id, 'subject_role' => 'caregiver', 'rating' => 5, 'comment' => 'Очень спокойная и заботливая сиделка. Всегда приходила вовремя.', 'published_at' => now()->subDays(2)],
            ['order_id' => $orders[2]->id, 'author_id' => $caregivers[1]->id, 'subject_id' => $clients[0]->id, 'subject_role' => 'client', 'rating' => 5, 'comment' => 'Четкая коммуникация, своевременная оплата, хорошие условия работы.', 'published_at' => now()->subDays(2)],
            ['order_id' => $orders[0]->id, 'author_id' => $clients[0]->id, 'subject_id' => $caregivers[0]->id, 'subject_role' => 'caregiver', 'rating' => 5, 'comment' => 'Быстро вышла на связь и подробно расспросила о состоянии пациента.', 'published_at' => now()->subDay()],
            ['order_id' => $orders[3]->id, 'author_id' => $clients[2]->id, 'subject_id' => $caregivers[3]->id, 'subject_role' => 'caregiver', 'rating' => 5, 'comment' => 'Сильная анкета и очень уверенное общение, видно опыт.', 'published_at' => now()->subHours(18)],
            ['order_id' => $orders[3]->id, 'author_id' => $caregivers[3]->id, 'subject_id' => $clients[2]->id, 'subject_role' => 'client', 'rating' => 5, 'comment' => 'Клиент подробно описал состояние пациентки и быстро внес предоплату.', 'published_at' => now()->subHours(18)],
            ['order_id' => $orders[5]->id, 'author_id' => $clients[1]->id, 'subject_id' => $caregivers[2]->id, 'subject_role' => 'caregiver', 'rating' => 4, 'comment' => 'Хорошо подходит для ночных смен и аккуратно ведет себя с пациентом.', 'published_at' => now()->subHours(8)],
        ];

        foreach ($reviews as $review) {
            Review::create($review);
        }

        $posts = [
            ['title' => 'Как безопасно выбрать сиделку для пожилого родственника', 'excerpt' => 'Памятка для семей, которые ищут сиделку впервые.', 'body' => 'Смотрите на опыт, проверку документов, отзывы, список услуг и ясность переписки до выхода на смену.', 'published_at' => now()->subDays(5)],
            ['title' => 'Что должно быть в анкете сиделки, чтобы чаще получать отклики', 'excerpt' => 'Советы для сиделок по заполнению профиля и календаря.', 'body' => 'Подробно перечисляйте навыки, отмечайте ночные смены, указывайте минимальную ставку и актуальный график.', 'published_at' => now()->subDays(4)],
            ['title' => 'Как работает безопасная оплата через сайт', 'excerpt' => 'Объясняем схему холда и подтверждения выполненной смены.', 'body' => 'Клиент оплачивает заказ на сайт, деньги резервируются и переходят сиделке после подтверждения выполненной работы.', 'published_at' => now()->subDays(3)],
            ['title' => 'Какие манипуляции должны быть доступны только сиделкам с медподготовкой', 'excerpt' => 'Разделяем бытовой уход и медицинские услуги в анкете.', 'body' => 'Инъекции, контроль сахара и часть медицинских процедур лучше отмечать только при наличии соответствующей подготовки.', 'published_at' => now()->subDays(2)],
            ['title' => 'Как клиенту правильно описать заявку, чтобы быстрее получить совпадения', 'excerpt' => 'Чем точнее заполнена заявка, тем лучше автоподбор.', 'body' => 'Указывайте возраст пациента, график, город, список услуг и важные ограничения для сиделки.', 'published_at' => now()->subDay()],
        ];

        foreach ($posts as $post) {
            NewsPost::create([
                'title' => $post['title'],
                'slug' => Str::slug($post['title']) . '-' . Str::lower(Str::random(4)),
                'excerpt' => $post['excerpt'],
                'body' => $post['body'],
                'published_at' => $post['published_at'],
                'is_published' => true,
            ]);
        }
    }

    private function createCaregiver(array $definition, $services): User
    {
        $user = User::create($definition['user']);
        $profile = $user->caregiverProfile()->create($definition['profile']);

        $syncPayload = [];
        foreach ($definition['services']['can_do'] as $serviceName) {
            $service = $services->firstWhere('name', $serviceName);
            $syncPayload[$service->id] = ['capability_status' => 'can_do'];
        }

        foreach ($definition['services']['cannot_do'] as $serviceName) {
            $service = $services->firstWhere('name', $serviceName);
            $syncPayload[$service->id] = ['capability_status' => 'cannot_do'];
        }

        $profile->services()->sync($syncPayload);

        foreach ($definition['slots'] as $slot) {
            AvailabilitySlot::create([
                'caregiver_profile_id' => $profile->id,
                'weekday' => $slot[0],
                'starts_at' => $slot[1],
                'ends_at' => $slot[2],
                'notes' => 'Регулярная доступность',
            ]);
        }

        return $user;
    }

    private function createOrder(User $client, ?User $caregiver, array $data, array $serviceNames, $services): Order
    {
        $order = Order::create(array_merge($data, [
            'client_id' => $client->id,
            'caregiver_id' => $caregiver?->id,
        ]));

        $order->services()->sync($services->whereIn('name', $serviceNames)->pluck('id'));

        return $order;
    }
}
