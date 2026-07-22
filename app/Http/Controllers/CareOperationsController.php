<?php

namespace App\Http\Controllers;

use App\Models\CarePlan;
use App\Models\Order;
use App\Models\OrderCaregiverAssignment;
use App\Models\ShiftJournal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CareOperationsController extends Controller
{
    public function savePlan(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        $allowed = ($user->isClient() && $order->client_id === $user->id)
            || $user->hasStaffPermission('crm.schedules.manage');
        abort_unless($allowed, 403);

        $data = $request->validate([
            'patient_name' => ['nullable', 'string', 'max:255'],
            'diagnosis_summary' => ['nullable', 'string', 'max:5000'],
            'allergies' => ['nullable', 'string', 'max:3000'],
            'medications' => ['nullable', 'string', 'max:5000'],
            'nutrition' => ['nullable', 'string', 'max:5000'],
            'mobility' => ['nullable', 'string', 'max:5000'],
            'hygiene' => ['nullable', 'string', 'max:5000'],
            'communication' => ['nullable', 'string', 'max:5000'],
            'risks' => ['nullable', 'string', 'max:5000'],
            'emergency_instructions' => ['nullable', 'string', 'max:5000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['array'],
            'items.*.category' => ['nullable', Rule::in(['nutrition', 'medication', 'hygiene', 'mobility', 'observation', 'household', 'other'])],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.instructions' => ['nullable', 'string', 'max:3000'],
            'items.*.schedule_text' => ['nullable', 'string', 'max:255'],
            'items.*.is_required' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($order, $user, $data) {
            $plan = CarePlan::updateOrCreate(['order_id' => $order->id], [
                'created_by_id' => $user->id,
                'status' => 'active',
                'patient_name' => $data['patient_name'] ?? $order->patient_name,
                'diagnosis_summary' => $data['diagnosis_summary'] ?? null,
                'allergies' => $data['allergies'] ?? null,
                'medications' => $data['medications'] ?? null,
                'nutrition' => $data['nutrition'] ?? null,
                'mobility' => $data['mobility'] ?? null,
                'hygiene' => $data['hygiene'] ?? null,
                'communication' => $data['communication'] ?? null,
                'risks' => $data['risks'] ?? null,
                'emergency_instructions' => $data['emergency_instructions'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'effective_from' => now(),
            ]);

            $plan->items()->delete();
            $sortOrder = 0;
            foreach (array_values($data['items'] ?? []) as $item) {
                $title = trim((string) ($item['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $plan->items()->create([
                    'category' => $item['category'] ?? 'other',
                    'title' => $title,
                    'instructions' => $item['instructions'] ?? null,
                    'schedule_text' => $item['schedule_text'] ?? null,
                    'is_required' => (bool) ($item['is_required'] ?? false),
                    'sort_order' => $sortOrder++,
                ]);
            }
        });

        return back()->with('status', 'План ухода сохранён и доступен назначенным сиделкам.');
    }

    public function saveJournal(Request $request, Order $order, OrderCaregiverAssignment $assignment): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isCaregiver()
            && $assignment->order_id === $order->id
            && $assignment->caregiver_id === $user->id,
            404
        );
        abort_unless(in_array($assignment->status, ['accepted', 'completed'], true), 422);

        $data = $request->validate([
            'arrived_at' => ['nullable', 'date'],
            'left_at' => ['nullable', 'date', 'after_or_equal:arrived_at'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'observations' => ['nullable', 'string', 'max:5000'],
            'vitals_text' => ['nullable', 'string', 'max:3000'],
            'meals_text' => ['nullable', 'string', 'max:3000'],
            'medications_text' => ['nullable', 'string', 'max:3000'],
            'hygiene_text' => ['nullable', 'string', 'max:3000'],
            'mobility_text' => ['nullable', 'string', 'max:3000'],
        ]);

        $journal = ShiftJournal::firstOrNew(['order_caregiver_assignment_id' => $assignment->id]);
        if ($journal->status === 'accepted') {
            throw ValidationException::withMessages(['journal' => 'Принятый журнал нельзя изменять.']);
        }

        $journal->fill([
            'order_id' => $order->id,
            'care_plan_id' => CarePlan::where('order_id', $order->id)->value('id'),
            'caregiver_id' => $user->id,
            'status' => $journal->status ?: 'draft',
            'arrived_at' => $data['arrived_at'] ?? $journal->arrived_at,
            'left_at' => $data['left_at'] ?? $journal->left_at,
            'summary' => $data['summary'] ?? null,
            'observations' => $data['observations'] ?? null,
            'vitals' => ['text' => $data['vitals_text'] ?? null],
            'meals' => ['text' => $data['meals_text'] ?? null],
            'medications' => ['text' => $data['medications_text'] ?? null],
            'hygiene' => ['text' => $data['hygiene_text'] ?? null],
            'mobility' => ['text' => $data['mobility_text'] ?? null],
        ])->save();

        return back()->with('status', 'Журнал смены сохранён.');
    }

    public function addJournalEntry(Request $request, Order $order, OrderCaregiverAssignment $assignment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isCaregiver() && $assignment->order_id === $order->id && $assignment->caregiver_id === $user->id, 404);

        $data = $request->validate([
            'entry_type' => ['required', Rule::in(['arrival', 'vital', 'meal', 'medication', 'hygiene', 'mobility', 'observation', 'departure'])],
            'title' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:2000'],
            'unit' => ['nullable', 'string', 'max:32'],
            'happened_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'is_alert' => ['nullable', 'boolean'],
        ]);

        $journal = ShiftJournal::firstOrCreate(['order_caregiver_assignment_id' => $assignment->id], [
            'order_id' => $order->id,
            'care_plan_id' => CarePlan::where('order_id', $order->id)->value('id'),
            'caregiver_id' => $user->id,
            'status' => 'draft',
        ]);

        abort_if($journal->status === 'accepted', 422);
        $journal->entries()->create(array_merge($data, [
            'created_by_id' => $user->id,
            'is_alert' => (bool) ($data['is_alert'] ?? false),
        ]));

        return back()->with('status', 'Событие добавлено в журнал смены.');
    }

    public function submitJournal(Request $request, Order $order, OrderCaregiverAssignment $assignment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isCaregiver() && $assignment->order_id === $order->id && $assignment->caregiver_id === $user->id, 404);

        $journal = ShiftJournal::where('order_caregiver_assignment_id', $assignment->id)->firstOrFail();
        if (! $journal->arrived_at || ! $journal->left_at || ! $journal->summary) {
            throw ValidationException::withMessages([
                'journal' => 'Для отправки укажите время прибытия, время завершения и итог смены.',
            ]);
        }

        $journal->update(['status' => 'submitted', 'submitted_at' => now()]);

        return back()->with('status', 'Журнал отправлен заказчику. Теперь можно сформировать акт смены.');
    }
}
