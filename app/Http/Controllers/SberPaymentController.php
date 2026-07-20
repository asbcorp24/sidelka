<?php

namespace App\Http\Controllers;

use App\Models\WalletTopUp;
use App\Services\WalletTopUpService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class SberPaymentController extends Controller
{
    public function __construct(private WalletTopUpService $topUpService)
    {
    }

    public function callback(Request $request): Response
    {
        $providerOrderId = (string) $request->input('mdOrder', '');
        $orderNumber = (string) $request->input('orderNumber', '');

        if ($providerOrderId === '' && $orderNumber === '') {
            return response('INVALID CALLBACK', 400);
        }

        $topUp = WalletTopUp::query()
            ->where(function ($query) use ($providerOrderId, $orderNumber) {
                if ($providerOrderId !== '') {
                    $query->where('provider_order_id', $providerOrderId);
                }

                if ($orderNumber !== '') {
                    $providerOrderId !== ''
                        ? $query->orWhere('order_number', $orderNumber)
                        : $query->where('order_number', $orderNumber);
                }
            })
            ->first();

        if (! $topUp) {
            Log::warning('Получен callback Сбера для неизвестного платежа.', [
                'mdOrder' => $providerOrderId,
                'orderNumber' => $orderNumber,
                'operation' => $request->input('operation'),
                'status' => $request->input('status'),
            ]);

            return response('NOT FOUND', 404);
        }

        try {
            $this->topUpService->sync($topUp);
        } catch (Throwable $exception) {
            report($exception);

            return response('RETRY', 503);
        }

        return response('OK');
    }

    public function returnResult(WalletTopUp $walletTopUp): View
    {
        $walletTopUp = $this->syncQuietly($walletTopUp);

        return view('payments.sber-result', [
            'topUp' => $walletTopUp,
            'isFailPage' => false,
        ]);
    }

    public function failResult(WalletTopUp $walletTopUp): View
    {
        $walletTopUp = $this->syncQuietly($walletTopUp);

        return view('payments.sber-result', [
            'topUp' => $walletTopUp,
            'isFailPage' => true,
        ]);
    }

    private function syncQuietly(WalletTopUp $topUp): WalletTopUp
    {
        try {
            return $this->topUpService->sync($topUp);
        } catch (Throwable $exception) {
            report($exception);

            return $topUp->fresh();
        }
    }
}
