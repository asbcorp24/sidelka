@php
    $center = $notificationCenter ?? [
        'tasks' => collect(),
        'notifications' => collect(),
        'task_count' => 0,
        'notification_count' => 0,
        'total_count' => 0,
    ];
@endphp

<li class="nav-item dropdown">
    <button
        class="btn btn-link nav-link notification-bell-button position-relative px-2"
        type="button"
        data-bs-toggle="dropdown"
        data-bs-auto-close="outside"
        aria-expanded="false"
        aria-label="Задачи и уведомления"
    >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
            <path d="M10 21h4"></path>
        </svg>
        @if($center['total_count'] > 0)
            <span class="notification-badge">{{ $center['total_count'] > 99 ? '99+' : $center['total_count'] }}</span>
        @endif
    </button>

    <div class="dropdown-menu dropdown-menu-end notification-dropdown p-0">
        <div class="d-flex justify-content-between align-items-center px-3 py-3 border-bottom">
            <div>
                <strong>Задачи и уведомления</strong>
                <div class="small text-secondary">Персонально для {{ auth()->user()->name }}</div>
            </div>
            @if($center['notification_count'] > 0)
                <form action="{{ route('notifications.read-all') }}" method="POST">
                    @csrf
                    <button class="btn btn-sm btn-link text-decoration-none">Прочитать все</button>
                </form>
            @endif
        </div>

        <div class="notification-scroll">
            @if($center['tasks']->isNotEmpty())
                <div class="notification-section-title">Мои задачи · {{ $center['task_count'] }}</div>
                @foreach($center['tasks'] as $task)
                    @php
                        $overdue = $task->due_at && $task->due_at->isPast();
                        $priorityClass = match($task->priority) {
                            'urgent' => 'danger',
                            'high' => 'warning',
                            default => 'secondary',
                        };
                    @endphp
                    <div class="notification-item border-bottom {{ $overdue ? 'notification-overdue' : '' }}">
                        <a href="{{ route('notification-tasks.open', $task) }}" class="notification-main-link">
                            <div class="d-flex justify-content-between gap-2">
                                <strong class="small">{{ $task->title }}</strong>
                                <span class="badge text-bg-{{ $priorityClass }}">{{ $task->priority }}</span>
                            </div>
                            @if($task->description)<div class="small text-secondary mt-1">{{ \Illuminate\Support\Str::limit($task->description, 105) }}</div>@endif
                            <div class="small mt-1 {{ $overdue ? 'text-danger fw-bold' : 'text-secondary' }}">
                                {{ $task->due_at ? ($overdue ? 'Просрочено: ' : 'Срок: ') . $task->due_at->format('d.m.Y H:i') : 'Без срока' }}
                            </div>
                        </a>
                        <form action="{{ route('notification-tasks.complete', $task) }}" method="POST" class="mt-2">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-sm btn-outline-success rounded-pill">Выполнено</button>
                        </form>
                    </div>
                @endforeach
            @endif

            @if($center['notifications']->isNotEmpty())
                <div class="notification-section-title">Новые уведомления · {{ $center['notification_count'] }}</div>
                @foreach($center['notifications'] as $notification)
                    <a href="{{ route('notifications.open', $notification) }}" class="notification-item notification-main-link border-bottom d-block">
                        <strong class="small">{{ $notification->title }}</strong>
                        <div class="small text-secondary mt-1">{{ \Illuminate\Support\Str::limit($notification->body, 120) }}</div>
                        <div class="small text-secondary mt-1">{{ $notification->created_at->format('d.m.Y H:i') }}</div>
                    </a>
                @endforeach
            @endif

            @if($center['total_count'] === 0)
                <div class="text-center text-secondary p-4">
                    <div class="mb-2">Новых задач и уведомлений нет</div>
                    <small>Здесь появятся назначенные вам действия.</small>
                </div>
            @endif
        </div>

        <a href="{{ route('notifications.index') }}" class="d-block text-center text-decoration-none px-3 py-3 border-top fw-semibold">
            Открыть все
        </a>
    </div>
</li>
