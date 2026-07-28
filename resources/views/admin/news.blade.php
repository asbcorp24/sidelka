@extends('admin.layout')

@php($pageTitle = 'Новости')

@section('admin-page')
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-soft p-4">
            <h2 class="h4 mb-3">Добавить новость</h2>
            <form action="{{ route('admin.news.store') }}" method="POST" class="row g-3">
                @csrf
                <div class="col-12"><input type="text" name="title" class="form-control" placeholder="Заголовок"></div>
                <div class="col-12"><textarea name="excerpt" class="form-control" rows="2" placeholder="Краткое описание"></textarea></div>
                <div class="col-12"><textarea name="body" class="form-control" rows="6" placeholder="Текст новости"></textarea></div>
                <div class="col-12">
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_published" value="1" checked>
                        <span class="form-check-label">Опубликовать сразу</span>
                    </label>
                </div>
                <div class="col-12"><button class="btn btn-dark rounded-pill px-4">Сохранить новость</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-soft p-4">
            <h2 class="h4 mb-3">Список новостей</h2>
            @foreach($posts as $post)
                <div class="border rounded-4 p-3 mb-3">
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <strong>{{ $post->title }}</strong>
                        <span class="badge {{ $post->is_published ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                            {{ $post->is_published ? 'Опубликовано' : 'Черновик' }}
                        </span>
                    </div>
                    @if($post->excerpt)
                        <p class="mb-2 mt-2">{{ $post->excerpt }}</p>
                    @endif
                    <div class="small text-secondary">{{ $post->published_at ? $post->published_at->format('d.m.Y H:i') : 'Пока не опубликовано' }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
