<?php

namespace App\Services;

use App\Models\CrmTask;
use App\Models\MarketplaceNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class NotificationCenterService
{
    public function summary(User $user, int $limit = 8): array
    {
        $tasksQuery = $this->tasksQuery($user);
        $notificationsQuery = $this->notificationsQuery($user);
        $taskCount = (clone $tasksQuery)->count();
        $notificationCount = (clone $notificationsQuery)->count();

        return [
            'tasks' => (clone $tasksQuery)->limit($limit)->get(),
            'notifications' => (clone $notificationsQuery)->limit($limit)->get(),
            'task_count' => $taskCount,
            'notification_count' => $notificationCount,
            'total_count' => $taskCount + $notificationCount,
        ];
    }

    public function tasksQuery(User $user): Builder
    {
        $query = CrmTask::query()
            ->with(['personUser', 'crmRequest'])
            ->where('status', 'open');

        if ($user->isAdmin()) {
            $query->where(function (Builder $builder) use ($user) {
                $builder->where('assigned_to_id', $user->id)
                    ->orWhereNull('assigned_to_id');
            });
        } elseif ($user->isCrm()) {
            $query->where('assigned_to_id', $user->id);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByRaw('CASE WHEN due_at IS NOT NULL AND due_at < ? THEN 0 ELSE 1 END', [now()])
            ->orderBy('due_at')
            ->latest('id');
    }

    public function notificationsQuery(User $user): Builder
    {
        return MarketplaceNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->latest('id');
    }

    public function taskUrl(CrmTask $task, User $user): string
    {
        if ($task->category === 'caregiver_document' && $user->hasStaffPermission('crm.documents.manage')) {
            return route('crm.caregiver-documents.index', ['status' => 'unverified']);
        }

        if ($task->category === 'shift_dispute' && $user->hasStaffPermission('crm.disputes.manage')) {
            return route('crm.shift-disputes.index', ['status' => 'open']);
        }

        if ($task->category === 'safety_incident' && $user->hasStaffPermission('crm.incidents.manage')) {
            return route('crm.incidents.index', ['status' => 'open']);
        }

        if ($task->crmRequest && $user->hasStaffPermission('crm.requests.manage')) {
            return route('crm.requests.show', $task->crmRequest);
        }

        if ($user->isAdmin() || $user->isCrm()) {
            return route('crm.dashboard');
        }

        return route('home');
    }

    public function notificationUrl(MarketplaceNotification $notification, User $user): string
    {
        $data = $notification->data ?? [];
        $internalUrl = $data['url'] ?? null;

        if (is_string($internalUrl) && str_starts_with($internalUrl, '/')) {
            return url($internalUrl);
        }

        if ($user->isClient() && ! empty($data['order_id'])) {
            return route('client.orders.show', $data['order_id']);
        }

        if ($user->isCaregiver() && ! empty($data['order_id'])) {
            return route('caregiver.orders.show', $data['order_id']);
        }

        if ($user->isCaregiver() && ! empty($data['payout_id'])) {
            return route('caregiver.payouts.index');
        }

        if (($user->isAdmin() || $user->isCrm()) && ! empty($data['incident_id']) && $user->hasStaffPermission('crm.incidents.manage')) {
            return route('crm.incidents.index');
        }

        if (($user->isAdmin() || $user->isCrm()) && ! empty($data['dispute_id']) && $user->hasStaffPermission('crm.disputes.manage')) {
            return route('crm.shift-disputes.index');
        }

        return match (true) {
            $user->isClient() => route('client.dashboard'),
            $user->isCaregiver() => route('caregiver.dashboard'),
            $user->isAdmin() || $user->isCrm() => route('crm.dashboard'),
            default => route('home'),
        };
    }
}
