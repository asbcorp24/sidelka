@php
    $contractQuery = \App\Models\LegalContract::query()
        ->where('order_id', $order->id)
        ->where('type', \App\Models\LegalContract::TYPE_ORDER_SERVICE)
        ->with('parties.signature')
        ->latest('id');

    if (! auth()->user()->isAdmin() && ! auth()->user()->isCrm()) {
        $contractQuery->whereHas('parties', fn($query) => $query->where('user_id', auth()->id()));
    }

    $orderContracts = $contractQuery->get();
    $hasCaregiver = (bool) $order->caregiver_id || $order->caregiverAssignments()->whereIn('status', ['invited', 'accepted', 'completed'])->exists();
@endphp

<div class="container mt-3">
    <div class="card-soft p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="text-uppercase small text-secondary">Юридическое оформление заказа</div>
                <h2 class="h4 mb-1">Договор заказчик ↔ сиделка</h2>
                <p class="text-secondary mb-0">Площадка выступает агентом и сопровождает подбор, документы и расчеты, но услуги оказывает выбранная сиделка.</p>
            </div>
            @if($hasCaregiver)
                <form action="{{ route('legal.orders.create', $order) }}" method="POST">
                    @csrf
                    <button class="btn btn-dark rounded-pill px-4">{{ $orderContracts->isEmpty() ? 'Сформировать договор' : 'Проверить договоры' }}</button>
                </form>
            @endif
        </div>

        @if(! $hasCaregiver)
            <div class="alert alert-warning rounded-4 mt-3 mb-0">Договор по заказу можно сформировать после выбора сиделки.</div>
        @elseif($orderContracts->isNotEmpty())
            <div class="row g-3 mt-1">
                @foreach($orderContracts as $legalContract)
                    <div class="col-lg-6">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <strong>{{ $legalContract->number }}</strong>
                                    <div class="small text-secondary">
                                        Сиделка: {{ $legalContract->parties->firstWhere('role', 'caregiver')?->name ?: '—' }}
                                    </div>
                                </div>
                                <span class="badge {{ $legalContract->status === 'signed' ? 'text-bg-success' : 'text-bg-warning' }}">
                                    {{ $legalContract->status === 'signed' ? 'Подписан' : 'Ожидает подписей' }}
                                </span>
                            </div>
                            <a href="{{ route('legal.contracts.show', $legalContract) }}" class="btn btn-outline-dark rounded-pill btn-sm mt-3">Открыть договор</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
