<?php

namespace App\Http\Controllers;

use App\Models\LegalContract;
use App\Models\LegalContractParty;
use App\Models\Order;
use App\Models\User;
use App\Services\LegalContractService;
use App\Services\LegalSignatureService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LegalContractController extends Controller
{
    public function __construct(
        private LegalContractService $contracts,
        private LegalSignatureService $signatures,
    ) {
    }

    public function createFramework(Request $request): RedirectResponse
    {
        $contract = $this->contracts->createFramework($request->user(), $request->user());

        return redirect()->route('legal.contracts.show', $contract)
            ->with('status', 'Агентский договор сформирован. Проверьте текст и подпишите одноразовым кодом.');
    }

    public function createFrameworkForUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isCrm(), 403);

        $contract = $this->contracts->createFramework($user, $request->user());

        return redirect()->route('legal.contracts.show', $contract)
            ->with('status', 'Агентский договор для пользователя сформирован.');
    }

    public function createOrder(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $contracts = $this->contracts->createOrderContracts($order, $request->user());
        $first = $contracts->first();

        return redirect()->route('legal.contracts.show', $first)
            ->with('status', 'Сформировано договоров по заказу: ' . $contracts->count() . '.');
    }

    public function show(Request $request, LegalContract $legalContract): View
    {
        $this->authorizeContract($request, $legalContract);
        $legalContract->load(['parties.signature', 'order', 'events.actor']);

        $party = $legalContract->parties->firstWhere('user_id', $request->user()->id);

        return view('contracts.online-show', [
            'contract' => $legalContract,
            'partyContext' => $party,
            'publicMode' => false,
        ]);
    }

    public function requestCode(Request $request, LegalContract $legalContract): RedirectResponse
    {
        $this->authorizeContract($request, $legalContract);
        $party = $legalContract->parties()->where('user_id', $request->user()->id)->firstOrFail();
        $result = $this->signatures->sendCode($party, $request);

        return back()->with('status', 'Код отправлен через ' . $result['channel'] . ' на ' . $result['destination'] . '.');
    }

    public function sign(Request $request, LegalContract $legalContract): RedirectResponse
    {
        $this->authorizeContract($request, $legalContract);
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'accept' => ['accepted'],
        ], [
            'accept.accepted' => 'Нужно подтвердить согласие с условиями договора.',
        ]);

        $party = $legalContract->parties()->where('user_id', $request->user()->id)->firstOrFail();
        $contract = $this->signatures->sign($party, $data['code'], $request);

        return redirect()->route('legal.contracts.show', $contract)
            ->with('status', $contract->status === LegalContract::STATUS_SIGNED
                ? 'Договор полностью подписан.'
                : 'Ваша подпись сохранена. Ожидается подпись второй стороны.');
    }

    public function publicShow(LegalContractParty $legalContractParty): View
    {
        $legalContractParty->load(['contract.parties.signature', 'contract.order']);

        return view('contracts.online-show', [
            'contract' => $legalContractParty->contract,
            'partyContext' => $legalContractParty,
            'publicMode' => true,
        ]);
    }

    public function publicRequestCode(Request $request, LegalContractParty $legalContractParty): RedirectResponse
    {
        $result = $this->signatures->sendCode($legalContractParty, $request);

        return back()->with('status', 'Код отправлен через ' . $result['channel'] . ' на ' . $result['destination'] . '.');
    }

    public function publicSign(Request $request, LegalContractParty $legalContractParty): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'accept' => ['accepted'],
        ], [
            'accept.accepted' => 'Нужно подтвердить согласие с условиями договора.',
        ]);

        $contract = $this->signatures->sign($legalContractParty, $data['code'], $request);

        return redirect()->route('legal.public.show', $legalContractParty)
            ->with('status', $contract->status === LegalContract::STATUS_SIGNED
                ? 'Договор полностью подписан.'
                : 'Ваша подпись сохранена. Ожидается подпись другой стороны.');
    }

    public function staffSendCode(Request $request, LegalContractParty $legalContractParty): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isCrm(), 403);
        $result = $this->signatures->sendCode($legalContractParty, $request);

        return back()->with('status', 'Код отправлен стороне через ' . $result['channel'] . ' на ' . $result['destination'] . '. Код сотруднику не показывается.');
    }

    public function download(Request $request, LegalContract $legalContract): Response
    {
        $this->authorizeContract($request, $legalContract);

        return $this->pdf($legalContract);
    }

    public function publicDownload(LegalContractParty $legalContractParty): Response
    {
        return $this->pdf($legalContractParty->contract);
    }

    public function protocol(Request $request, LegalContract $legalContract): View
    {
        $this->authorizeContract($request, $legalContract);
        $legalContract->load(['parties.signature', 'events.actor']);

        return view('contracts.protocol', ['contract' => $legalContract]);
    }

    private function pdf(LegalContract $contract): Response
    {
        $contract->load(['parties.signature']);

        return Pdf::loadView('contracts.online-pdf', ['contract' => $contract])
            ->setPaper('a4')
            ->download('contract-' . $contract->number . '.pdf');
    }

    private function authorizeContract(Request $request, LegalContract $contract): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin()
            || $user->isCrm()
            || $contract->parties()->where('user_id', $user->id)->exists(),
            403
        );
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin()
            || $user->isCrm()
            || $order->client_id === $user->id
            || $order->caregiver_id === $user->id
            || $order->caregiverAssignments()->where('caregiver_id', $user->id)->exists(),
            403
        );
    }
}
