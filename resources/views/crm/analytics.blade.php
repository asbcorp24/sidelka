@extends('layouts.app')

@php($title = 'Аналитика руководителя')

@section('content')
<div class="container-fluid px-3 px-xl-5 py-4">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Управление платформой</div>
            <h1 class="section-title mb-0">Аналитика руководителя</h1>
        </div>
        <form class="d-flex gap-2">
            <input type="date" name="from" class="form-control" value="{{ $from->format('Y-m-d') }}">
            <input type="date" name="to" class="form-control" value="{{ $to->format('Y-m-d') }}">
            <button class="btn btn-dark">Применить</button>
        </form>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['Новые обращения', $metrics['new_requests'], ''],
            ['Заказы из CRM', $metrics['converted_orders'], ''],
            ['Конверсия', $metrics['conversion_percent'].'%', ''],
            ['Среднее оформление', $metrics['average_conversion_hours'].' ч', ''],
            ['Расход на лиды', number_format($metrics['lead_spend'], 0, ',', ' ').' ₽', ''],
            ['Стоимость лида', number_format($metrics['cost_per_lead'], 0, ',', ' ').' ₽', ''],
            ['Стоимость заказа', number_format($metrics['cost_per_order'], 0, ',', ' ').' ₽', ''],
            ['Средний чек', number_format($metrics['average_check'], 0, ',', ' ').' ₽', ''],
            ['Повторные клиенты', $metrics['repeat_clients'], 'text-success'],
            ['Отмены', $metrics['cancellations'], 'text-danger'],
            ['Активные заказы', $metrics['active_orders'], ''],
            ['Завершено смен', $metrics['completed_shifts'], ''],
            ['Выплаты ожидают', number_format($metrics['pending_payouts'], 0, ',', ' ').' ₽', 'text-warning'],
            ['Выплачено', number_format($metrics['paid_payouts'], 0, ',', ' ').' ₽', 'text-success'],
            ['Комиссия', number_format($metrics['commission'], 0, ',', ' ').' ₽', ''],
            ['Открытые споры', $metrics['open_disputes'], 'text-danger'],
            ['Инциденты', $metrics['open_incidents'], 'text-danger'],
            ['Критические', $metrics['critical_incidents'], 'text-danger'],
            ['Просроченные документы', $metrics['expired_documents'], 'text-warning'],
            ['Просроченные задачи', $metrics['overdue_tasks'], 'text-danger'],
        ] as $metric)
            <div class="col-6 col-md-3 col-xl-2">
                <div class="metric">
                    <div class="value {{ $metric[2] }}">{{ $metric[1] }}</div>
                    <div class="small">{{ $metric[0] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card-soft p-4">
                <h2 class="h5">Обращения, заказы и завершенные смены</h2>
                <canvas id="dailyChart" height="100"></canvas>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card-soft p-4">
                <h2 class="h5">Инциденты по важности</h2>
                <canvas id="incidentChart" height="215"></canvas>
            </div>
        </div>
    </div>

    <div class="card-soft p-4">
        <h2 class="h5 mb-3">SLA и загрузка менеджеров</h2>
        <div class="table-responsive">
            <table class="table crm-table">
                <thead>
                    <tr>
                        <th>Сотрудник</th>
                        <th>Должность</th>
                        <th>Открытые задачи</th>
                        <th>Просрочено</th>
                        <th>Активные заявки</th>
                        <th>Средний первый ответ</th>
                        <th>Конверсия в заказ</th>
                        <th>Просрочки follow-up</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($staffWorkload as $staff)
                        <tr class="{{ $staff['overdue_tasks'] > 0 ? 'table-danger' : '' }}">
                            <td>{{ $staff['name'] }}</td>
                            <td>{{ $staff['role'] }}</td>
                            <td>{{ $staff['open_tasks'] }}</td>
                            <td>{{ $staff['overdue_tasks'] }}</td>
                            <td>{{ $staff['requests'] }}</td>
                            <td>{{ $staff['first_response_minutes'] !== null ? $staff['first_response_minutes'].' мин' : 'нет данных' }}</td>
                            <td>{{ $staff['conversion_percent'] }}%</td>
                            <td>{{ $staff['sla_overdue'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const daily = @json($daily);
new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: daily.map(x => x.date),
        datasets: [
            { label: 'Обращения', data: daily.map(x => x.requests) },
            { label: 'Заказы', data: daily.map(x => x.orders) },
            { label: 'Завершенные смены', data: daily.map(x => x.shifts) }
        ]
    },
    options: { responsive: true, interaction: { mode: 'index', intersect: false } }
});

const incidents = @json($incidentsBySeverity);
new Chart(document.getElementById('incidentChart'), {
    type: 'doughnut',
    data: {
        labels: Object.keys(incidents),
        datasets: [{ data: Object.values(incidents) }]
    },
    options: { responsive: true }
});
</script>
@endpush
