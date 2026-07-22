<?php

namespace App\Http\Controllers;

use App\Models\AgentCommission;
use App\Models\CrmRequest;
use App\Models\CrmTask;
use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\Payout;
use App\Models\SafetyIncident;
use App\Models\ShiftDispute;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ExecutiveAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();

        $requests = CrmRequest::whereBetween('created_at', [$from, $to])->get();
        $converted = $requests->whereNotNull('order_id');
        $conversion = $requests->count() > 0 ? round($converted->count() * 100 / $requests->count(), 1) : 0;
        $averageConversionHours = $converted->avg(function (CrmRequest $crmRequest) {
            $order = $crmRequest->order;
            return $order ? $crmRequest->created_at->diffInMinutes($order->created_at) / 60 : null;
        });

        $days = collect();
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $days->push([
                'date' => $date->format('d.m'),
                'requests' => $requests->filter(fn ($item) => $item->created_at->format('Y-m-d') === $key)->count(),
                'orders' => Order::whereDate('created_at', $key)->count(),
                'shifts' => OrderCaregiverAssignment::whereDate('completed_at', $key)->count(),
            ]);
        }

        $staffWorkload = User::where('role', 'crm')->where('staff_active', true)->get()->map(function (User $user) {
            return [
                'name' => $user->name,
                'role' => $user->staffRoleLabel(),
                'open_tasks' => CrmTask::where('assigned_to_id', $user->id)->where('status', 'open')->count(),
                'overdue_tasks' => CrmTask::where('assigned_to_id', $user->id)->where('status', 'open')->where('due_at', '<', now())->count(),
                'requests' => CrmRequest::where('responsible_user_id', $user->id)->whereNotIn('status', ['completed', 'cancelled'])->count(),
            ];
        })->sortByDesc('open_tasks')->values();

        return view('crm.analytics', [
            'from' => $from,
            'to' => $to,
            'metrics' => [
                'new_requests' => $requests->count(),
                'converted_orders' => $converted->count(),
                'conversion_percent' => $conversion,
                'average_conversion_hours' => round((float) ($averageConversionHours ?? 0), 1),
                'active_orders' => Order::where('status', 'in_progress')->count(),
                'completed_shifts' => OrderCaregiverAssignment::whereBetween('completed_at', [$from, $to])->count(),
                'pending_payouts' => Payout::whereIn('status', ['pending', 'processing'])->sum('amount'),
                'paid_payouts' => Payout::where('status', 'paid')->whereBetween('paid_at', [$from, $to])->sum('amount'),
                'commission' => AgentCommission::whereBetween('recognized_at', [$from, $to])->sum('amount'),
                'open_disputes' => ShiftDispute::whereIn('status', ['open', 'in_review'])->count(),
                'open_incidents' => SafetyIncident::whereIn('status', ['open', 'in_progress'])->count(),
                'critical_incidents' => SafetyIncident::whereIn('status', ['open', 'in_progress'])->where('severity', 'critical')->count(),
                'expired_documents' => UserDocument::whereHas('user', fn ($q) => $q->where('role', 'caregiver'))->whereDate('expires_at', '<', today())->count(),
                'overdue_tasks' => CrmTask::where('status', 'open')->where('due_at', '<', now())->count(),
            ],
            'daily' => $days,
            'incidentsBySeverity' => SafetyIncident::whereBetween('occurred_at', [$from, $to])
                ->selectRaw('severity, COUNT(*) as total')->groupBy('severity')->pluck('total', 'severity'),
            'disputesByDecision' => ShiftDispute::whereBetween('opened_at', [$from, $to])
                ->selectRaw('COALESCE(decision, status) as result, COUNT(*) as total')->groupBy('result')->pluck('total', 'result'),
            'staffWorkload' => $staffWorkload,
        ]);
    }
}
