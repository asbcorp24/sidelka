<?php

namespace Database\Seeders;

use App\Support\PlatformSettings;
use Illuminate\Database\Seeder;

class CrmTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $platformSettings = app(PlatformSettings::class);
        $templates = $platformSettings->defaultCrmTemplates();

        $platformSettings->setMany([
            'crm_message_templates_catalog' => $templates,
            'crm_template_client_intro' => $templates['client_intro']['text'],
            'crm_template_client_follow_up' => $templates['client_follow_up']['text'],
            'crm_template_caregiver_offer' => $templates['caregiver_offer']['text'],
            'crm_template_caregiver_docs' => $templates['caregiver_docs']['text'],
            'crm_template_urgent_case' => $templates['urgent_case']['text'],
        ]);
    }
}
