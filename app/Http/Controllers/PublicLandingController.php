<?php

namespace App\Http\Controllers;

use App\Models\CaregiverProfile;
use App\Models\NewsPost;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicLandingController extends Controller
{
    public function cityService(string $citySlug, ?string $serviceSlug = null): View
    {
        $cityName = Str::of($citySlug)->replace('-', ' ')->title()->toString();
        $service = $serviceSlug
            ? Service::query()->get()->first(fn (Service $item) => Str::slug($item->name) === $serviceSlug)
            : null;

        $caregivers = CaregiverProfile::with(['user', 'services'])
            ->whereHas('user', fn ($query) => $query->where('role', 'caregiver')->where('city', $cityName))
            ->when($service, fn ($query) => $query->whereHas('services', fn ($serviceQuery) => $serviceQuery->where('services.id', $service->id)))
            ->orderByDesc('documents_verified')
            ->orderBy('hourly_rate_from')
            ->take(12)
            ->get();

        $ordersCount = Order::query()->where('city', $cityName)->count();
        $news = NewsPost::query()->where('is_published', true)->latest('published_at')->take(3)->get();

        return view('landing.city-service', [
            'cityName' => $cityName,
            'service' => $service,
            'caregivers' => $caregivers,
            'ordersCount' => $ordersCount,
            'news' => $news,
        ]);
    }
}
