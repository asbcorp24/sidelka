<?php

namespace App\Services;

use App\Models\CrmTask;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CaregiverDocumentService
{
    public function blockingDocuments(User $caregiver): Collection
    {
        return $caregiver->documents()
            ->where('is_required', true)
            ->where('blocks_assignments', true)
            ->get()
            ->filter(fn (UserDocument $document) => $document->blocksCaregiver())
            ->values();
    }

    public function assertEligible(User $caregiver): void
    {
        $blocking = $this->blockingDocuments($caregiver);

        if ($blocking->isNotEmpty()) {
            throw ValidationException::withMessages([
                'documents' => 'Сиделка временно не допущена к новым сменам. Требуют проверки или обновления: '
                    . $blocking->pluck('title')->implode(', ') . '.',
            ]);
        }
    }

    public function createExpiryTasks(): int
    {
        $created = 0;
        $documents = UserDocument::query()
            ->whereHas('user', fn ($query) => $query->where('role', 'caregiver'))
            ->whereNotNull('expires_at')
            ->where('is_required', true)
            ->with('user')
            ->get();

        foreach ($documents as $document) {
            $days = today()->diffInDays($document->expires_at, false);

            if ($days <= 30 && $days > 14 && ! $document->reminder_30_at) {
                $created += $this->createTask($document, '30', 'normal', $document->expires_at->copy()->subDays(25));
                $document->update(['reminder_30_at' => now()]);
            }
            if ($days <= 14 && $days > 3 && ! $document->reminder_14_at) {
                $created += $this->createTask($document, '14', 'high', $document->expires_at->copy()->subDays(10));
                $document->update(['reminder_14_at' => now()]);
            }
            if ($days <= 3 && $days >= 0 && ! $document->reminder_3_at) {
                $created += $this->createTask($document, '3', 'urgent', $document->expires_at);
                $document->update(['reminder_3_at' => now()]);
            }
            if ($days < 0 && ! $document->expired_task_at) {
                $created += $this->createTask($document, 'expired', 'urgent', now());
                $document->update(['expired_task_at' => now()]);
            }
        }

        return $created;
    }

    private function createTask(UserDocument $document, string $stage, string $priority, $dueAt): int
    {
        $assignee = User::query()
            ->where(function ($query) {
                $query->where('role', 'admin')
                    ->orWhere(function ($staff) {
                        $staff->where('role', 'crm')
                            ->where('staff_active', true)
                            ->whereIn('staff_role', ['coordinator', 'supervisor', 'manager']);
                    });
            })
            ->orderByRaw("CASE WHEN role = 'crm' THEN 0 ELSE 1 END")
            ->first();

        $task = CrmTask::firstOrCreate([
            'dedup_key' => 'document:' . $document->id . ':' . $stage,
        ], [
            'person_user_id' => $document->user_id,
            'assigned_to_id' => $assignee?->id,
            'created_by_id' => null,
            'title' => $stage === 'expired'
                ? 'Истёк документ сиделки: ' . $document->title
                : 'Обновить документ сиделки: ' . $document->title,
            'description' => 'Сиделка: ' . $document->user->name . '. Срок действия: '
                . $document->expires_at->format('d.m.Y') . '.',
            'category' => 'caregiver_document',
            'source_type' => UserDocument::class,
            'source_id' => $document->id,
            'status' => 'open',
            'priority' => $priority,
            'due_at' => $dueAt,
        ]);

        return $task->wasRecentlyCreated ? 1 : 0;
    }
}
