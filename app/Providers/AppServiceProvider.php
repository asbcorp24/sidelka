<?php

namespace App\Providers;

use App\Services\NotificationCenterService;
use App\Support\PlatformSettings;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(PlatformSettings::class, fn () => new PlatformSettings());
    }

    public function boot()
    {
        $settings = app(PlatformSettings::class);
        $bank = $settings->bankPayload();
        $legal = $settings->legalPayload();

        config([
            'sber.enabled' => $bank['enabled'],
            'sber.base_url' => $bank['base_url'],
            'sber.username' => $bank['username'],
            'sber.password' => $bank['password'],
            'sber.description_prefix' => $bank['description_prefix'],
            'sber.timeout' => $bank['timeout'],
            'legal.company' => $legal,
        ]);

        view()->composer('*', function ($view) {
            $settings = app(PlatformSettings::class);
            $seo = $settings->seoPayload();
            $routeName = request()->route()?->getName();

            $pageTitle = match ($routeName) {
                'home' => $seo['home_title'] ?: $seo['default_title'],
                'caregivers.index' => $seo['caregivers_title'] ?: $seo['default_title'],
                'news.index' => $seo['news_title'] ?: $seo['default_title'],
                default => $seo['default_title'],
            };

            $pageDescription = match ($routeName) {
                'home' => $seo['home_description'] ?: $seo['default_description'],
                'caregivers.index' => $seo['caregivers_description'] ?: $seo['default_description'],
                'news.index' => $seo['news_description'] ?: $seo['default_description'],
                default => $seo['default_description'],
            };

            $view->with('platformSeo', array_merge($seo, [
                'page_title' => $pageTitle,
                'page_description' => $pageDescription,
            ]));
        });

        view()->composer('layouts.app', function ($view) {
            $user = auth()->user();

            $view->with('notificationCenter', $user
                ? app(NotificationCenterService::class)->summary($user)
                : null);
        });
    }
}
