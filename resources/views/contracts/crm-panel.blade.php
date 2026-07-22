@php
    $crmClient = $crmRequest->clientUser;
    $crmCaregiver = $crmRequest->caregiverUser;
    $crmOrder = $crmRequest->order;

    $clientFramework = $crmClient?->legalContractParties()
        ->whereHas('contract', fn($query) => $query->where('type', \App\Models\LegalContract::TYPE_CLIENT_AGENCY))
        ->with('contract.parties.signature')
        ->latest('id')
        ->first()?->contract;

    $caregiverFramework = $crmCaregiver?->legalContractParties()
        ->whereHas('contract', fn($query) => $query->where('type', \App\Models\LegalContract::TYPE_CAREGIVER_AGENCY))
        ->with('contract.parties.signature')
        ->latest('id')
        ->first()?->contract;

    $crmOrderContracts = $crmOrder
        ? \App\Models\LegalContract::where('order_id', $crmOrder->id)->where('type', \App\Models\LegalContract::TYPE_ORDER_SERVICE)->with('parties.signature')->get()
        : collect();
@endphp

<div class="container-fluid px-3 px-xl-5 mt-3">
    <div class="card-soft p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
                <div class="text-uppercase small text-secondary">CRM • электронные документы</div>
                <h2 class="h4 mb-1">Агентские договоры и подписи</h2>
                <p class="text-secondary mb-0">CRM формирует и отправляет документы, но не вводит код за клиента или сиделку.</p>
            </div>
            @if($crmOrder && $crmClient && $crmCaregiver)
                <form action="{{ route('legal.orders.create', $crmOrder) }}" method="POST">
                    @csrf
                    <button class="btn btn-dark rounded-pill">Сформировать договор по заказу</button>
                </form>
            @endif
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="border rounded-4 p-3 h-100">
                    <strong>Заказчик</strong>
                    @if(! $crmClient)
                        <p class="small text-secondary mt-2 mb-0">Сначала создайте или свяжите карточку клиента.</p>
                    @elseif($clientFramework)
                        <div class="small text-secondary mt-2">{{ $clientFramework->number }}</div>
                        <span class="badge {{ $clientFramework->status === 'signed' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $clientFramework->status === 'signed' ? 'Подписан' : 'Ожидает подписи' }}</span>
                        <div><a href="{{ route('legal.contracts.show', $clientFramework) }}" class="btn btn-outline-dark rounded-pill btn-sm mt-3">Открыть</a></div>
                    @else
                        <form action="{{ route('legal.framework.create-for-user', $crmClient) }}" method="POST" class="mt-3">
                            @csrf
                            <button class="btn btn-outline-dark rounded-pill btn-sm">Создать договор заказчика</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="col-lg-4">
                <div class="border rounded-4 p-3 h-100">
                    <strong>Сиделка</strong>
                    @if(! $crmCaregiver)
                        <p class="small text-secondary mt-2 mb-0">Сначала выберите и свяжите сиделку.</p>
                    @elseif($caregiverFramework)
                        <div class="small text-secondary mt-2">{{ $caregiverFramework->number }}</div>
                        <span class="badge {{ $caregiverFramework->status === 'signed' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $caregiverFramework->status === 'signed' ? 'Подписан' : 'Ожидает подписи' }}</span>
                        <div><a href="{{ route('legal.contracts.show', $caregiverFramework) }}" class="btn btn-outline-dark rounded-pill btn-sm mt-3">Открыть</a></div>
                    @else
                        <form action="{{ route('legal.framework.create-for-user', $crmCaregiver) }}" method="POST" class="mt-3">
                            @csrf
                            <button class="btn btn-outline-dark rounded-pill btn-sm">Создать договор сиделки</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="col-lg-4">
                <div class="border rounded-4 p-3 h-100">
                    <strong>Договор по заказу</strong>
                    @if(! $crmOrder)
                        <p class="small text-secondary mt-2 mb-0">Сначала преобразуйте заявку в заказ.</p>
                    @elseif($crmOrderContracts->isEmpty())
                        <p class="small text-secondary mt-2 mb-0">Документ ещё не сформирован.</p>
                    @else
                        @foreach($crmOrderContracts as $legalContract)
                            <div class="border-top pt-2 mt-2">
                                <div class="small">{{ $legalContract->number }}</div>
                                <span class="badge {{ $legalContract->status === 'signed' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $legalContract->status === 'signed' ? 'Подписан' : 'Ожидает' }}</span>
                                <a href="{{ route('legal.contracts.show', $legalContract) }}" class="btn btn-link btn-sm">открыть</a>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
