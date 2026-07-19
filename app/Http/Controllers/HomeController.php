<?php

namespace App\Http\Controllers;

use App\Models\CaregiverProfile;
use App\Models\NewsPost;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    public function index()
    {
        $featuredCaregivers = CaregiverProfile::with(['user', 'services', 'availabilitySlots'])
            ->whereHas('user', fn ($query) => $query->where('role', 'caregiver'))
            ->orderByDesc('documents_verified')
            ->orderBy('hourly_rate_from')
            ->take(6)
            ->get();

        $services = Service::orderBy('category')->orderBy('name')->get();
        $latestNews = NewsPost::where('is_published', true)->latest('published_at')->take(3)->get();
        $activeOrders = Order::with('services')->whereIn('status', ['published', 'matched', 'in_chat'])->latest()->take(3)->get();
        $stats = [
            'caregivers_total' => CaregiverProfile::whereHas('user', fn ($query) => $query->where('role', 'caregiver'))->count(),
            'verified_caregivers' => CaregiverProfile::where('documents_verified', true)->count(),
            'active_orders' => Order::whereIn('status', ['published', 'matched', 'in_chat', 'in_progress'])->count(),
            'medical_services' => Service::where('requires_medical_training', true)->count(),
        ];

        return view('home', compact('featuredCaregivers', 'services', 'latestNews', 'activeOrders', 'stats'));
    }

    public function caregivers()
    {
        $caregivers = CaregiverProfile::with(['user', 'services', 'availabilitySlots'])
            ->whereHas('user', fn ($query) => $query->where('role', 'caregiver'))
            ->orderBy('hourly_rate_from')
            ->get();

        return view('caregivers.index', compact('caregivers'));
    }

    public function showCaregiver(CaregiverProfile $caregiverProfile)
    {
        $caregiverProfile->load(['user.receivedReviews.author', 'services', 'availabilitySlots']);

        return view('caregivers.show', [
            'profile' => $caregiverProfile,
            'reviews' => $caregiverProfile->user->receivedReviews->where('subject_role', 'caregiver')->sortByDesc('published_at'),
        ]);
    }

    public function demoCaregiver(): RedirectResponse
    {
        $user = User::where('role', 'caregiver')->orderBy('id')->firstOrFail();

        return redirect()->route('dashboard.caregiver', $user);
    }

    public function demoClient(): RedirectResponse
    {
        $user = User::where('role', 'client')->orderBy('id')->firstOrFail();

        return redirect()->route('dashboard.client', $user);
    }
}
