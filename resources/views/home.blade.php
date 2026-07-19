@extends('layouts.app')

@php($title = 'Сиделка24')

@push('styles')
<style>
    .home-hero {
        position: relative;
        overflow: hidden;
        border-radius: 32px;
        background:
            radial-gradient(circle at top left, rgba(255, 255, 255, 0.72), transparent 28%),
            linear-gradient(135deg, rgba(18, 88, 98, 0.96), rgba(30, 131, 126, 0.92));
        color: #fff;
    }

    .home-hero::after {
        content: "";
        position: absolute;
        inset: auto -8% -28% auto;
        width: 360px;
        height: 360px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        filter: blur(4px);
    }

    .hero-grid {
        position: relative;
        z-index: 1;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.55rem 0.9rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        color: rgba(255, 255, 255, 0.96);
        font-size: 0.92rem;
    }

    .hero-title {
        font-size: clamp(2.4rem, 6vw, 4.5rem);
        line-height: 0.98;
        font-weight: 800;
        letter-spacing: -0.03em;
    }

    .hero-copy {
        color: rgba(255, 255, 255, 0.82);
        max-width: 640px;
        font-size: 1.08rem;
    }

    .signal-card {
        border-radius: 26px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.14);
        backdrop-filter: blur(8px);
    }

    .signal-card .value {
        font-size: 2rem;
        font-weight: 800;
    }

    .trust-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
    }

    .trust-item,
    .step-card,
    .feature-panel,
    .cta-panel {
        border-radius: 24px;
        border: 1px solid rgba(31, 111, 120, 0.1);
        background: rgba(255, 255, 255, 0.94);
        box-shadow: 0 18px 45px rgba(19, 46, 61, 0.08);
    }

    .step-index {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #d7ebe5;
        color: #174f56;
        font-weight: 800;
    }

    .feature-panel.accent {
        background: linear-gradient(180deg, rgba(215, 235, 229, 0.7), rgba(255, 255, 255, 0.96));
    }

    .feature-panel.warning {
        background: linear-gradient(180deg, rgba(244, 239, 231, 0.82), rgba(255, 255, 255, 0.96));
    }

    .status-line {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        margin-bottom: 0.8rem;
        color: #53616d;
    }

    .status-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #1f6f78;
        box-shadow: 0 0 0 6px rgba(31, 111, 120, 0.12);
    }

    .care-card {
        border-radius: 24px;
        background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(246, 250, 249, 0.98));
        border: 1px solid rgba(31, 111, 120, 0.08);
        box-shadow: 0 18px 45px rgba(19, 46, 61, 0.08);
    }

    .care-card p {
        color: #5f6d78;
    }

    .urgent-ribbon {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.8rem;
        border-radius: 999px;
        background: #ffe3d8;
        color: #8d3b20;
        font-weight: 700;
    }

    .cta-panel {
        background:
            radial-gradient(circle at right top, rgba(215, 235, 229, 0.75), transparent 22%),
            linear-gradient(135deg, rgba(255,255,255,0.98), rgba(244, 248, 249, 0.98));
    }

    .seo-card {
        border-radius: 24px;
        border: 1px solid rgba(31, 111, 120, 0.08);
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 18px 45px rgba(19, 46, 61, 0.06);
    }

    .sticky-mobile-cta {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1040;
        padding: 0.85rem 1rem calc(0.85rem + env(safe-area-inset-bottom));
        background: rgba(255, 255, 255, 0.94);
        border-top: 1px solid rgba(31, 111, 120, 0.12);
        box-shadow: 0 -12px 30px rgba(19, 46, 61, 0.12);
        backdrop-filter: blur(10px);
    }

    @media (max-width: 991px) {
        .trust-strip {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575px) {
        .trust-strip {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="container py-4 py-lg-5">
    <section class="home-hero p-4 p-lg-5 mb-5">
        <div class="hero-grid row g-4 align-items-center">
            <div class="col-xl-7">
                <span class="hero-badge mb-3">Подбор сиделки по услугам, календарю, проверке документов и ставке</span>
                <h1 class="hero-title mb-3">Сервис заказа сиделок для семей, которым нужна помощь без хаоса и риска</h1>
                <p class="hero-copy mb-4">
                    Клиент задает реальные даты и часы на календаре, указывает нужные услуги и получает подходящих сиделок.
                    Сиделка проходит профиль, документы, договор и выбирает, что может делать, а что нет.
                    Оплата проходит через сайт и переводится только после подтверждения смены.
                </p>
                <div class="d-flex flex-column flex-sm-row gap-3 mb-4">
                    <a href="{{ route('register') }}" class="btn btn-light btn-lg rounded-pill px-4">Заказать сиделку</a>
                    <a href="{{ route('caregivers.index') }}" class="btn btn-outline-light btn-lg rounded-pill px-4">Смотреть анкеты</a>
                </div>
                <div class="d-flex flex-wrap gap-3">
                    <span class="hero-badge">Экстренный заказ “нужна сегодня”</span>
                    <span class="hero-badge">Семейный доступ для родственников</span>
                    <span class="hero-badge">Договоры и документы внутри платформы</span>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="signal-card p-4 mb-3">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="small text-white-50">Анкет сиделок</div>
                            <div class="value">{{ $stats['caregivers_total'] }}</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-white-50">Проверенных</div>
                            <div class="value">{{ $stats['verified_caregivers'] }}</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-white-50">Активных заказов</div>
                            <div class="value">{{ $stats['active_orders'] }}</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-white-50">Медуслуг в каталоге</div>
                            <div class="value">{{ $stats['medical_services'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="signal-card p-4">
                    <div class="status-line"><span class="status-dot"></span><span>Календарь по датам и часам вместо неудобных списков</span></div>
                    <div class="status-line"><span class="status-dot"></span><span>Список услуг с разделением на бытовые и медицинские</span></div>
                    <div class="status-line"><span class="status-dot"></span><span>Подбор совпадений и переписка до выхода на смену</span></div>
                    <div class="status-line mb-0"><span class="status-dot"></span><span>Оплата с удержанием до подтверждения клиентом</span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <div class="trust-strip">
            <div class="trust-item p-4">
                <div class="text-uppercase small text-secondary mb-2">Безопасность</div>
                <h2 class="h5 mb-2">Документы и договор</h2>
                <p class="mb-0 text-secondary">Собираем паспортные данные, реквизиты, документы сиделки и формируем договоры внутри сервиса.</p>
            </div>
            <div class="trust-item p-4">
                <div class="text-uppercase small text-secondary mb-2">Оплата</div>
                <h2 class="h5 mb-2">Деньги под контролем</h2>
                <p class="mb-0 text-secondary">Клиент оплачивает на сайт, а сиделка получает выплату только после подтвержденной отработки.</p>
            </div>
            <div class="trust-item p-4">
                <div class="text-uppercase small text-secondary mb-2">Подбор</div>
                <h2 class="h5 mb-2">Не просто каталог</h2>
                <p class="mb-0 text-secondary">Сервис сопоставляет услуги, ставку, город, занятость и конкретные интервалы по календарю.</p>
            </div>
            <div class="trust-item p-4">
                <div class="text-uppercase small text-secondary mb-2">Репутация</div>
                <h2 class="h5 mb-2">Отзывы в обе стороны</h2>
                <p class="mb-0 text-secondary">История заказов, рейтинг сиделок и отзывы о клиентах помогают принимать решение спокойнее.</p>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="step-card p-4 h-100">
                    <span class="step-index mb-3">1</span>
                    <h2 class="h4">Клиент создает заказ</h2>
                    <p class="mb-0 text-secondary">Указывает адрес, бюджет, пациента, нужные услуги и выделяет точные даты и часы на календаре.</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="step-card p-4 h-100">
                    <span class="step-index mb-3">2</span>
                    <h2 class="h4">Платформа подбирает совпадения</h2>
                    <p class="mb-0 text-secondary">Учитываются навыки сиделки, наличие медподготовки, документы, ставка и свободные интервалы.</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="step-card p-4 h-100">
                    <span class="step-index mb-3">3</span>
                    <h2 class="h4">Чат, оплата, подтверждение</h2>
                    <p class="mb-0 text-secondary">После согласования условий клиент резервирует оплату, а после смены подтверждает выполнение работы.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="feature-panel accent p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                        <div>
                            <div class="text-uppercase small text-secondary mb-2">Для семей</div>
                            <h2 class="section-title mb-0">Главное на продакшн-уровне уже заложено</h2>
                        </div>
                        <span class="urgent-ribbon">Срочный сценарий: нужна сиделка сегодня</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card-soft p-3 h-100">
                                <strong>График по реальным слотам</strong>
                                <div class="text-secondary small mt-2">Не “понедельник-пятница”, а конкретные даты и часы, когда действительно нужна помощь.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card-soft p-3 h-100">
                                <strong>Семейный доступ</strong>
                                <div class="text-secondary small mt-2">Дочь, сын или другой родственник могут видеть заказы, участвовать в согласовании и чате.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card-soft p-3 h-100">
                                <strong>Шаблоны повторяющихся заказов</strong>
                                <div class="text-secondary small mt-2">Подходит для постоянного ухода несколько раз в неделю и долгих повторяющихся смен.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card-soft p-3 h-100">
                                <strong>Свои услуги клиента</strong>
                                <div class="text-secondary small mt-2">Если нужного пункта нет в общем каталоге, клиент добавляет его в свою заявку отдельно.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="feature-panel warning p-4 h-100">
                    <div class="text-uppercase small text-secondary mb-2">Для сиделок</div>
                    <h2 class="h3 mb-3">Анкета, которой можно доверять</h2>
                    <div class="status-line"><span class="status-dot"></span><span>Отдельный список “могу выполнять” и “не выполняю”</span></div>
                    <div class="status-line"><span class="status-dot"></span><span>Медицинские услуги отделены от бытовых</span></div>
                    <div class="status-line"><span class="status-dot"></span><span>Графический календарь доступности по датам и времени</span></div>
                    <div class="status-line"><span class="status-dot"></span><span>Документы, реквизиты, договор и дальнейшие выплаты</span></div>
                    <div class="status-line mb-0"><span class="status-dot"></span><span>Отзывы, рейтинг и история завершенных заказов</span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
            <div>
                <div class="text-uppercase small text-secondary">Рекомендации</div>
                <h2 class="section-title mb-0">Проверенные сиделки в каталоге</h2>
            </div>
            <a href="{{ route('caregivers.index') }}" class="btn btn-outline-dark rounded-pill">Все анкеты</a>
        </div>
        <div class="row g-4">
            @foreach($featuredCaregivers as $profile)
                <div class="col-lg-4">
                    <div class="care-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <h3 class="h5 mb-1">{{ $profile->user->name }}</h3>
                                <div class="text-secondary">{{ $profile->user->city }} • {{ $profile->experience_years }} лет опыта</div>
                            </div>
                            <span class="badge {{ $profile->documents_verified ? 'text-bg-success' : 'text-bg-warning' }}">
                                {{ $profile->documents_verified ? 'Проверена' : 'На проверке' }}
                            </span>
                        </div>
                        <p class="mb-3">{{ $profile->bio }}</p>
                        <div class="mb-3">
                            @foreach($profile->availableServices()->take(4) as $service)
                                <span class="service-chip">{{ $service->name }}</span>
                            @endforeach
                        </div>
                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <div>
                                <div class="price-tag">от {{ number_format($profile->hourly_rate_from, 0, ',', ' ') }} ₽/час</div>
                                <div class="text-secondary small">Рейтинг {{ number_format((float) $profile->user->rating, 1, ',', ' ') }} • {{ $profile->user->reviews_count }} отзывов</div>
                            </div>
                            <a href="{{ route('caregivers.show', $profile) }}" class="btn btn-dark rounded-pill">Открыть</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mb-5">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="feature-panel p-4 h-100">
                    <div class="text-uppercase small text-secondary mb-2">Услуги</div>
                    <h2 class="h3 mb-3">Что можно выбрать в заказе</h2>
                    <div>
                        @foreach($services as $service)
                            <span class="service-chip">{{ $service->name }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="feature-panel p-4 h-100">
                    <div class="text-uppercase small text-secondary mb-2">Сейчас на платформе</div>
                    <h2 class="h3 mb-3">Живые примеры активных заказов</h2>
                    @foreach($activeOrders as $order)
                        <div class="border rounded-4 p-3 mb-3">
                            <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                <strong>{{ $order->title }}</strong>
                                <span class="badge text-bg-light">{{ $order->status }}</span>
                            </div>
                            <div class="text-secondary mb-2">{{ $order->city }} • {{ $order->starts_at->format('d.m.Y H:i') }} - {{ $order->ends_at->format('d.m.Y H:i') }}</div>
                            <div class="mb-2">
                                @foreach($order->services as $service)
                                    <span class="service-chip">{{ $service->name }}</span>
                                @endforeach
                            </div>
                            <div class="fw-bold">Бюджет: от {{ number_format($order->hourly_budget, 0, ',', ' ') }} ₽/час</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
            <div>
                <div class="text-uppercase small text-secondary">Новости</div>
                <h2 class="section-title mb-0">Материалы для семей и сиделок</h2>
            </div>
            <a href="{{ route('news.index') }}" class="btn btn-outline-dark rounded-pill">Все новости</a>
        </div>
        <div class="row g-4">
            @foreach($latestNews as $post)
                <div class="col-lg-4">
                    <article class="news-card p-4 h-100">
                        <div class="text-secondary small mb-2">{{ optional($post->published_at)->format('d.m.Y') }}</div>
                        <h3 class="h4">{{ $post->title }}</h3>
                        <p class="mb-0 text-secondary">{{ $post->excerpt }}</p>
                    </article>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
            <div>
                <div class="text-uppercase small text-secondary">SEO-раздел</div>
                <h2 class="section-title mb-0">Сиделки по городам и типам услуг</h2>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="seo-card p-4 h-100">
                    <h3 class="h4 mb-3">Сиделка в Москве</h3>
                    <p class="text-secondary mb-3">
                        Заказать сиделку в Москве можно для дневных, ночных и срочных смен, а также для длительного ухода с проживанием.
                        На платформе клиент выбирает даты и часы на календаре, отмечает нужные услуги и получает подходящие анкеты сиделок по городу,
                        ставке, опыту и наличию документов.
                    </p>
                    <p class="text-secondary mb-0">
                        Для Москвы особенно важны быстрый отклик, экстренные заказы “нужна сиделка сегодня”, уход после выписки из стационара,
                        сопровождение по квартире, контроль лекарств и помощь лежачим пациентам.
                    </p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="seo-card p-4 h-100">
                    <h3 class="h4 mb-3">Сиделка в Химках</h3>
                    <p class="text-secondary mb-3">
                        Если нужна сиделка в Химках, сервис помогает быстро подобрать исполнителя по конкретным сменам: на несколько часов, на день,
                        на ночь или на длительный период. В заявке можно указать адрес, график, бюджет и свои дополнительные услуги, если их нет в каталоге.
                    </p>
                    <p class="text-secondary mb-0">
                        Такой формат подходит для ухода за пожилыми родственниками дома, после операции, после инсульта и в ситуациях,
                        когда семье нужен постоянный помощник рядом и прозрачная оплата через сайт.
                    </p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="seo-card p-4 h-100">
                    <h3 class="h4 mb-3">Медицинская сиделка</h3>
                    <p class="text-secondary mb-3">
                        Медицинская сиделка нужна, когда важны не только бытовой уход и присутствие, но и навыки медицинских манипуляций.
                        На платформе медицинские услуги отмечены отдельно, поэтому клиент сразу видит, что именно требует подготовки,
                        а сиделка выбирает, какие процедуры она может выполнять.
                    </p>
                    <p class="text-secondary mb-0">
                        Это удобно для заказов с контролем лекарств, уколами, измерением давления и сахара, наблюдением после операций
                        и уходом за пациентами, которым нужен повышенный уровень ответственности.
                    </p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="seo-card p-4 h-100">
                    <h3 class="h4 mb-3">Сиделка для лежачего больного</h3>
                    <p class="text-secondary mb-3">
                        Сиделка для лежачего больного подбирается с учетом опыта, доступности по графику, бытовых и гигиенических услуг,
                        а также готовности работать в сложных условиях дома или с проживанием. Семья может заранее описать состояние пациента,
                        особенности ухода и точные часы присутствия.
                    </p>
                    <p class="text-secondary mb-0">
                        В заказ можно включить гигиенический уход, смену белья, вынос отходов, приготовление еды, помощь при перемещении,
                        ночной присмотр и сопровождение восстановления после тяжелых состояний.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-panel p-4 p-lg-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="text-uppercase small text-secondary mb-2">Следующий шаг</div>
                <h2 class="section-title mb-3">Можно искать сиделку, публиковать анкету и проводить сделку внутри одной платформы</h2>
                <p class="mb-0 text-secondary">Подходит и для разового срочного заказа, и для долгого ухода с графиком, документами, перепиской и безопасной оплатой.</p>
            </div>
            <div class="col-lg-4">
                <div class="d-grid gap-3">
                    <a href="{{ route('register') }}" class="btn btn-dark btn-lg rounded-pill">Начать работу</a>
                    <a href="{{ route('caregivers.index') }}" class="btn btn-outline-dark btn-lg rounded-pill">Перейти в каталог</a>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="sticky-mobile-cta d-lg-none">
    <a href="{{ route('register') }}" class="btn btn-dark btn-lg rounded-pill w-100">Заказать сиделку сегодня</a>
</div>
@endsection
