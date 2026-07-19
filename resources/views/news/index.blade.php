@extends('layouts.app')

@php($title = 'Новости')
@php($featuredPost = $posts->first())
@php($otherPosts = $posts->skip(1))

@section('content')
<div class="container py-4 py-lg-5">
    <div class="card-soft p-4 p-lg-5 mb-4">
        <div class="text-uppercase small text-secondary mb-2">Новости сервиса</div>
        <h1 class="section-title mb-3">Обновления, полезные материалы и важные объявления</h1>
        <p class="text-secondary mb-0">Здесь мы публикуем новости о работе сервиса, новые функции, правила безопасности, материалы по уходу за близкими и предложения от партнеров.</p>
    </div>

    @if($featuredPost)
        <section class="mb-4">
            <div class="card-soft p-4 p-lg-5">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-8">
                        <div class="badge text-bg-dark rounded-pill mb-3">Главная новость</div>
                        <div class="text-secondary small mb-2">{{ optional($featuredPost->published_at)->format('d.m.Y') }}</div>
                        <h2 class="h2 mb-3">{{ $featuredPost->title }}</h2>
                        @if($featuredPost->excerpt)
                            <p class="lead fs-6 mb-3">{{ $featuredPost->excerpt }}</p>
                        @endif
                        <p class="text-secondary mb-0">{{ \Illuminate\Support\Str::limit(strip_tags($featuredPost->body), 340) }}</p>
                    </div>
                    <div class="col-lg-4">
                        <div class="news-card h-100 p-4">
                            <h3 class="h5 mb-3">Что публикуется в разделе</h3>
                            <ul class="mb-0">
                                <li>изменения по заказам, оплатам и документам</li>
                                <li>советы по уходу за пожилыми и лежачими пациентами</li>
                                <li>предложения от медицинских клиник и партнеров</li>
                                <li>важные уведомления для семей и сиделок</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <div class="row g-4">
        @forelse($otherPosts as $post)
            <div class="col-lg-6">
                <article class="news-card p-4 h-100">
                    <div class="text-secondary small mb-2">{{ optional($post->published_at)->format('d.m.Y') }}</div>
                    <h2 class="h4 mb-3">{{ $post->title }}</h2>
                    @if($post->excerpt)
                        <p class="mb-3">{{ $post->excerpt }}</p>
                    @endif
                    <p class="text-secondary mb-0">{{ \Illuminate\Support\Str::limit(strip_tags($post->body), 220) }}</p>
                </article>
            </div>
        @empty
            @if(! $featuredPost)
                <div class="col-12">
                    <div class="card-soft p-4">
                        <h2 class="h4 mb-2">Раздел скоро наполнится</h2>
                        <p class="text-secondary mb-0">Пока опубликованных новостей нет. Здесь будут появляться обновления сервиса, полезные статьи и предложения партнеров.</p>
                    </div>
                </div>
            @endif
        @endforelse
    </div>
</div>
@endsection
