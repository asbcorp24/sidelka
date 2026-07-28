@extends('layouts.app')

@php($title = 'Долгие заказы')

@section('content')
<div class="container-fluid px-3 px-xl-5 py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">CRM расписание</div>
            <h1 class="section-title mb-0">Долгие заказы</h1>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('crm.dashboard') }}" class="btn btn-outline-dark rounded-pill">Назад в CRM</a>
            <a href="{{ route('crm.kanban') }}" class="btn btn-dark rounded-pill">Kanban</a>
        </div>
    </div>

    @forelse($orders as $order)
        <div class="card-soft p-4 mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <div>
                    <h2 class="h4 mb-1">#{{ $order->id }} {{ $order->title }}</h2>
                    <div class="text-secondary">{{ $order->client?->name }} • {{ $order->city }} • {{ $order->status_label }}</div>
                </div>
                <div class="text-end">
                    <div><span class="badge {{ $order->status_badge_class }}">{{ $order->status_label }}</span></div>
                    <div class="small text-secondary mt-2">Открытых слотов: {{ $order->open_slots_count }} • Конфликтов: {{ $order->conflicts_count }}</div>
                </div>
            </div>

            <div class="row g-3">
                @foreach($order->scheduleSlots->sortBy(['scheduled_date', 'starts_at']) as $slot)
                    @php($slotAssignments = $order->caregiverAssignments->where('order_schedule_slot_id', $slot->id))
                    <div class="col-lg-6">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="d-flex justify-content-between gap-2 flex-wrap">
                                <strong>{{ $slot->scheduled_date->format('d.m.Y') }} {{ substr($slot->starts_at, 0, 5) }}-{{ substr($slot->ends_at, 0, 5) }}</strong>
                                <span class="badge {{ $slotAssignments->whereIn('status', ['accepted', 'completed', 'completion_requested'])->count() > 1 ? 'text-bg-danger' : ($slotAssignments->isEmpty() ? 'text-bg-warning' : 'text-bg-success') }}">
                                    {{ $slotAssignments->isEmpty() ? 'Нужна замена' : 'Назначено: '.$slotAssignments->count() }}
                                </span>
                            </div>

                            <div class="mt-3">
                                @forelse($slotAssignments as $assignment)
                                    <div class="border rounded-3 p-2 mb-2">
                                        <div class="d-flex justify-content-between gap-2">
                                            <strong>{{ $assignment->caregiver?->name ?: 'Сиделка' }}</strong>
                                            <span class="badge {{ in_array($assignment->status, ['accepted', 'completed', 'completion_requested'], true) ? 'text-bg-success' : ($assignment->status === 'declined' ? 'text-bg-danger' : 'text-bg-secondary') }}">{{ $assignment->status }}</span>
                                        </div>
                                        <form action="{{ route('crm.assignments.replace', [$order, $assignment]) }}" method="POST" class="row g-2 mt-2">
                                            @csrf
                                            <div class="col-md-7">
                                                <select name="caregiver_id" class="form-select form-select-sm" required>
                                                    <option value="">Заменить сиделкой</option>
                                                    @foreach($caregivers as $caregiver)
                                                        <option value="{{ $caregiver->id }}">{{ $caregiver->name }} • {{ $caregiver->city }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-5">
                                                <button class="btn btn-outline-dark btn-sm w-100">Заменить</button>
                                            </div>
                                            <div class="col-12">
                                                <input type="text" name="notes" class="form-control form-control-sm" placeholder="Причина замены / комментарий">
                                            </div>
                                        </form>
                                    </div>
                                @empty
                                    <div class="text-secondary small mb-2">На этот слот пока нет назначенной сиделки.</div>
                                    <div class="small text-danger">Для полностью пустого слота сначала пригласите сиделку из карточки заказа или CRM-заявки.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="card-soft p-4">
            <p class="text-secondary mb-0">Долгих заказов пока нет.</p>
        </div>
    @endforelse
</div>
@endsection
