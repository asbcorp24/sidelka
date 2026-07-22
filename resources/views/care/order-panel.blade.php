@php
    $viewer = auth()->user();
    $carePlan = \App\Models\CarePlan::where('order_id', $order->id)->with('items')->first();
    $careAssignmentsQuery = $order->caregiverAssignments()->with(['caregiver', 'scheduleSlot', 'journal.entries'])->whereIn('status', ['accepted', 'completed']);
    if ($viewer->isCaregiver()) {
        $careAssignmentsQuery->where('caregiver_id', $viewer->id);
    }
    $careAssignments = $careAssignmentsQuery->get();
    $canEditPlan = ($viewer->isClient() && $order->client_id === $viewer->id) || $viewer->hasStaffPermission('crm.schedules.manage');
@endphp

@if($viewer->isClient() || $viewer->isCaregiver() || $viewer->hasStaffPermission('crm.schedules.manage'))
<div class="container mt-3">
    <div class="card-soft p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
                <div class="text-uppercase small text-secondary">Уход и безопасность</div>
                <h2 class="h4 mb-1">План ухода и журналы смен</h2>
                <p class="text-secondary mb-0">Каждая сиделка видит единый план, но заполняет только журнал своих смен.</p>
            </div>
            <span class="badge {{ $carePlan ? 'text-bg-success' : 'text-bg-warning' }}">{{ $carePlan ? 'План активен' : 'План не заполнен' }}</span>
        </div>

        @if($canEditPlan)
            <details class="border rounded-4 p-3 mb-4" {{ $carePlan ? '' : 'open' }}>
                <summary class="fw-bold">{{ $carePlan ? 'Изменить план ухода' : 'Создать план ухода' }}</summary>
                <form action="{{ route('care-plans.save', $order) }}" method="POST" class="row g-3 mt-2">
                    @csrf
                    <div class="col-md-6"><label class="form-label">Подопечный</label><input name="patient_name" class="form-control" value="{{ old('patient_name', $carePlan?->patient_name ?: $order->patient_name) }}"></div>
                    <div class="col-md-6"><label class="form-label">Экстренный контакт</label><input name="emergency_contact_name" class="form-control" value="{{ old('emergency_contact_name', $carePlan?->emergency_contact_name) }}" placeholder="ФИО"></div>
                    <div class="col-md-6"><label class="form-label">Телефон экстренного контакта</label><input name="emergency_contact_phone" class="form-control" value="{{ old('emergency_contact_phone', $carePlan?->emergency_contact_phone) }}"></div>
                    <div class="col-md-6"><label class="form-label">Состояние и диагнозы</label><textarea name="diagnosis_summary" class="form-control" rows="2">{{ old('diagnosis_summary', $carePlan?->diagnosis_summary) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Аллергии и противопоказания</label><textarea name="allergies" class="form-control" rows="2">{{ old('allergies', $carePlan?->allergies) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Лекарства и напоминания</label><textarea name="medications" class="form-control" rows="2">{{ old('medications', $carePlan?->medications) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Питание</label><textarea name="nutrition" class="form-control" rows="2">{{ old('nutrition', $carePlan?->nutrition) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Подвижность и перемещение</label><textarea name="mobility" class="form-control" rows="2">{{ old('mobility', $carePlan?->mobility) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Гигиена</label><textarea name="hygiene" class="form-control" rows="2">{{ old('hygiene', $carePlan?->hygiene) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Общение и особенности поведения</label><textarea name="communication" class="form-control" rows="2">{{ old('communication', $carePlan?->communication) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Риски</label><textarea name="risks" class="form-control" rows="2">{{ old('risks', $carePlan?->risks) }}</textarea></div>
                    <div class="col-12"><label class="form-label">Действия в экстренной ситуации</label><textarea name="emergency_instructions" class="form-control" rows="3">{{ old('emergency_instructions', $carePlan?->emergency_instructions) }}</textarea></div>
                    <div class="col-12"><label class="form-label">Примечания</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', $carePlan?->notes) }}</textarea></div>

                    <div class="col-12"><h3 class="h6">Обязательные действия каждой смены</h3></div>
                    @for($i = 0; $i < 5; $i++)
                        @php($item = $carePlan?->items?->get($i))
                        <div class="col-md-2"><select name="items[{{ $i }}][category]" class="form-select"><option value="observation">Наблюдение</option><option value="medication" {{ $item?->category === 'medication' ? 'selected' : '' }}>Лекарства</option><option value="nutrition" {{ $item?->category === 'nutrition' ? 'selected' : '' }}>Питание</option><option value="hygiene" {{ $item?->category === 'hygiene' ? 'selected' : '' }}>Гигиена</option><option value="mobility" {{ $item?->category === 'mobility' ? 'selected' : '' }}>Подвижность</option><option value="household" {{ $item?->category === 'household' ? 'selected' : '' }}>Быт</option><option value="other">Другое</option></select></div>
                        <div class="col-md-3"><input name="items[{{ $i }}][title]" class="form-control" value="{{ $item?->title }}" placeholder="Действие"></div>
                        <div class="col-md-4"><input name="items[{{ $i }}][instructions]" class="form-control" value="{{ $item?->instructions }}" placeholder="Инструкция"></div>
                        <div class="col-md-2"><input name="items[{{ $i }}][schedule_text]" class="form-control" value="{{ $item?->schedule_text }}" placeholder="Когда"></div>
                        <div class="col-md-1"><label class="form-check mt-2"><input type="checkbox" class="form-check-input" name="items[{{ $i }}][is_required]" value="1" {{ $item?->is_required ? 'checked' : '' }}><span class="small">Обяз.</span></label></div>
                    @endfor
                    <div class="col-12"><button class="btn btn-dark rounded-pill">Сохранить план ухода</button></div>
                </form>
            </details>
        @elseif($carePlan)
            <div class="border rounded-4 p-3 mb-4">
                <div class="row g-3 small">
                    <div class="col-md-6"><strong>Лекарства:</strong><br>{{ $carePlan->medications ?: 'не указаны' }}</div>
                    <div class="col-md-6"><strong>Риски:</strong><br>{{ $carePlan->risks ?: 'не указаны' }}</div>
                    <div class="col-md-6"><strong>Питание:</strong><br>{{ $carePlan->nutrition ?: 'не указано' }}</div>
                    <div class="col-md-6"><strong>Экстренные действия:</strong><br>{{ $carePlan->emergency_instructions ?: 'связаться с заказчиком и экстренными службами' }}</div>
                </div>
                @foreach($carePlan->items as $item)<div class="border-top mt-2 pt-2"><strong>{{ $item->title }}</strong> — {{ $item->instructions }} <span class="text-secondary">{{ $item->schedule_text }}</span></div>@endforeach
            </div>
        @endif

        @if($viewer->isCaregiver())
            @foreach($careAssignments as $assignment)
                @php($journal = $assignment->journal)
                <div class="border rounded-4 p-3 mb-4">
                    <h3 class="h5">Журнал смены {{ $assignment->scheduleSlot?->scheduled_date?->format('d.m.Y') }}</h3>
                    <form action="{{ route('caregiver.journals.save', [$order, $assignment]) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-3"><label class="form-label">Прибытие</label><input type="datetime-local" name="arrived_at" class="form-control" value="{{ $journal?->arrived_at?->format('Y-m-d\TH:i') }}"></div>
                        <div class="col-md-3"><label class="form-label">Завершение</label><input type="datetime-local" name="left_at" class="form-control" value="{{ $journal?->left_at?->format('Y-m-d\TH:i') }}"></div>
                        <div class="col-md-6"><label class="form-label">Итог смены</label><input name="summary" class="form-control" value="{{ $journal?->summary }}"></div>
                        <div class="col-md-6"><label class="form-label">Наблюдения</label><textarea name="observations" class="form-control" rows="2">{{ $journal?->observations }}</textarea></div>
                        <div class="col-md-3"><label class="form-label">Показатели</label><textarea name="vitals_text" class="form-control" rows="2">{{ data_get($journal?->vitals, 'text') }}</textarea></div>
                        <div class="col-md-3"><label class="form-label">Питание</label><textarea name="meals_text" class="form-control" rows="2">{{ data_get($journal?->meals, 'text') }}</textarea></div>
                        <div class="col-md-4"><label class="form-label">Лекарства</label><textarea name="medications_text" class="form-control" rows="2">{{ data_get($journal?->medications, 'text') }}</textarea></div>
                        <div class="col-md-4"><label class="form-label">Гигиена</label><textarea name="hygiene_text" class="form-control" rows="2">{{ data_get($journal?->hygiene, 'text') }}</textarea></div>
                        <div class="col-md-4"><label class="form-label">Подвижность</label><textarea name="mobility_text" class="form-control" rows="2">{{ data_get($journal?->mobility, 'text') }}</textarea></div>
                        <div class="col-12"><button class="btn btn-outline-dark">Сохранить журнал</button></div>
                    </form>
                    @if($journal && $journal->status !== 'accepted')
                        <form action="{{ route('caregiver.journals.submit', [$order, $assignment]) }}" method="POST" class="mt-3">@csrf<button class="btn btn-success rounded-pill">Отправить журнал заказчику</button></form>
                    @endif
                    @if($journal?->entries?->isNotEmpty())
                        <div class="mt-3">@foreach($journal->entries as $entry)<div class="small border-top py-2 {{ $entry->is_alert ? 'text-danger fw-bold' : '' }}">{{ $entry->happened_at->format('H:i') }} — {{ $entry->title }}: {{ $entry->value }} {{ $entry->unit }}</div>@endforeach</div>
                    @endif
                    <details class="mt-3"><summary>Добавить событие в журнал</summary><form action="{{ route('caregiver.journals.entries.store', [$order, $assignment]) }}" method="POST" class="row g-2 mt-2">@csrf<div class="col-md-2"><select name="entry_type" class="form-select"><option value="vital">Показатель</option><option value="meal">Питание</option><option value="medication">Лекарство</option><option value="hygiene">Гигиена</option><option value="mobility">Подвижность</option><option value="observation">Наблюдение</option></select></div><div class="col-md-3"><input name="title" class="form-control" placeholder="Название" required></div><div class="col-md-2"><input name="value" class="form-control" placeholder="Значение"></div><div class="col-md-1"><input name="unit" class="form-control" placeholder="ед."></div><div class="col-md-3"><input type="datetime-local" name="happened_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}" required></div><div class="col-md-1"><label class="form-check mt-2"><input type="checkbox" name="is_alert" value="1" class="form-check-input"><span class="small">Тревога</span></label></div><div class="col-12"><textarea name="notes" class="form-control" rows="2" placeholder="Комментарий"></textarea></div><div class="col-12"><button class="btn btn-sm btn-dark">Добавить</button></div></form></details>
                </div>
            @endforeach
        @elseif($viewer->isClient())
            @foreach($careAssignments as $assignment)
                @if($assignment->journal)
                    <div class="border rounded-4 p-3 mb-3"><strong>{{ $assignment->caregiver?->name }}</strong> — {{ $assignment->scheduleSlot?->scheduled_date?->format('d.m.Y') }}<div class="small text-secondary">{{ $assignment->journal->summary }}</div><div class="mt-2">Статус: {{ $assignment->journal->status }}</div></div>
                @endif
            @endforeach
        @endif

        <details class="border border-danger-subtle rounded-4 p-3 mt-4">
            <summary class="fw-bold text-danger">Сообщить об инциденте или угрозе безопасности</summary>
            <form action="{{ route('safety-incidents.store', $order) }}" method="POST" class="row g-3 mt-2">
                @csrf
                <div class="col-md-4"><select name="order_caregiver_assignment_id" class="form-select"><option value="">Весь заказ</option>@foreach($careAssignments as $assignment)<option value="{{ $assignment->id }}">{{ $assignment->caregiver?->name }} — {{ $assignment->scheduleSlot?->scheduled_date?->format('d.m.Y') }}</option>@endforeach</select></div>
                <div class="col-md-4"><select name="incident_type" class="form-select" required>@foreach(\App\Models\SafetyIncident::TYPE_LABELS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4"><select name="severity" class="form-select" required>@foreach(\App\Models\SafetyIncident::SEVERITY_LABELS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4"><input type="datetime-local" name="occurred_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}" required></div>
                <div class="col-md-8"><textarea name="description" class="form-control" rows="2" placeholder="Что произошло" required></textarea></div>
                <div class="col-12"><textarea name="actions_taken" class="form-control" rows="2" placeholder="Что уже сделано"></textarea></div>
                <div class="col-md-4"><label class="form-check"><input type="checkbox" name="emergency_called" value="1" class="form-check-input"><span class="form-check-label">Вызвана экстренная помощь</span></label></div>
                <div class="col-md-8"><input name="emergency_service_reference" class="form-control" placeholder="Номер обращения или бригады"></div>
                <div class="col-12"><button class="btn btn-danger rounded-pill">Зарегистрировать инцидент</button></div>
            </form>
        </details>
    </div>
</div>
@endif
