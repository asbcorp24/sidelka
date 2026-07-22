<?php

namespace App\Console\Commands;

use App\Services\CaregiverDocumentService;
use Illuminate\Console\Command;

class CreateCaregiverDocumentTasks extends Command
{
    protected $signature = 'caregivers:check-documents';
    protected $description = 'Создать задачи CRM по истекающим и просроченным документам сиделок';

    public function handle(CaregiverDocumentService $documents): int
    {
        $count = $documents->createExpiryTasks();
        $this->info('Создано задач: ' . $count);

        return self::SUCCESS;
    }
}
