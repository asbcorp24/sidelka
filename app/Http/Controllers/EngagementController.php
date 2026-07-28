<?php

namespace App\Http\Controllers;

use App\Models\CaregiverFavorite;
use App\Models\CaregiverProfile;
use App\Models\Order;
use App\Models\UserReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EngagementController extends Controller
{
    public function favorite(Request $request, CaregiverProfile $caregiverProfile): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isClient(), 404);

        CaregiverFavorite::query()->updateOrCreate(
            [
                'client_id' => $user->id,
                'caregiver_id' => $caregiverProfile->user_id,
            ],
            [
                'note' => $request->input('note'),
            ]
        );

        return back()->with('status', 'Сиделка добавлена в избранное.');
    }

    public function unfavorite(Request $request, CaregiverProfile $caregiverProfile): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isClient(), 404);

        CaregiverFavorite::query()
            ->where('client_id', $user->id)
            ->where('caregiver_id', $caregiverProfile->user_id)
            ->delete();

        return back()->with('status', 'Сиделка удалена из избранного.');
    }

    public function report(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['client', 'caregiver'], true), 404);

        $data = $request->validate([
            'reported_user_id' => ['required', 'integer', 'exists:users,id'],
            'kind' => ['required', 'in:complaint,blacklist'],
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:5000'],
        ]);

        UserReport::create([
            'order_id' => $order->id,
            'reporter_id' => $user->id,
            'reported_user_id' => $data['reported_user_id'],
            'kind' => $data['kind'],
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'adds_to_blacklist' => $data['kind'] === 'blacklist',
            'status' => 'new',
        ]);

        return back()->with('status', 'Жалоба отправлена в CRM.');
    }
}
