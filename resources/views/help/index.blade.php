@extends('layouts.app')

@php
    $title = 'Помощь и инструкции';
    $roleTitle = $audienceLabels[$audience] ?? 'Пользователь';
@endphp

@push('styles')
<style>
    .help-hero { background:linear-gradient(135deg,rgba(31,111,120,.96),rgba(23,79,86,.96)); color:#fff; border-radius:28px; box-shadow:var(--card-shadow); }
    .help-search { min-height:54px; border:0; border-radius:18px; box-shadow:0 12px 35px rgba(0,0,0,.12); }
    .help-role-tabs .btn { white-space:nowrap; }
    .help-sidebar { position:sticky; top:18px; }
    .help-sidebar a { display:block; padding:.58rem .75rem; border-radius:12px; color:var(--ink); text-decoration:none; }
    .help-sidebar a:hover { background:rgba(31,111,120,.08); color:var(--brand-dark); }
    .help-topic { scroll-margin-top:18px; }
    .help-topic h2 { font-weight:800; }
    .help-step { display:flex; gap:.9rem; align-items:flex-start; margin-bottom:1rem; }
    .help-step-number { flex:0 0 34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:var(--brand); color:#fff; font-weight:800; }
    .help-note { border-left:4px solid var(--brand); background:#f4f8f9; padding:1rem 1.1rem; border-radius:0 16px 16px 0; }
    .help-warning { border-left-color:#dc3545; background:#fff5f5; }
    .help-success { border-left-color:#198754; background:#f1fbf6; }
    .help-permission-table td,.help-permission-table th { vertical-align:middle; }
    .help-empty { display:none; }
    .help-key { display:inline-block; min-width:30px; padding:.12rem .42rem; border:1px solid #ced4da; border-bottom-width:2px; border-radius:6px; background:#fff; font-size:.82rem; text-align:center; }
    .help-path { font-family:monospace; background:#eef2f3; padding:.1rem .35rem; border-radius:6px; }
    @media print {
        nav,footer,.help-controls,.help-sidebar,.btn { display:none!important; }
        body { background:#fff!important; }
        .card-soft,.help-hero { box-shadow:none!important; border:1px solid #ddd!important; }
        .help-hero { color:#000!important; background:#fff!important; }
        .help-topic { break-inside:avoid; }
    }
</style>
@endpush

@section('content')
<div class="container py-4 py-lg-5">
    <section class="help-hero p-4 p-lg-5 mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="text-uppercase small opacity-75 mb-2">Справочный центр Сиделка24</div>
                <h1 class="display-6 fw-bold mb-3">Полная инструкция: {{ $roleTitle }}</h1>
                <p class="lead mb-0 opacity-90">Пошаговая работа с аккаунтом, заказами, документами, оплатой, сменами, уведомлениями и внутренними разделами платформы.</p>
            </div>
            <div class="col-lg-4 help-controls">
                <label for="helpSearch" class="form-label fw-bold">Поиск по инструкции</label>
                <input id="helpSearch" type="search" class="form-control help-search px-4" placeholder="Например: документ, оплата, смена…" autocomplete="off">
                <div class="d-flex gap-2 mt-3 flex-wrap">
                    <button type="button" class="btn btn-light rounded-pill px-4" onclick="window.print()">Печать / PDF</button>
                    <button type="button" class="btn btn-outline-light rounded-pill px-4" id="helpExpandAll">Развернуть всё</button>
                </div>
            </div>
        </div>
    </section>

    @if(count($allowedAudiences) > 1)
        <div class="card-soft p-3 mb-4 help-controls help-role-tabs overflow-auto">
            <div class="d-flex gap-2">
                @foreach($allowedAudiences as $role)
                    <a href="{{ route('help.index', ['role' => $role]) }}" class="btn rounded-pill px-4 {{ $audience === $role ? 'btn-dark' : 'btn-outline-dark' }}">
                        {{ $audienceLabels[$role] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div id="helpNoResults" class="alert alert-warning rounded-4 help-empty">По вашему запросу ничего не найдено. Попробуйте другое слово.</div>

    <div class="row g-4">
        <aside class="col-lg-3 help-controls">
            <div class="card-soft p-3 help-sidebar">
                <div class="fw-bold px-2 mb-2">Содержание</div>
                <a href="#quick-start">Быстрый старт</a>
                <a href="#account">Аккаунт и безопасность</a>
                <a href="#navigation">Меню и уведомления</a>
                <a href="#documents-common">Документы и подпись</a>
                @if($audience === 'guest')
                    <a href="#guest-registration">Регистрация</a>
                    <a href="#guest-catalog">Каталог сиделок</a>
                @elseif($audience === 'client')
                    <a href="#client-order">Создание заказа</a>
                    <a href="#client-caregiver">Выбор сиделки</a>
                    <a href="#client-payment">Оплата</a>
                    <a href="#client-shift">Работа и смены</a>
                    <a href="#client-problems">Споры и отмена</a>
                @elseif($audience === 'caregiver')
                    <a href="#caregiver-profile">Профиль и допуск</a>
                    <a href="#caregiver-orders">Заказы</a>
                    <a href="#caregiver-shift">Работа на смене</a>
                    <a href="#caregiver-money">Расходы и выплаты</a>
                    <a href="#caregiver-safety">Безопасность</a>
                @elseif($audience === 'crm')
                    <a href="#crm-permissions">Роли и права</a>
                    <a href="#crm-requests">Заявки и люди</a>
                    <a href="#crm-tasks">Задачи</a>
                    <a href="#crm-documents">Допуски</a>
                    <a href="#crm-control">Споры и инциденты</a>
                    <a href="#crm-finance">Финансы и договоры</a>
                @elseif($audience === 'admin')
                    <a href="#admin-start">Панель администратора</a>
                    <a href="#admin-settings">Настройки сайта</a>
                    <a href="#admin-staff">Сотрудники и права</a>
                    <a href="#admin-content">Контент и пользователи</a>
                    <a href="#admin-operations">Обслуживание системы</a>
                @endif
                <a href="#faq">Частые вопросы</a>
            </div>
        </aside>

        <div class="col-lg-9" id="helpTopics">
            <section id="quick-start" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                <h2 class="h3 mb-3">Быстрый старт</h2>
                @if($audience === 'guest')
                    <div class="help-step"><div class="help-step-number">1</div><div><strong>Откройте каталог.</strong> Просматривайте анкеты, опыт, услуги, рейтинг и доступность сиделок без регистрации.</div></div>
                    <div class="help-step"><div class="help-step-number">2</div><div><strong>Создайте аккаунт.</strong> Выберите роль клиента или сиделки, укажите действующий email и подтвердите его.</div></div>
                    <div class="help-step"><div class="help-step-number">3</div><div><strong>Войдите в кабинет.</strong> После подтверждения почты будут доступны функции выбранной роли.</div></div>
                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <a href="{{ route('caregivers.index') }}" class="btn btn-dark rounded-pill">Каталог сиделок</a>
                        @guest<a href="{{ route('register') }}" class="btn btn-outline-dark rounded-pill">Регистрация</a>@endguest
                    </div>
                @elseif($audience === 'client')
                    <div class="help-step"><div class="help-step-number">1</div><div>Заполните личные и договорные данные клиента.</div></div>
                    <div class="help-step"><div class="help-step-number">2</div><div>Создайте заказ или выберите сиделку в каталоге.</div></div>
                    <div class="help-step"><div class="help-step-number">3</div><div>Согласуйте кандидата, подпишите договор и пополните баланс.</div></div>
                    <div class="help-step"><div class="help-step-number">4</div><div>Контролируйте смены, сообщения, расходы и подтверждайте выполненную работу.</div></div>
                    @auth
                        @if(auth()->user()->isClient())
                            <div class="d-flex gap-2 flex-wrap mt-3">
                                <a href="{{ route('client.dashboard') }}" class="btn btn-dark rounded-pill">Мой кабинет</a>
                                <a href="{{ route('client.orders.create') }}" class="btn btn-outline-dark rounded-pill">Создать заказ</a>
                            </div>
                        @endif
                    @endauth
                @elseif($audience === 'caregiver')
                    <div class="help-step"><div class="help-step-number">1</div><div>Заполните профиль, услуги, стоимость и доступность.</div></div>
                    <div class="help-step"><div class="help-step-number">2</div><div>Загрузите обязательные документы и дождитесь проверки CRM.</div></div>
                    <div class="help-step"><div class="help-step-number">3</div><div>Откликайтесь на открытые заказы или принимайте приглашения.</div></div>
                    <div class="help-step"><div class="help-step-number">4</div><div>Ведите журнал смены, фиксируйте расходы и завершайте смену через кабинет.</div></div>
                    @auth
                        @if(auth()->user()->isCaregiver())
                            <div class="d-flex gap-2 flex-wrap mt-3">
                                <a href="{{ route('caregiver.dashboard') }}" class="btn btn-dark rounded-pill">Мой кабинет</a>
                                <a href="{{ route('caregiver.orders.open') }}" class="btn btn-outline-dark rounded-pill">Открытые заказы</a>
                            </div>
                        @endif
                    @endauth
                @elseif($audience === 'crm')
                    <div class="help-step"><div class="help-step-number">1</div><div>Проверьте свою роль и доступные разделы — меню формируется по разрешениям.</div></div>
                    <div class="help-step"><div class="help-step-number">2</div><div>Работайте с персональными задачами через колокольчик в верхнем меню.</div></div>
                    <div class="help-step"><div class="help-step-number">3</div><div>Фиксируйте каждое действие: звонок, назначение, проверку документа, решение спора или инцидента.</div></div>
                    <div class="help-step"><div class="help-step-number">4</div><div>Закрывайте задачи после фактического выполнения, чтобы очередь оставалась актуальной.</div></div>
                    @auth
                        @if(auth()->user()->isCrm() || auth()->user()->isAdmin())
                            <a href="{{ route('crm.dashboard') }}" class="btn btn-dark rounded-pill mt-2">Открыть CRM</a>
                        @endif
                    @endauth
                @elseif($audience === 'admin')
                    <div class="help-step"><div class="help-step-number">1</div><div>Настройте реквизиты, банк, SEO, услуги и новости.</div></div>
                    <div class="help-step"><div class="help-step-number">2</div><div>Создайте CRM-сотрудников и назначьте им роли либо индивидуальные разрешения.</div></div>
                    <div class="help-step"><div class="help-step-number">3</div><div>Контролируйте пользователей, документы, выплаты, договоры, споры и аналитику.</div></div>
                    <div class="help-step"><div class="help-step-number">4</div><div>Следите за планировщиком, резервными копиями и журналами ошибок.</div></div>
                    @auth
                        @if(auth()->user()->isAdmin())
                            <div class="d-flex gap-2 flex-wrap mt-3">
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-dark rounded-pill">Админка</a>
                                <a href="{{ route('crm.dashboard') }}" class="btn btn-outline-dark rounded-pill">CRM</a>
                            </div>
                        @endif
                    @endauth
                @endif
            </section>

            <section id="account" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                <h2 class="h3 mb-3">Аккаунт, вход и безопасность</h2>
                <div class="accordion" id="accountHelpAccordion">
                    <div class="accordion-item border-0 border-bottom">
                        <h3 class="accordion-header"><button class="accordion-button bg-transparent fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#helpAccountLogin">Вход и подтверждение email</button></h3>
                        <div id="helpAccountLogin" class="accordion-collapse collapse show"><div class="accordion-body px-0">
                            После регистрации на email отправляется ссылка подтверждения. Пока адрес не подтверждён, рабочие кабинеты и защищённые операции могут быть недоступны. Если письмо не пришло, проверьте папку «Спам» и запросите отправку повторно на странице подтверждения.
                        </div></div>
                    </div>
                    <div class="accordion-item border-0 border-bottom">
                        <h3 class="accordion-header"><button class="accordion-button collapsed bg-transparent fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#helpAccountRole">Роль аккаунта</button></h3>
                        <div id="helpAccountRole" class="accordion-collapse collapse"><div class="accordion-body px-0">
                            Клиент создаёт и оплачивает заказы. Сиделка принимает работу и получает выплаты. CRM-сотрудник обрабатывает обращения и внутренние процессы. Администратор управляет всей платформой. Рабочие действия следует выполнять только из аккаунта нужной роли.
                        </div></div>
                    </div>
                    <div class="accordion-item border-0">
                        <h3 class="accordion-header"><button class="accordion-button collapsed bg-transparent fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#helpAccountSecurity">Правила безопасности</button></h3>
                        <div id="helpAccountSecurity" class="accordion-collapse collapse"><div class="accordion-body px-0">
                            Не передавайте пароль, одноразовый код подписи или доступ к почте другим людям. Перед подписанием проверяйте текст договора и участника. Не публикуйте паспортные данные и банковские реквизиты в обычном чате. Документы загружайте только через раздел «Документы».
                        </div></div>
                    </div>
                </div>
            </section>

            <section id="navigation" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                <h2 class="h3 mb-3">Меню, колокольчик и уведомления</h2>
                <p>Верхнее меню меняется в зависимости от роли и выданных разрешений. Если раздел не отображается, у аккаунта нет соответствующего права либо email ещё не подтверждён.</p>
                <ul>
                    <li><strong>Красный счётчик на колокольчике</strong> — количество непрочитанных уведомлений и открытых персональных задач.</li>
                    <li><strong>Для CRM</strong> колокольчик показывает только задачи, назначенные конкретному сотруднику, а администратору — также неназначенные задачи.</li>
                    <li><strong>Для клиента и сиделки</strong> отображаются изменения по заказам, оплате, документам, договорам и выплатам.</li>
                    <li>После открытия уведомление отмечается прочитанным. Задача исчезает только после нажатия «Выполнено» либо завершения связанного действия.</li>
                </ul>
                @auth
                    <a href="{{ route('notifications.index') }}" class="btn btn-outline-dark rounded-pill">Все задачи и уведомления</a>
                @endauth
            </section>

            <section id="documents-common" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                <h2 class="h3 mb-3">Документы, договоры и электронная подпись</h2>
                <h3 class="h5">Загрузка документов</h3>
                <p>Выберите тип документа, при необходимости укажите номер, дату выдачи и срок действия, затем загрузите PDF, JPG, JPEG, PNG или WEBP. Максимальный размер файла — 8 МБ.</p>
                <h3 class="h5 mt-4">Статусы</h3>
                <ul>
                    <li><strong>Загружен / На проверке</strong> — документ ожидает решения сотрудника.</li>
                    <li><strong>Проверен</strong> — данные подтверждены; сохраняются сотрудник и дата проверки.</li>
                    <li><strong>Отклонён</strong> — требуется исправление или новый файл; причина указывается в комментарии.</li>
                    <li><strong>Просрочен</strong> — срок действия закончился, даже если документ ранее был проверен.</li>
                </ul>
                <h3 class="h5 mt-4">Подписание договора</h3>
                <ol>
                    <li>Откройте карточку договора и проверьте стороны, заказ, сумму и условия.</li>
                    <li>Запросите одноразовый код по доступному каналу.</li>
                    <li>Введите код до окончания срока действия и подтвердите подпись.</li>
                    <li>После подписания всеми обязательными сторонами статус изменится на «Подписан».</li>
                </ol>
                <div class="help-note">Код электронной подписи нельзя сообщать сотруднику, клиенту, сиделке или третьему лицу. В протоколе сохраняются время, способ подтверждения, IP-адрес и контрольная сумма документа.</div>
            </section>

            @if($audience === 'guest')
                <section id="guest-registration" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Регистрация нового пользователя</h2>
                    <ol>
                        <li>Нажмите «Регистрация» в верхнем меню.</li>
                        <li>Укажите имя, телефон, email и надёжный пароль.</li>
                        <li>Выберите роль: клиент или сиделка.</li>
                        <li>Введите код CAPTCHA и отправьте форму.</li>
                        <li>Откройте письмо и подтвердите email.</li>
                        <li>Войдите в созданный кабинет и заполните профиль.</li>
                    </ol>
                    <div class="help-note">Регистрация также может быть продолжена после входа через VK или Яндекс, если системе не хватает обязательных данных.</div>
                </section>

                <section id="guest-catalog" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Как пользоваться каталогом</h2>
                    <p>В каталоге можно сравнивать сиделок по городу, услугам, опыту, ставке, рейтингу и доступности. Откройте анкету, чтобы увидеть подробное описание и график.</p>
                    <p>Для создания заказа, добавления в избранное и отправки приглашения необходимо войти как клиент.</p>
                    <a href="{{ route('caregivers.index') }}" class="btn btn-dark rounded-pill">Перейти в каталог</a>
                </section>
            @endif

            @if($audience === 'client')
                <section id="client-order" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Создание заказа клиентом</h2>
                    <div class="help-step"><div class="help-step-number">1</div><div><strong>Опишите потребность.</strong> Укажите пациента, адрес, город, необходимые услуги, особенности ухода и важные ограничения.</div></div>
                    <div class="help-step"><div class="help-step-number">2</div><div><strong>Задайте график.</strong> Выберите даты и время. Для длительного ухода можно создать несколько смен и разрешить нескольких сиделок.</div></div>
                    <div class="help-step"><div class="help-step-number">3</div><div><strong>Укажите бюджет.</strong> Проверьте ставку, расчёт базовой суммы и возможные дополнительные расходы.</div></div>
                    <div class="help-step"><div class="help-step-number">4</div><div><strong>Опубликуйте заказ.</strong> После публикации сиделки смогут откликаться, а вы — приглашать подходящих кандидатов.</div></div>
                    <p>Повторяющиеся параметры можно сохранить как шаблон, а сведения о подопечном — как профиль члена семьи.</p>
                </section>

                <section id="client-caregiver" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Выбор и согласование сиделки</h2>
                    <ul>
                        <li><strong>Пригласить</strong> — отправить выбранной сиделке предложение по заказу.</li>
                        <li><strong>Подтвердить кандидата</strong> — назначить откликнувшуюся сиделку.</li>
                        <li><strong>Резерв</strong> — оставить кандидата запасным, не отклоняя его окончательно.</li>
                        <li><strong>Отклонить</strong> — исключить кандидата из текущего подбора.</li>
                    </ul>
                    <div class="help-note help-warning">До назначения проверяйте статус документов и допуска. Система блокирует приглашение или принятие заказа, если обязательный документ не проверен либо просрочен.</div>
                </section>

                <section id="client-payment" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Баланс, оплата и дополнительные расходы</h2>
                    <ol>
                        <li>Откройте кабинет или историю платежей.</li>
                        <li>Пополните внутренний баланс через подключённый эквайринг.</li>
                        <li>После согласования заказа средства резервируются, а не перечисляются сиделке сразу.</li>
                        <li>Дополнительный расход, созданный сиделкой, можно одобрить или отклонить.</li>
                        <li>Выплата за смену формируется после подтверждения выполненной работы и подписания необходимых документов.</li>
                    </ol>
                    <div class="help-note">При отмене неиспользованный остаток возвращается на внутренний баланс с учётом уже выполненных смен и подтверждённых расходов.</div>
                    @auth
                        @if(auth()->user()->isClient())<a href="{{ route('client.payments.index') }}" class="btn btn-outline-dark rounded-pill">История платежей</a>@endif
                    @endauth
                </section>

                <section id="client-shift" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Контроль работы и подтверждение смен</h2>
                    <ul>
                        <li>Используйте чат заказа для организационных сообщений.</li>
                        <li>Просматривайте план ухода, журнал смены, записи, показатели, питание, лекарства и замечания.</li>
                        <li>Проверяйте заявленные дополнительные расходы.</li>
                        <li>Когда сиделка запросит завершение, подтвердите смену либо откройте спор.</li>
                        <li>После подтверждения создаётся акт по конкретной смене и запускается расчёт выплаты.</li>
                        <li>После завершения заказа оставьте отзыв.</li>
                    </ul>
                </section>

                <section id="client-problems" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Отмена, спор, жалоба и инцидент</h2>
                    <h3 class="h5">Спор по смене</h3>
                    <p>Открывайте спор, если работа выполнена не полностью, время отличается, есть вопрос к сумме или акту. Опишите обстоятельства и желаемое решение. До решения спорная выплата не должна завершаться автоматически.</p>
                    <h3 class="h5 mt-3">Инцидент безопасности</h3>
                    <p>Используйте для падения, травмы, резкого ухудшения состояния, пропажи имущества, конфликта или другого риска. При непосредственной угрозе сначала вызывайте экстренные службы, затем фиксируйте событие в системе.</p>
                    <h3 class="h5 mt-3">Отмена заказа</h3>
                    <p>Укажите причину и подробности. Итог возврата зависит от этапа заказа, уже выполненных смен и произведённых выплат.</p>
                </section>
            @endif

            @if($audience === 'caregiver')
                <section id="caregiver-profile" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Профиль, услуги, график и допуск</h2>
                    <ol>
                        <li>Заполните описание опыта, город, ставку, навыки и доступные услуги.</li>
                        <li>Укажите свободные интервалы в календаре.</li>
                        <li>Заполните договорные и банковские данные.</li>
                        <li>Загрузите паспорт, СНИЛС, ИНН, медицинскую книжку и иные требуемые документы.</li>
                        <li>Следите за результатом проверки через колокольчик и раздел «Документы».</li>
                    </ol>
                    <p>Паспорт и медицинская книжка могут блокировать новые смены, если они не проверены или просрочены. Другие обязательные документы отображаются в реестре и также требуют своевременного обновления.</p>
                    @auth
                        @if(auth()->user()->isCaregiver())<a href="{{ route('caregiver.legal') }}" class="btn btn-outline-dark rounded-pill">Мои документы</a>@endif
                    @endauth
                </section>

                <section id="caregiver-orders" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Поиск и принятие заказов</h2>
                    <ul>
                        <li>В разделе открытых заказов изучите адрес, график, задачи ухода, бюджет и требования.</li>
                        <li>Нажмите «Откликнуться», если готовы работать на указанных условиях.</li>
                        <li>При прямом приглашении откройте заказ и примите либо отклоните предложение.</li>
                        <li>До принятия убедитесь, что нет конфликта расписания и документы допущены.</li>
                        <li>После согласования подпишите договор по заказу.</li>
                    </ul>
                    <div class="help-note help-warning">Не принимайте заказ, если фактические обязанности или состояние пациента не соответствуют описанию. Сначала уточните детали в чате или через CRM.</div>
                </section>

                <section id="caregiver-shift" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Работа на смене и журнал ухода</h2>
                    <ol>
                        <li>Откройте назначенную смену и изучите план ухода.</li>
                        <li>Зафиксируйте время прибытия.</li>
                        <li>Вносите значимые записи: лекарства, питание, гигиена, мобильность, показатели и наблюдения.</li>
                        <li>Отмечайте тревожные события как предупреждения и при необходимости создавайте инцидент.</li>
                        <li>Перед завершением заполните итог, время ухода и отправьте журнал.</li>
                        <li>Запросите подтверждение завершения смены клиентом.</li>
                    </ol>
                    <div class="help-note">Записи должны быть фактическими и сделанными своевременно. Не изменяйте медицинские назначения и не указывайте действия, которые не выполнялись.</div>
                </section>

                <section id="caregiver-money" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Расходы, акты и выплаты</h2>
                    <ul>
                        <li>Дополнительные покупки оформляйте как расход по заказу с названием, количеством, ценой и пояснением.</li>
                        <li>Не совершайте крупную покупку без предварительного согласования клиента.</li>
                        <li>После подтверждения смены формируется акт и сумма выплаты за конкретную смену.</li>
                        <li>Комиссия площадки рассчитывается по условиям подписанного договора.</li>
                        <li>Статусы выплаты доступны в истории: ожидает, обрабатывается, выплачена или отменена.</li>
                    </ul>
                    @auth
                        @if(auth()->user()->isCaregiver())<a href="{{ route('caregiver.payouts.index') }}" class="btn btn-outline-dark rounded-pill">История выплат</a>@endif
                    @endauth
                </section>

                <section id="caregiver-safety" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Безопасность, спор и отмена</h2>
                    <p><strong>Экстренная ситуация:</strong> сначала вызовите 112 или профильную службу, окажите допустимую помощь, уведомите контактное лицо и только затем подробно зафиксируйте инцидент.</p>
                    <p><strong>Спор:</strong> используйте при несогласии с подтверждением смены, объёмом работы, временем, расходами или суммой. Прикладывайте конкретные факты и сообщения.</p>
                    <p><strong>Отмена:</strong> отменяйте заказ только по объективной причине и как можно раньше. Укажите подробности, чтобы CRM мог быстро организовать замену.</p>
                    <p><strong>Отчёт на пользователя:</strong> используйте при нарушении правил, угрозах, мошенничестве или недопустимом поведении.</p>
                </section>
            @endif

            @if($audience === 'crm')
                <section id="crm-permissions" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Роли CRM и разрешения</h2>
                    <div class="table-responsive">
                        <table class="table help-permission-table">
                            <thead><tr><th>Роль</th><th>Основная зона ответственности</th></tr></thead>
                            <tbody>
                                <tr><td>Оператор</td><td>Входящие обращения, карточки людей, заявки и контакты.</td></tr>
                                <tr><td>Координатор</td><td>Заявки, подбор, графики, документы и допуски сиделок.</td></tr>
                                <tr><td>Супервайзер</td><td>Работа координаторов, споры, качество и инциденты.</td></tr>
                                <tr><td>Бухгалтер</td><td>Выплаты, комиссии и финансовые операции.</td></tr>
                                <tr><td>Юрист</td><td>Договоры, протоколы подписи, споры и претензии.</td></tr>
                                <tr><td>Руководитель</td><td>Все CRM-разделы, аналитика и управление сотрудниками.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="help-note">Администратор может дополнить стандартную роль индивидуальными разрешениями. Меню показывает только разрешённые разделы.</div>
                </section>

                <section id="crm-requests" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Люди, обращения, заявки и подбор</h2>
                    <h3 class="h5">Карточка человека</h3>
                    <p>Хранит контакты, роль, историю взаимодействий, связанные заявки, заказы, документы, платежи и задачи. Перед созданием новой карточки проверяйте, нет ли человека по телефону или email.</p>
                    <h3 class="h5 mt-3">CRM-заявка</h3>
                    <ol>
                        <li>Запишите источник, контакты, пациента, адрес, потребность, график и бюджет.</li>
                        <li>Назначьте ответственного и дату следующего контакта.</li>
                        <li>Переводите заявку по этапам kanban только после фактического результата.</li>
                        <li>Каждый звонок, сообщение, встречу и решение фиксируйте во взаимодействиях.</li>
                        <li>Подберите сиделку, объясните причину выбора и согласуйте с клиентом.</li>
                        <li>После подтверждения преобразуйте заявку в заказ.</li>
                    </ol>
                    <h3 class="h5 mt-3">Длинные заказы</h3>
                    <p>Контролируйте незакрытые интервалы, пересечения и назначение нескольких сиделок. При замене используйте функцию замены по смене — она сохраняет сам заказ и историю.</p>
                </section>

                <section id="crm-tasks" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Персональные задачи и колокольчик</h2>
                    <ul>
                        <li>Задача назначается конкретному сотруднику и содержит срок, приоритет, источник и ссылку на рабочий объект.</li>
                        <li>Красным выделяются просроченные задачи, приоритет «Срочно» обрабатывается первым.</li>
                        <li>Автоматические задачи создаются по следующему контакту, документам, спорам, инцидентам и другим контрольным событиям.</li>
                        <li>Нажатие на задачу открывает нужный экран. После фактического выполнения нажмите «Выполнено».</li>
                        <li>Не закрывайте задачу только ради очистки счётчика: это нарушает контроль сроков.</li>
                    </ul>
                </section>

                <section id="crm-documents" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Проверка документов и допуск сиделки</h2>
                    <ol>
                        <li>Откройте задачу в колокольчике или раздел «Допуски».</li>
                        <li>Нажмите «Открыть скан» — PDF или изображение откроется в модальном окне.</li>
                        <li>Сверьте ФИО, номер, даты, читаемость и соответствие типа документа.</li>
                        <li>Укажите срок действия, обязательность и влияние на допуск.</li>
                        <li>Выберите «Проверен» либо «Отклонён». При отклонении обязательно напишите понятную причину.</li>
                        <li>После сохранения задача закроется, а сиделка получит уведомление.</li>
                    </ol>
                    <div class="help-note help-warning">Нельзя подтверждать пустой, нечитаемый, чужой, просроченный или явно изменённый документ. Паспортные и медицинские сведения не копируйте в открытые комментарии.</div>
                    @auth
                        @if(auth()->user()->hasStaffPermission('crm.documents.manage'))<a href="{{ route('crm.caregiver-documents.index') }}" class="btn btn-outline-dark rounded-pill">Открыть допуски</a>@endif
                    @endauth
                </section>

                <section id="crm-control" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Споры, качество и инциденты</h2>
                    <h3 class="h5">Спор по смене</h3>
                    <p>Изучите акт, журнал, сообщения, заявленное и фактическое время, расходы и позиции обеих сторон. Зафиксируйте решение, одобренную сумму и обоснование. Решение должно быть проверяемым и не противоречить договору.</p>
                    <h3 class="h5 mt-3">Инцидент безопасности</h3>
                    <p>Сначала оцените срочность и наличие вызова экстренных служб. Назначьте ответственного, обновляйте статус, фиксируйте связь с клиентом и итоговые меры. Критические события нельзя оставлять без ответа.</p>
                    <h3 class="h5 mt-3">Контроль качества</h3>
                    <p>Анализируйте жалобы, отчёты пользователей, частые отмены, отрицательные отзывы и повторяющиеся нарушения. Результат проверки должен отражаться в карточке пользователя и связанных задачах.</p>
                </section>

                <section id="crm-finance" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Договоры, финансы и аналитика</h2>
                    <h3 class="h5">Договоры</h3>
                    <p>Проверяйте тип, стороны, заказ, версию, хэш и статусы подписей. При отправке кода убедитесь, что выбран правильный участник и контакт. Протокол подписи используется для аудита.</p>
                    <h3 class="h5 mt-3">Финансы</h3>
                    <p>Выплата формируется по подтверждённой смене. Перед отметкой «Выплачено» проверьте получателя, реквизиты, сумму, комиссию и отсутствие открытого спора. Ручное изменение статуса без фактической операции запрещено.</p>
                    <h3 class="h5 mt-3">Аналитика</h3>
                    <p>Используйте показатели заявок, конверсии, занятости, выплат, споров, инцидентов и просроченных задач для управленческих решений. Резкие отклонения проверяйте по первичным карточкам.</p>
                </section>
            @endif

            @if($audience === 'admin')
                <section id="admin-start" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Панель администратора</h2>
                    <p>Администратор имеет полный доступ к платформе и CRM. На главной странице отображаются основные показатели: пользователи, сиделки, CRM-сотрудники, проверенные анкеты, услуги и новости.</p>
                    <p>Используйте отдельные разделы для каждой группы настроек. Перед изменением критичных параметров зафиксируйте прежнее значение и убедитесь, что есть резервная копия.</p>
                    @auth
                        @if(auth()->user()->isAdmin())<a href="{{ route('admin.dashboard') }}" class="btn btn-dark rounded-pill">Открыть админку</a>@endif
                    @endauth
                </section>

                <section id="admin-settings" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Настройки сайта, банка и юридических данных</h2>
                    <h3 class="h5">SEO</h3>
                    <p>Настраиваются название сайта, заголовки, описания, ключевые слова, robots, Open Graph и отдельные метаданные главной страницы, каталога и новостей. После изменения проверьте исходный код страницы и предпросмотр ссылки.</p>
                    <h3 class="h5 mt-3">Банк и эквайринг</h3>
                    <p>Укажите режим работы, адрес шлюза, логин, пароль, префикс назначения и таймаут. Проверяйте тестовый платёж перед включением боевого режима. Секретные параметры не передавайте в сообщениях и скриншотах.</p>
                    <h3 class="h5 mt-3">Юридические реквизиты</h3>
                    <p>Реквизиты площадки автоматически используются в договорах и документах. После изменения создайте тестовый договор и проверьте название, ИНН, адрес, банковские данные и подписанта.</p>
                    <h3 class="h5 mt-3">Настройки CRM</h3>
                    <p>Здесь задаются параметры внутренних процессов и значения, используемые CRM. Изменения должны быть согласованы с ответственными подразделениями.</p>
                </section>

                <section id="admin-staff" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">CRM-сотрудники, роли и права</h2>
                    <ol>
                        <li>Откройте раздел CRM-сотрудников.</li>
                        <li>Создайте аккаунт с рабочим email и назначьте базовую роль.</li>
                        <li>При необходимости добавьте индивидуальные разрешения.</li>
                        <li>Проверьте, какие пункты меню доступны сотруднику.</li>
                        <li>При увольнении отключите `staff_active`, а не передавайте аккаунт другому человеку.</li>
                        <li>Переназначьте открытые задачи и ответственные заявки.</li>
                    </ol>
                    <div class="help-note">Принцип минимальных прав: сотрудник должен иметь только те разрешения, которые нужны для его работы. Финансы, договоры, персональные документы и управление сотрудниками выдаются отдельно.</div>
                </section>

                <section id="admin-content" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Услуги, новости и пользователи</h2>
                    <h3 class="h5">Каталог услуг</h3>
                    <p>Создавайте понятные названия и описания. Перед удалением или переименованием проверьте связанные анкеты, заказы и посадочные страницы.</p>
                    <h3 class="h5 mt-3">Новости</h3>
                    <p>Проверяйте заголовок, текст, дату публикации, изображение и отображение на мобильном устройстве. Не публикуйте персональные и медицинские данные без законного основания.</p>
                    <h3 class="h5 mt-3">Пользователи и права</h3>
                    <p>Можно изменять роль, статус проверки и основные данные. Смена роли действующего аккаунта может изменить доступ ко всем кабинетам, поэтому сначала проверьте связанные заказы, выплаты, документы и задачи.</p>
                </section>

                <section id="admin-operations" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                    <h2 class="h3 mb-3">Техническое обслуживание системы</h2>
                    <h3 class="h5">Планировщик</h3>
                    <p>На сервере каждую минуту должен выполняться Laravel Scheduler:</p>
                    <pre class="bg-dark text-light p-3 rounded-4"><code>* * * * * cd /FULL/PATH/TO/PROJECT &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code></pre>
                    <p>Он запускает контроль просроченных смен, сроков документов и назначение задач по непроверенным документам.</p>
                    <h3 class="h5 mt-3">Обновление приложения</h3>
                    <pre class="bg-dark text-light p-3 rounded-4"><code>git checkout main
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache</code></pre>
                    <h3 class="h5 mt-3">Резервные копии</h3>
                    <p>Перед обновлением сохраняйте базу данных, `.env` и каталог пользовательских файлов. Резервную копию необходимо периодически проверять восстановлением на отдельном окружении.</p>
                    <h3 class="h5 mt-3">Журналы и диагностика</h3>
                    <pre class="bg-dark text-light p-3 rounded-4"><code>tail -n 200 storage/logs/laravel.log
php artisan route:list
php artisan migrate:status
php artisan schedule:list
php artisan test</code></pre>
                    <div class="help-note help-warning">Не запускайте `migrate:fresh`, `db:wipe` или удаление каталога `storage` на рабочем сервере: эти команды могут уничтожить данные.</div>
                </section>
            @endif

            <section id="faq" class="card-soft p-4 mb-4 help-topic" data-help-topic>
                <h2 class="h3 mb-3">Частые вопросы и ошибки</h2>
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item border-0 border-bottom">
                        <h3 class="accordion-header"><button class="accordion-button bg-transparent fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faqNoMenu">Почему нет нужного пункта меню?</button></h3>
                        <div id="faqNoMenu" class="accordion-collapse collapse show"><div class="accordion-body px-0">Проверьте роль, подтверждение email и разрешения CRM. После изменения прав выйдите и войдите снова, а администратору следует очистить кэш конфигурации и маршрутов.</div></div>
                    </div>
                    <div class="accordion-item border-0 border-bottom">
                        <h3 class="accordion-header"><button class="accordion-button collapsed bg-transparent fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faqOldPage">Почему после обновления отображается старая страница?</button></h3>
                        <div id="faqOldPage" class="accordion-collapse collapse"><div class="accordion-body px-0">Нажмите <span class="help-key">Ctrl</span> + <span class="help-key">F5</span>. На сервере выполните `php artisan optimize:clear` и `php artisan view:clear`, затем заново сформируйте кэш.</div></div>
                    </div>
                    <div class="accordion-item border-0 border-bottom">
                        <h3 class="accordion-header"><button class="accordion-button collapsed bg-transparent fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faqDocument">Почему документ блокирует смену?</button></h3>
                        <div id="faqDocument" class="accordion-collapse collapse"><div class="accordion-body px-0">Документ отмечен обязательным и блокирующим, но не подтверждён, отклонён либо просрочен. Загрузите корректный файл и дождитесь проверки сотрудником CRM.</div></div>
                    </div>
                    <div class="accordion-item border-0 border-bottom">
                        <h3 class="accordion-header"><button class="accordion-button collapsed bg-transparent fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faqPayment">Почему выплата или возврат ещё не завершены?</button></h3>
                        <div id="faqPayment" class="accordion-collapse collapse"><div class="accordion-body px-0">Проверьте подтверждение смены, подпись договора, открытые споры, статус платежа и реквизиты. Для банковской операции также возможна задержка внешнего провайдера.</div></div>
                    </div>
                    <div class="accordion-item border-0">
                        <h3 class="accordion-header"><button class="accordion-button collapsed bg-transparent fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faqError">Что отправить администратору при ошибке?</button></h3>
                        <div id="faqError" class="accordion-collapse collapse"><div class="accordion-body px-0">Укажите страницу, время, свою роль, последовательность действий и полный текст ошибки. Скриншот полезен, но предварительно закройте паспортные данные, банковские реквизиты, пароли и одноразовые коды.</div></div>
                    </div>
                </div>
            </section>

            <section class="card-soft p-4 help-topic" data-help-topic>
                <h2 class="h3 mb-3">Куда вернуться после инструкции</h2>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('home') }}" class="btn btn-outline-dark rounded-pill">На главную</a>
                    @guest
                        <a href="{{ route('login') }}" class="btn btn-dark rounded-pill">Войти</a>
                    @else
                        @if(auth()->user()->isClient())
                            <a href="{{ route('client.dashboard') }}" class="btn btn-dark rounded-pill">Кабинет клиента</a>
                        @elseif(auth()->user()->isCaregiver())
                            <a href="{{ route('caregiver.dashboard') }}" class="btn btn-dark rounded-pill">Кабинет сиделки</a>
                        @elseif(auth()->user()->isCrm())
                            <a href="{{ route('crm.dashboard') }}" class="btn btn-dark rounded-pill">CRM</a>
                        @elseif(auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-dark rounded-pill">Админка</a>
                        @endif
                    @endguest
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('helpSearch');
    const topics = Array.from(document.querySelectorAll('[data-help-topic]'));
    const empty = document.getElementById('helpNoResults');
    const expand = document.getElementById('helpExpandAll');

    if (search) {
        search.addEventListener('input', function () {
            const query = search.value.trim().toLocaleLowerCase('ru');
            let visible = 0;

            topics.forEach(function (topic) {
                const matches = query === '' || topic.textContent.toLocaleLowerCase('ru').includes(query);
                topic.classList.toggle('d-none', !matches);
                if (matches) visible++;
            });

            if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
        });
    }

    if (expand) {
        let expanded = false;
        expand.addEventListener('click', function () {
            expanded = !expanded;
            document.querySelectorAll('.accordion-collapse').forEach(function (element) {
                const instance = bootstrap.Collapse.getOrCreateInstance(element, { toggle: false });
                expanded ? instance.show() : instance.hide();
            });
            expand.textContent = expanded ? 'Свернуть всё' : 'Развернуть всё';
        });
    }
});
</script>
@endpush
