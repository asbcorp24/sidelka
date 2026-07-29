<?php

namespace App\Services;

use App\Models\CrmTask;
use App\Models\MarketplaceNotification;
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

    public function createReviewTask(UserDocument $document): ?CrmTask
    {
        $document->loadMissing('user');

        if (! $document->user?->isCaregiver()) {
            return null;
        }

        $assignee = $this->resolveReviewer();
        $priority = $document->is_required ? 'high' : 'normal';
        $dedupKey = 'document-review:' . $document->id;
        $task = CrmTask::where('dedup_key', $dedupKey)->first();
        $newAssignment = false;

        if (! $task) {
            $task = CrmTask::create([
                'dedup_key' => $dedupKey,
                'person_user_id' => $document->user_id,
                'assigned_to_id' => $assignee?->id,
                'created_by_id' => null,
                'title' => 'Проверить документ сиделки: ' . $document->title,
                'description' => 'Сиделка: ' . $document->user->name
                    . '. Откройте скан, проверьте реквизиты, срок действия и примите решение.',
                'category' => 'caregiver_document',
                'source_type' => UserDocument::class,
                'source_id' => $document->id,
                'status' => 'open',
                'priority' => $priority,
                'due_at' => now()->addDay(),
            ]);
            $newAssignment = true;
        } elseif ($task->status !== 'open' || ! $task->assigned_to_id) {
            $task->update([
                'assigned_to_id' => $task->assigned_to_id ?: $assignee?->id,
                'status' => 'open',
                'priority' => $priority,
                'due_at' => now()->addDay(),
                'completed_at' => null,
            ]);
            $newAssignment = true;
        }

        if ($document->verification_status === UserDocument::STATUS_UPLOADED) {
            $document->update(['verification_status' => UserDocument::STATUS_PENDING]);
        }

        $assignedUser = $task->assignedTo()->first() ?: $assignee;
        if ($newAssignment && $assignedUser) {
            $this->notifyAssignee(
                $assignedUser,
                'document.review_assigned',
                'Назначена проверка документа',
                'Сиделка ' . $document->user->name . ' загрузила документ «' . $document->title . '».',
                $task,
            );
        }

        return $task;
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
                $created += $this->createExpiryTask(
                    $document,
                    '30',
                    'normal',
                    $document->expires_at->copy()->subDays(14)->endOfDay(),
                );
                $document->update(['reminder_30_at' => now()]);
            }

            if ($days <= 14 && $days > 3 && ! $document->reminder_14_at) {
                $created += $this->createExpiryTask(
                    $document,
                    '14',
                    'high',
                    $document->expires_at->copy()->subDays(3)->endOfDay(),
                );
                $document->update(['reminder_14_at' => now()]);
            }

            if ($days <= 3 && $days >= 0 && ! $document->reminder_3_at) {
                $created += $this->createExpiryTask($document, '3', 'urgent', $document->expires_at->copy()->endOfDay());
                $document->update(['reminder_3_at' => now()]);
            }

            if ($days < 0 && ! $document->expired_task_at) {
                $created += $this->createExpiryTask($document, 'expired', 'urgent', now());
                $document->update(['expired_task_at' => now()]);
            }
        }

        return $created;
    }

    public function completeDocumentTasks(UserDocument $document): void
    {
        CrmTask::query()
            ->where('source_type', UserDocument::class)
            ->where('source_id', $document->id)
            ->where('status', 'open')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
    }

    private function createExpiryTask(UserDocument $document, string $stage, string $priority, $dueAt): int
    {
        $assignee = $this->resolveReviewer();

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

        if ($task->wasRecentlyCreated && $assignee) {
            $this->notifyAssignee(
                $assignee,
                'document.expiry_task',
                $stage === 'expired' ? 'Документ сиделки просрочен' : 'Истекает документ сиделки',
                $task->description,
                $task,
            );
        }

        return $task->wasRecentlyCreated ? 1 : 0;
    }

    private function resolveReviewer(): ?User
    {
        return User::query()
            ->where(function ($query) {
                $query->where('role', 'admin')
                    ->orWhere(function ($staff) {
                        $staff->where('role', 'crm')->where('staff_active', true);
                    });
            })
            ->get()
            ->filter(fn (User $user) => $user->hasStaffPermission('crm.documents.manage'))
            ->sortBy(function (User $user) {
                return CrmTask::query()
                    ->where('assigned_to_id', $user->id)
                    ->where('status', 'open')
                    ->count();
            })
            ->first();
    }

    private function notifyAssignee(User $assignee, string $type, string $title, string $body, CrmTask $task): void
    {
        MarketplaceNotification::create([
            'user_id' => $assignee->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => [
                'task_id' => $task->id,
                'url' => '/tasks/' . $task->id . '/open',
            ],
        ]);
    }
}
