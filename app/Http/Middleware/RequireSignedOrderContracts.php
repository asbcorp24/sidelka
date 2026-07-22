<?php

namespace App\Http\Middleware;

use App\Models\LegalContract;
use App\Models\Order;
use Closure;
use Illuminate\Http\Request;

class RequireSignedOrderContracts
{
    public function handle(Request $request, Closure $next)
    {
        $order = $request->route('order');

        if (! $order instanceof Order) {
            return $next($request);
        }

        $order->loadMissing(['caregiverAssignments.caregiver']);

        $caregivers = $order->caregiverAssignments
            ->whereIn('status', ['accepted', 'completed'])
            ->pluck('caregiver')
            ->filter()
            ->unique('id')
            ->values();

        $missing = $caregivers->filter(function ($caregiver) use ($order) {
            return ! LegalContract::query()
                ->where('type', LegalContract::TYPE_ORDER_SERVICE)
                ->where('order_id', $order->id)
                ->where('meta->caregiver_id', $caregiver->id)
                ->where('status', LegalContract::STATUS_SIGNED)
                ->exists();
        });

        if ($missing->isNotEmpty()) {
            return back()->withErrors([
                'contract' => 'Нельзя начать заказ: отсутствует полностью подписанный договор с ' . $missing->pluck('name')->implode(', ') . '.',
            ]);
        }

        return $next($request);
    }
}
