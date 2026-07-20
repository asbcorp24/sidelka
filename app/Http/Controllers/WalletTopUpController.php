<?php

namespace App\Http\Controllers;

use App\Services\WalletTopUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class WalletTopUpController extends Controller
{
    public function __construct(private WalletTopUpService $topUpService)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isClient(), 404);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:100', 'max:1000000'],
        ], [
            'amount.min' => 'Минимальная сумма пополнения — 100 ₽.',
            'amount.max' => 'Максимальная сумма одного пополнения — 1 000 000 ₽.',
        ]);

        try {
            $topUp = $this->topUpService->start($user, (int) $data['amount']);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withErrors([
                    'amount' => 'Не удалось создать платёж в Сбере. Проверьте тестовые реквизиты эквайринга и повторите попытку.',
                ])
                ->withInput();
        }

        return redirect()->away($topUp->payment_url);
    }
}
