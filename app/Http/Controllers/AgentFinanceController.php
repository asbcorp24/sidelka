<?php

namespace App\Http\Controllers;

use App\Models\AgentCommission;
use App\Models\Payout;
use App\Services\OrderFinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgentFinanceController extends Controller
{
    public function __construct(private OrderFinanceService $financeService)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isCrm(), 403);

        $query = Payout::query()
            ->with(['order', 'payment', 'caregiver.contractProfile'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($search) use ($term) {
                $search->where('external_reference', 'like', "%{$term}%")
                    ->orWhereHas('caregiver', function ($caregiverQuery) use ($term) {
                        $caregiverQuery->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    })
                    ->orWhereHas('order', function ($orderQuery) use ($term) {
                        $orderQuery->where('title', 'like', "%{$term}%");
                    });
            });
        }

        return view('payments.agent-registry', [
            'payouts' => $query->paginate(40)->withQueryString(),
            'stats' => [
                'pending_count' => Payout::whereIn('status', ['pending', 'processing'])->count(),
                'pending_amount' => Payout::whereIn('status', ['pending', 'processing'])->sum('amount'),
                'paid_month' => Payout::where('status', 'paid')
                    ->where('paid_at', '>=', now()->startOfMonth())
                    ->sum('amount'),
                'commission_month' => AgentCommission::where('status', 'recognized')
                    ->where('recognized_at', '>=', now()->startOfMonth())
                    ->sum('amount'),
            ],
        ]);
    }

    public function markPaid(Request $request, Payout $payout): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isCrm(), 403);

        $data = $request->validate([
            'destination' => ['required', 'string', 'max:500'],
            'external_reference' => ['required', 'string', 'max:255'],
        ]);

        $paidPayout = DB::transaction(function () use ($payout, $data) {
            $locked = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'paid') {
                return $locked;
            }

            abort_unless(in_array($locked->status, ['pending', 'processing'], true), 422);

            $locked->update([
                'status' => 'paid',
                'destination' => $data['destination'],
                'external_reference' => $data['external_reference'],
                'paid_at' => now(),
            ]);

            return $locked->fresh(['caregiver', 'order']);
        });

        $this->financeService->notify(
            $paidPayout->caregiver,
            'payout.released',
            'Выплата переведена',
            'По заказу «' . ($paidPayout->order?->title ?: '#' . $paidPayout->order_id) . '» переведено '
                . number_format($paidPayout->amount, 0, ',', ' ') . ' ₽. Номер операции: '
                . $paidPayout->external_reference . '.'
        );

        return back()->with('status', 'Выплата отмечена как выполненная.');
    }
}
