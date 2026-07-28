@extends('admin.layout')

@php($pageTitle = 'SEO')

@section('admin-page')
<div class="card-soft p-4">
    <h2 class="h4 mb-3">SEO-настройки сайта</h2>
    <form action="{{ route('admin.seo.update') }}" method="POST" class="row g-3">
        @csrf
        <div class="col-md-6">
            <label class="form-label">Название сайта</label>
            <input type="text" name="seo_site_name" class="form-control" value="{{ old('seo_site_name', $seoSettings['site_name']) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Title по умолчанию</label>
            <input type="text" name="seo_default_title" class="form-control" value="{{ old('seo_default_title', $seoSettings['default_title']) }}">
        </div>
        <div class="col-12">
            <label class="form-label">Description по умолчанию</label>
            <textarea name="seo_default_description" class="form-control" rows="3">{{ old('seo_default_description', $seoSettings['default_description']) }}</textarea>
        </div>
        <div class="col-12">
            <label class="form-label">Keywords</label>
            <textarea name="seo_default_keywords" class="form-control" rows="2">{{ old('seo_default_keywords', $seoSettings['default_keywords']) }}</textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Robots</label>
            <input type="text" name="seo_robots" class="form-control" value="{{ old('seo_robots', $seoSettings['robots']) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">OG image URL</label>
            <input type="text" name="seo_og_image" class="form-control" value="{{ old('seo_og_image', $seoSettings['og_image']) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Главная: title</label>
            <input type="text" name="seo_home_title" class="form-control" value="{{ old('seo_home_title', $seoSettings['home_title']) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Главная: description</label>
            <textarea name="seo_home_description" class="form-control" rows="2">{{ old('seo_home_description', $seoSettings['home_description']) }}</textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Каталог сиделок: title</label>
            <input type="text" name="seo_caregivers_title" class="form-control" value="{{ old('seo_caregivers_title', $seoSettings['caregivers_title']) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Каталог сиделок: description</label>
            <textarea name="seo_caregivers_description" class="form-control" rows="2">{{ old('seo_caregivers_description', $seoSettings['caregivers_description']) }}</textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Новости: title</label>
            <input type="text" name="seo_news_title" class="form-control" value="{{ old('seo_news_title', $seoSettings['news_title']) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Новости: description</label>
            <textarea name="seo_news_description" class="form-control" rows="2">{{ old('seo_news_description', $seoSettings['news_description']) }}</textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-dark rounded-pill px-4">Сохранить SEO</button>
        </div>
    </form>
</div>
@endsection
