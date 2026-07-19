@extends('layouts.app')

@php($title = 'Новости')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4">
        <div class="text-uppercase small text-secondary">Контент</div>
        <h1 class="section-title mb-0">Новости и полезные материалы</h1>
    </div>

    <div class="row g-4">
        @foreach($posts as $post)
            <div class="col-lg-6">
                <article class="news-card p-4 h-100">
                    <div class="text-secondary small mb-2">{{ optional($post->published_at)->format('d.m.Y') }}</div>
                    <h2 class="h3">{{ $post->title }}</h2>
                    <p class="lead fs-6">{{ $post->excerpt }}</p>
                    <p class="mb-0 text-secondary">{{ $post->body }}</p>
                </article>
            </div>
        @endforeach
    </div>
</div>
@endsection
