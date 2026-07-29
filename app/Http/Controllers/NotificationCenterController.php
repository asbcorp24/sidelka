<?php

namespace App\Http\Controllers;

use App\Models\CrmTask;
use App\Models\MarketplaceNotification;
use App\Services\NotificationCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function __construct(private NotificationCenterService $center)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('notifications.index', [
            'tasks' => $this->center->tasksQuery($user)
                ->paginate(30, ['*'], 'tasks_page')
                ->withQueryString(),
            'notifications' => MarketplaceNotification::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->paginate(30, ['*'], 'notifications_page')
                ->withQueryString(),
        ]);
    }

    public function openNotification(Request $request, MarketplaceNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return redirect()->to($this->center->notificationUrl($notification, $request->user()));
    }

    public function openTask(Request $request, CrmTask $crmTask): RedirectResponse
    {
        $this->authorizeTask($request, $crmTask);

        return redirect()->to($this->center->taskUrl($crmTask, $request->user()));
    }

    public function completeTask(Request $request, CrmTask $crmTask): RedirectResponse
    {
        $this->authorizeTask($request, $crmTask);

        if ($crmTask->status !== 'completed') {
            $crmTask->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return back()->with('status', 'Задача отмечена выполненной.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        MarketplaceNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'Все уведомления отмечены прочитанными.');
    }

    private function authorizeTask(Request $request, CrmTask $task): void
    {
        $user = $request->user();
        $allowed = $user->isAdmin()
            ? ($task->assigned_to_id === null || $task->assigned_to_id === $user->id)
            : ($user->isCrm() && $task->assigned_to_id === $user->id);

        abort_unless($allowed, 403);
    }
}
