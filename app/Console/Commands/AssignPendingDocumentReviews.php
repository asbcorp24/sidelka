<?php

namespace App\Console\Commands;

use App\Models\UserDocument;
use App\Services\CaregiverDocumentService;
use Illuminate\Console\Command;

class AssignPendingDocumentReviews extends Command
{
    protected $signature = 'caregivers:assign-document-reviews';

    protected $description = 'Назначить персональные задачи CRM по непроверенным документам сиделок';

    public function handle(CaregiverDocumentService $documents): int
    {
        $processed = 0;

        UserDocument::query()
            ->whereHas('user', fn ($query) => $query->where('role', 'caregiver'))
            ->whereIn('verification_status', [
                UserDocument::STATUS_UPLOADED,
                UserDocument::STATUS_PENDING,
            ])
            ->with('user')
            ->orderBy('id')
            ->chunkById(100, function ($items) use ($documents, &$processed) {
                foreach ($items as $document) {
                    $documents->createReviewTask($document);
                    $processed++;
                }
            });

        $this->info('Проверено непроверенных документов: ' . $processed . '.');

        return self::SUCCESS;
    }
}
