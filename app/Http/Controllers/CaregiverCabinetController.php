<?php

namespace App\Http\Controllers;

use App\Models\AvailabilitySlot;
use App\Models\Order;
use App\Models\OrderScheduleSlot;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CaregiverCabinetController extends Controller
{
    public function ordersHistory(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->isCaregiver(), 404);

        $orders = $this->caregiverOrders($user);
        $group = (string) $request->query('group', 'current');

        return view('caregiver.orders-history', [
            'user' => $user,
            'group' => in_array($group, ['current', 'completed', 'cancelled'], true) ? $group : 'current',
            'currentOrders' => $orders->filter(fn (Order $order) => in_array($order->status, ['matched', 'in_chat', 'in_progress'], true))->values(),
            'completedOrders' => $orders->where('status', 'completed')->values(),
            'cancelledOrders' => $orders->where('status', 'cancelled')->values(),
        ]);
    }

    public function openOrders(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->isCaregiver() && $user->caregiverProfile, 404);

        return view('caregiver.open-orders', [
            'user' => $user,
            'orders' => $this->openOrdersForUser($user),
        ]);
    }

    public function clientReviews(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->isCaregiver(), 404);

        $orders = $this->caregiverOrders($user);
        $reviews = Review::query()
            ->where('author_id', $user->id)
            ->where('subject_role', 'client')
            ->with(['subject', 'order'])
            ->latest('published_at')
            ->get();

        $reviewedOrderIds = $reviews->pluck('order_id')->filter()->all();
        $pendingReviewOrders = $orders
            ->where('status', 'completed')
            ->filter(fn (Order $order) => ! in_array($order->id, $reviewedOrderIds, true))
            ->values();

        return view('caregiver.client-reviews', [
            'user' => $user,
            'reviews' => $reviews,
            'pendingReviewOrders' => $pendingReviewOrders,
        ]);
    }

    private function caregiverOrders(User $user): Collection
    {
        $directOrderIds = $user->caregiverOrders()->pluck('orders.id');
        $assignmentOrderIds = $user->caregiverAssignments()->pluck('order_id');
        $orderIds = $directOrderIds->merge($assignmentOrderIds)->unique()->values();

        if ($orderIds->isEmpty()) {
            return collect();
        }

        return Order::query()
            ->with(['client', 'scheduleSlots'])
            ->whereIn('id', $orderIds)
            ->latest()
            ->get();
    }

    private function openOrdersForUser(User $user): Collection
    {
        $profile = $user->caregiverProfile;
        $serviceIds = $profile->availableServices()->pluck('id');

        return Order::query()
            ->with(['client', 'services', 'scheduleSlots', 'caregiverAssignments'])
            ->where('status', 'published')
            ->where('city', $user->city)
            ->where('hourly_budget', '>=', $profile->hourly_rate_from)
            ->whereHas('services', fn ($query) => $query->whereIn('services.id', $serviceIds))
            ->latest()
            ->get()
            ->filter(fn (Order $order) => $this->matchesAvailability($profile, $order))
            ->map(function (Order $order) use ($serviceIds, $user) {
                $order->match_count = $order->services->pluck('id')->intersect($serviceIds)->count();
                $order->my_application = $order->caregiverAssignments
                    ->where('caregiver_id', $user->id)
                    ->whereIn('status', ['applied', 'reserved', 'invited', 'accepted', 'completed', 'declined'])
                    ->sortByDesc('id')
                    ->first();

                return $order;
            })
            ->values();
    }

    private function matchesAvailability($profile, Order $order): bool
    {
        if ($profile->availabilitySlots->isEmpty()) {
            return true;
        }

        return $order->scheduleSlots->every(fn (OrderScheduleSlot $slot) => $this->slotMatchesAvailability($profile, $slot));
    }

    private function slotMatchesAvailability($profile, OrderScheduleSlot $requiredSlot): bool
    {
        if ($profile->availabilitySlots->isEmpty()) {
            return true;
        }

        return $profile->availabilitySlots->contains(function (AvailabilitySlot $slot) use ($requiredSlot) {
            $dateMatches = $slot->specific_date
                ? $slot->specific_date->format('Y-m-d') === $requiredSlot->scheduled_date->format('Y-m-d')
                : (int) $slot->weekday === (int) $requiredSlot->scheduled_date->dayOfWeek;

            return $dateMatches
                && substr($slot->starts_at, 0, 5) <= substr($requiredSlot->starts_at, 0, 5)
                && substr($slot->ends_at, 0, 5) >= substr($requiredSlot->ends_at, 0, 5);
        });
    }
}
