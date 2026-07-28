<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PlatformSettings
{
    private ?array $cache = null;

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            if (! Schema::hasTable('platform_settings')) {
                return $this->cache = [];
            }

            return $this->cache = PlatformSetting::query()
                ->pluck('value', 'key')
                ->map(fn ($value) => $this->decodeValue($value))
                ->all();
        } catch (Throwable) {
            return $this->cache = [];
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $this->encodeValue($value)]
            );
        }

        $this->cache = null;
    }

    public function seoPayload(): array
    {
        return [
            'site_name' => (string) $this->get('seo_site_name', 'Sidelka24'),
            'default_title' => (string) $this->get('seo_default_title', 'Sidelka24'),
            'default_description' => (string) $this->get('seo_default_description', 'Подбор сиделок, безопасная оплата, календарь смен и проверенные анкеты.'),
            'default_keywords' => (string) $this->get('seo_default_keywords', 'сиделка, уход, сиделка на дом, медицинская сиделка'),
            'robots' => (string) $this->get('seo_robots', 'index,follow'),
            'og_image' => (string) $this->get('seo_og_image', ''),
            'home_title' => (string) $this->get('seo_home_title', ''),
            'home_description' => (string) $this->get('seo_home_description', ''),
            'caregivers_title' => (string) $this->get('seo_caregivers_title', ''),
            'caregivers_description' => (string) $this->get('seo_caregivers_description', ''),
            'news_title' => (string) $this->get('seo_news_title', ''),
            'news_description' => (string) $this->get('seo_news_description', ''),
        ];
    }

    public function bankPayload(): array
    {
        return [
            'provider' => (string) $this->get('bank_provider', 'sber'),
            'enabled' => (bool) $this->get('bank_enabled', false),
            'mode' => (string) $this->get('bank_mode', 'test'),
            'base_url' => (string) $this->get('bank_base_url', config('sber.base_url')),
            'username' => (string) $this->get('bank_username', config('sber.username')),
            'password' => (string) $this->get('bank_password', config('sber.password')),
            'description_prefix' => (string) $this->get('bank_description_prefix', config('sber.description_prefix')),
            'timeout' => (int) $this->get('bank_timeout', (int) config('sber.timeout', 20)),
            'callback_email' => (string) $this->get('bank_callback_email', ''),
            'merchant_name' => (string) $this->get('bank_merchant_name', 'Sidelka24'),
        ];
    }

    public function legalPayload(): array
    {
        return [
            'name' => (string) $this->get('legal_company_name', config('legal.company.name')),
            'short_name' => (string) $this->get('legal_company_short_name', config('legal.company.short_name')),
            'inn' => (string) $this->get('legal_company_inn', config('legal.company.inn')),
            'kpp' => (string) $this->get('legal_company_kpp', config('legal.company.kpp')),
            'ogrn' => (string) $this->get('legal_company_ogrn', config('legal.company.ogrn')),
            'address' => (string) $this->get('legal_company_address', config('legal.company.address')),
            'email' => (string) $this->get('legal_company_email', config('legal.company.email')),
            'phone' => (string) $this->get('legal_company_phone', config('legal.company.phone')),
            'bank_name' => (string) $this->get('legal_company_bank_name', config('legal.company.bank_name')),
            'bank_bik' => (string) $this->get('legal_company_bank_bik', config('legal.company.bank_bik')),
            'bank_account' => (string) $this->get('legal_company_bank_account', config('legal.company.bank_account')),
            'correspondent_account' => (string) $this->get('legal_company_correspondent_account', config('legal.company.correspondent_account')),
            'signatory_name' => (string) $this->get('legal_company_signatory_name', config('legal.company.signatory_name')),
            'signatory_position' => (string) $this->get('legal_company_signatory_position', config('legal.company.signatory_position')),
            'signatory_basis' => (string) $this->get('legal_company_signatory_basis', config('legal.company.signatory_basis')),
        ];
    }

    public function crmPayload(): array
    {
        $defaults = $this->defaultCrmTemplates();
        $catalog = $this->get('crm_message_templates_catalog');

        if (is_array($catalog) && $catalog !== []) {
            return $this->normalizeCrmTemplates($catalog, $defaults);
        }

        $legacyMap = [
            'client_intro' => 'crm_template_client_intro',
            'client_follow_up' => 'crm_template_client_follow_up',
            'caregiver_offer' => 'crm_template_caregiver_offer',
            'caregiver_docs' => 'crm_template_caregiver_docs',
            'urgent_case' => 'crm_template_urgent_case',
        ];

        foreach ($defaults as $key => $template) {
            if (isset($legacyMap[$key])) {
                $defaults[$key]['text'] = (string) $this->get($legacyMap[$key], $template['text']);
            }
        }

        return $defaults;
    }

    public function defaultCrmTemplates(): array
    {
        return [
            'client_intro' => ['title' => 'Клиент: первое сообщение', 'audience' => 'client', 'text' => 'Здравствуйте. Получили вашу заявку на подбор сиделки. Сейчас уточняем детали по графику, услугам и бюджету, после чего предложим подходящих кандидатов.'],
            'client_follow_up' => ['title' => 'Клиент: follow-up', 'audience' => 'client', 'text' => 'Возвращаюсь по вашей заявке. Подбор уже в работе, мы сверяем график, опыт и стоимость кандидатов. Дам следующее обновление в ближайшее время.'],
            'client_clarify_schedule' => ['title' => 'Клиент: уточнить график', 'audience' => 'client', 'text' => 'Чтобы быстрее подобрать сиделку, уточните, пожалуйста, даты, часы работы и нужен ли уход разово или на постоянной основе.'],
            'client_clarify_services' => ['title' => 'Клиент: уточнить услуги', 'audience' => 'client', 'text' => 'Подскажите, пожалуйста, какие именно услуги нужны: гигиена, приготовление еды, уборка, сопровождение, уколы, перевязки или другие задачи.'],
            'client_clarify_budget' => ['title' => 'Клиент: уточнить бюджет', 'audience' => 'client', 'text' => 'Чтобы предложить реалистичные варианты, подскажите комфортный бюджет за час или за смену. Так мы быстрее подберем подходящую сиделку.'],
            'client_candidates_ready' => ['title' => 'Клиент: есть кандидаты', 'audience' => 'client', 'text' => 'Подготовили несколько подходящих сиделок под ваш запрос. Можем показать анкеты по опыту, графику, медподготовке и стоимости.'],
            'client_need_documents' => ['title' => 'Клиент: нужны документы', 'audience' => 'client', 'text' => 'Для оформления договора и безопасного старта нам нужно заполнить данные клиента и пациента. После этого можно сразу запускать заказ в работу.'],
            'client_payment_reminder' => ['title' => 'Клиент: пополнение баланса', 'audience' => 'client', 'text' => 'Для подтверждения заказа нужно пополнить баланс на платформе. Деньги будут списываться по подтвержденным сменам, остаток сохранится на счете.'],
            'client_today_urgent' => ['title' => 'Клиент: срочно сегодня', 'audience' => 'client', 'text' => 'Понял, это срочный заказ на сегодня. Мы запускаем быстрый подбор по свободным сиделкам и вернемся с ответом сразу, как только найдем подтверждение.'],
            'client_repeat_order' => ['title' => 'Клиент: повтор заказа', 'audience' => 'client', 'text' => 'Можем быстро повторить прошлый график или продлить завершенный заказ. Если условия те же, мы запустим оформление без долгого согласования.'],
            'caregiver_offer' => ['title' => 'Сиделка: предложение заказа', 'audience' => 'caregiver', 'text' => 'Здравствуйте. Есть заявка в вашем городе. Подскажите, готовы ли рассмотреть смены по этому заказу и подтвердить доступность.'],
            'caregiver_docs' => ['title' => 'Сиделка: документы и доступность', 'audience' => 'caregiver', 'text' => 'Перед подтверждением заказа нужно проверить актуальность документов, реквизитов и календаря доступности. Пожалуйста, обновите карточку профиля.'],
            'caregiver_schedule_request' => ['title' => 'Сиделка: подтвердить слоты', 'audience' => 'caregiver', 'text' => 'Уточните, пожалуйста, какие даты и часы из предложенного графика вы можете взять в работу. Можно подтвердить не весь заказ, а только свои слоты.'],
            'caregiver_medical_check' => ['title' => 'Сиделка: медподготовка', 'audience' => 'caregiver', 'text' => 'В заказе есть медицинские манипуляции. Подтвердите, пожалуйста, что у вас есть соответствующая подготовка и вы готовы выполнять эти услуги.'],
            'caregiver_rate_confirmation' => ['title' => 'Сиделка: подтвердить ставку', 'audience' => 'caregiver', 'text' => 'Подтвердите, пожалуйста, что указанная ставка и условия смены вам подходят. После подтверждения сможем открыть чат с клиентом по заявке.'],
            'caregiver_replacement_offer' => ['title' => 'Сиделка: подмена в длинном заказе', 'audience' => 'caregiver', 'text' => 'Есть длинный заказ с отдельными свободными сменами. Если готовы взять часть дат как замена или подмена, подтвердите доступные слоты.'],
            'caregiver_purchase_report' => ['title' => 'Сиделка: покупки и отчет', 'audience' => 'caregiver', 'text' => 'Если по смене были покупки или дополнительные услуги, внесите их в отчет после работы. Это поможет корректно выставить счет клиенту.'],
            'caregiver_shift_reminder' => ['title' => 'Сиделка: напоминание о смене', 'audience' => 'caregiver', 'text' => 'Напоминаем о предстоящей смене. Проверьте адрес, время начала, список задач и будьте на связи, если потребуется уточнение по уходу.'],
            'caregiver_quality_followup' => ['title' => 'Сиделка: контроль качества', 'audience' => 'caregiver', 'text' => 'Спасибо за работу. После завершения смены не забудьте отметить выполненные задачи, изменения состояния подопечного и приложить отчет при необходимости.'],
            'urgent_case' => ['title' => 'Срочно сегодня', 'audience' => 'both', 'text' => 'Срочная заявка на сегодня. Нужен быстрый ответ по доступности, времени выезда и стоимости, чтобы сразу перевести заказ в работу.'],
        ];
    }

    private function normalizeCrmTemplates(array $templates, array $defaults): array
    {
        $normalized = [];

        foreach ($templates as $key => $template) {
            if (! is_array($template)) {
                continue;
            }

            $default = $defaults[$key] ?? ['title' => ucfirst(str_replace('_', ' ', (string) $key)), 'audience' => 'client', 'text' => ''];

            $normalized[$key] = [
                'title' => (string) ($template['title'] ?? $default['title']),
                'audience' => (string) ($template['audience'] ?? $default['audience']),
                'text' => (string) ($template['text'] ?? $default['text']),
            ];
        }

        foreach ($defaults as $key => $default) {
            if (! isset($normalized[$key])) {
                $normalized[$key] = $default;
            }
        }

        return $normalized;
    }

    private function encodeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value) || is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    private function decodeValue(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }
}
