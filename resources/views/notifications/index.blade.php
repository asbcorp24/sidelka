@extends('layouts.app')

@php($title = 'Задачи и уведомления')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase small text-secondary">Персональный центр</div>
            <h1 class="section-title mb-0">Задачи и уведомления</h1>
        </div>
        <form action="{{ route('notifications.read-all') }}" method="POST">
            @csrf
            <button class="btn btn-outline-dark rounded-pill">Отметить уведомления прочитанными</button>
        </form>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Мои рабочие задачи</h2>
                @forelse($tasks as $task)
                    @php($overdue = $task->due_at && $task->due_at->isPast())
                    <div class="border rounded-4 p-3 mb-3 {{ $overdue ? 'border-danger border-2' : '' }}">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <a href="{{ route('notification-tasks.open', $task) }}" class="text-decoration-none text-dark fw-bold">{{ $task->title }}</a>
                                @if($task->description)<div class="text-secondary mt-1">{{ $task->description }}</div>@endif
                                <div class="small mt-2 {{ $overdue ? 'text-danger fw-bold' : 'text-secondary' }}">
                                    {{ $task->due_at ? ($overdue ? 'Просрочено: ' : 'Срок: ') . $task->due_at->format('d.m.Y H:i') : 'Без срока выполнения' }}
                                </div>
                            </div>
                            <span class="badge {{ $task->priority === 'urgent' ? 'text-bg-danger' : ($task->priority === 'high' ? 'text-bg-warning' : 'text-bg-secondary') }}">{{ $task->priority }}</span>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <a href="{{ route('notification-tasks.open', $task) }}" class="btn btn-sm btn-dark rounded-pill">Открыть</a>
                            <form action="{{ route('notification-tasks.complete', $task) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-sm btn-outline-success rounded-pill">Выполнено</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="text-secondary py-4">Назначенных открытых задач нет.</div>
                @endforelse
                {{ $tasks->links() }}
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-soft p-4">
                <h2 class="h4 mb-3">Уведомления</h2>
                @forelse($notifications as $notification)
                    <a href="{{ route('notifications.open', $notification) }}" class="d-block border rounded-4 p-3 mb-3 text-decoration-none text-dark {{ $notification->read_at ? 'opacity-75' : 'border-primary' }}">
                        <div class="d-flex justify-content-between gap-2">
                            <strong>{{ $notification->title }}</strong>
                            @if(!$notification->read_at)<span class="badge text-bg-primary">Новое</span>@endif
                        </div>
                        <div class="text-secondary mt-1">{{ $notification->body }}</div>
                        <div class="small text-secondary mt-2">{{ $notification->created_at->format('d.m.Y H:i') }}</div>
                    </a>
                @empty
                    <div class="text-secondary py-4">Уведомлений пока нет.</div>
                @endforelse
                {{ $notifications->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
