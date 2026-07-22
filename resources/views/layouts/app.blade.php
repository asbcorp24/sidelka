<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Сиделка24' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @stack('styles')
    <style>
        :root {
            --brand: #1f6f78;
            --brand-dark: #174f56;
            --sand: #f4efe7;
            --mint: #d7ebe5;
            --ink: #213547;
            --muted: #6c7a86;
            --card-shadow: 0 18px 45px rgba(19, 46, 61, 0.12);
        }

        body {
            font-family: 'Manrope', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top right, rgba(215, 235, 229, 0.85), transparent 28%),
                linear-gradient(180deg, #f9f6f1 0%, #fff 100%);
        }

        .navbar-brand { font-weight: 800; letter-spacing: 0.03em; }
        .hero-shell, .card-soft { border: 1px solid rgba(31, 111, 120, 0.08); box-shadow: var(--card-shadow); }
        .hero-shell { background: linear-gradient(135deg, rgba(244, 239, 231, 0.95), rgba(215, 235, 229, 0.95)); border-radius: 28px; }
        .card-soft { border-radius: 22px; background: rgba(255, 255, 255, 0.92); }
        .badge-soft { background: rgba(31, 111, 120, 0.1); color: var(--brand-dark); }
        .section-title { font-size: clamp(1.8rem, 3vw, 2.7rem); font-weight: 800; }
        .price-tag { color: var(--brand-dark); font-weight: 800; font-size: 1.4rem; }

        .availability-chip, .service-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            padding: 0.45rem 0.8rem;
            background: #f4f8f9;
            color: var(--ink);
            font-size: 0.92rem;
            margin: 0 0.45rem 0.45rem 0;
        }

        .metric {
            background: #fff;
            border-radius: 20px;
            padding: 1.2rem;
            height: 100%;
            border: 1px solid rgba(31, 111, 120, 0.08);
        }

        .metric .value { font-size: 1.9rem; font-weight: 800; }
        .chat-bubble { border-radius: 18px; padding: 1rem 1.1rem; max-width: 85%; }
        .chat-bubble.client { background: var(--sand); }
        .chat-bubble.caregiver { background: var(--mint); margin-left: auto; }
        .news-card { background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(244, 248, 249, 0.96)); border-radius: 24px; border: 1px solid rgba(31, 111, 120, 0.08); }
        .crm-table td { vertical-align: middle; }
        .crm-overdue { border-left: 4px solid #dc3545 !important; }
        footer { color: var(--muted); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg py-3">
        <div class="container">
            <a class="navbar-brand text-uppercase" href="{{ route('home') }}">Сиделка24</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    @guest
                        <li class="nav-item"><a class="nav-link" href="{{ route('caregivers.index') }}">Каталог сиделок</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('news.index') }}">Новости</a></li>
                    @endguest

                    @auth
                        @if(auth()->user()->isClient())
                            <li class="nav-item"><a class="btn btn-dark rounded-pill px-4" href="{{ route('client.dashboard') }}">Мой кабинет</a></li>
                            <li class="nav-item"><a class="btn btn-outline-dark rounded-pill px-4" href="{{ route('client.legal') }}">Документы</a></li>
                        @elseif(auth()->user()->isCaregiver())
                            <li class="nav-item"><a class="btn btn-dark rounded-pill px-4" href="{{ route('caregiver.dashboard') }}">Мой кабинет</a></li>
                            <li class="nav-item"><a class="btn btn-outline-dark rounded-pill px-4" href="{{ route('caregiver.legal') }}">Документы</a></li>
                        @elseif(auth()->user()->isCrm())
                            <li class="nav-item"><a class="btn btn-dark rounded-pill px-4" href="{{ route('crm.dashboard') }}">CRM</a></li>
                            <li class="nav-item"><a class="btn btn-outline-dark rounded-pill px-4" href="{{ route('crm.people.index') }}">Люди</a></li>
                            <li class="nav-item"><a class="btn btn-outline-dark rounded-pill px-4" href="{{ route('crm.contracts.index') }}">Договоры</a></li>
                            <li class="nav-item"><a class="btn btn-outline-dark rounded-pill px-4" href="{{ route('crm.finance.index') }}">Финансы</a></li>
                        @elseif(auth()->user()->isAdmin())
                            <li class="nav-item"><a class="btn btn-dark rounded-pill px-4" href="{{ route('crm.dashboard') }}">CRM</a></li>
                            <li class="nav-item"><a class="btn btn-outline-dark rounded-pill px-4" href="{{ route('crm.contracts.index') }}">Договоры</a></li>
                            <li class="nav-item"><a class="btn btn-outline-dark rounded-pill px-4" href="{{ route('crm.finance.index') }}">Финансы</a></li>
                            <li class="nav-item"><a class="btn btn-outline-dark rounded-pill px-4" href="{{ route('admin.dashboard') }}">Админка</a></li>
                        @endif
                        <li class="nav-item">
                            <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-dark rounded-pill px-4">Выход</button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item"><a class="btn btn-dark rounded-pill px-4" href="{{ route('login') }}">Вход</a></li>
                        <li class="nav-item"><a class="btn btn-outline-dark rounded-pill px-4" href="{{ route('register') }}">Регистрация</a></li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main class="pb-5">
        <div class="container">
            @if(session('status'))
                <div class="alert alert-success rounded-4 mt-3 mb-0">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger rounded-4 mt-3 mb-0">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @auth
            @if(isset($order) && $order instanceof \App\Models\Order)
                @include('contracts.order-panel')
                @include('payments.shift-settlement-panel')
            @endif
            @if(isset($crmRequest) && $crmRequest instanceof \App\Models\CrmRequest)
                @include('contracts.crm-panel')
            @endif
        @endauth

        @yield('content')
    </main>

    <footer class="container py-4 border-top">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
            <span>Сервис подбора сиделок с безопасной оплатой, календарем смен и отзывами.</span>
            <span>Подходит для разовых, срочных и постоянных заказов с прозрачными условиями для клиента и сиделки.</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
