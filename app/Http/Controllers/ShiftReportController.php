<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShiftReportController extends Controller
{
    public function store(Request $request, Order $order, OrderCaregiverAssignment $assignment): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user
            && $user->isCaregiver()
            && $assignment->order_id === $order->id
            && $assignment->caregiver_id === $user->id,
            404
        );

        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:5000'],
            'health_changes' => ['nullable', 'string', 'max:5000'],
            'completed_tasks' => ['array'],
            'completed_tasks.*' => ['string', 'max:255'],
            'purchased_items_text' => ['nullable', 'string', 'max:5000'],
            'photo_links_text' => ['nullable', 'string', 'max:5000'],
        ]);

        $report = $assignment->report()->updateOrCreate(
            [],
            [
                'order_id' => $order->id,
                'caregiver_id' => $user->id,
                'summary' => $data['summary'] ?? null,
                'health_changes' => $data['health_changes'] ?? null,
                'completed_tasks' => array_values($data['completed_tasks'] ?? []),
                'purchased_items' => $this->explodeLines($data['purchased_items_text'] ?? ''),
                'photo_paths' => $this->explodeLines($data['photo_links_text'] ?? ''),
                'submitted_at' => now(),
            ]
        );

        return back()->with('status', 'Отчет по смене сохранен.');
    }

    private function explodeLines(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
