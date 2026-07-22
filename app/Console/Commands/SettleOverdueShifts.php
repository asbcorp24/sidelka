<?php

namespace App\Console\Commands;

use App\Models\OrderCaregiverAssignment;
use App\Models\ShiftAct;
use App\Services\ShiftSettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SettleOverdueShifts extends Command
{
    protected $signature = 'shifts:settle-overdue';

    protected $description = 'Подтвердить акты смен без возражений клиента и сформировать отдельные выплаты';

    public function handle(ShiftSettlementService $settlements): int
    {
        $hours = max(1, (int) config('legal.shift_auto_confirmation_hours', 24));
        $threshold = now()->subHours($hours);
        $processed = 0;
        $failed = 0;

        OrderCaregiverAssignment::query()
            ->where('status', 'accepted')
            ->whereNotNull('completion_requested_at')
            ->where('completion_requested_at', '<=', $threshold)
            ->whereNull('payout_generated_at')
            ->whereHas('order', fn ($query) => $query->whereIn('status', ['in_progress', 'completed']))
            ->whereHas('act', fn ($query) => $query->where('status', ShiftAct::STATUS_AWAITING_CLIENT))
            ->whereDoesntHave('disputes', fn ($query) => $query->whereIn('status', ['open', 'in_review']))
            ->with(['order', 'scheduleSlot', 'caregiver', 'act', 'journal'])
            ->orderBy('id')
            ->chunkById(100, function ($assignments) use ($settlements, &$processed, &$failed) {
                foreach ($assignments as $assignment) {
                    try {
                        DB::transaction(function () use ($assignment, $settlements) {
                            $locked = OrderCaregiverAssignment::query()
                                ->whereKey($assignment->id)
                                ->lockForUpdate()
                                ->firstOrFail();
                            $locked->loadMissing(['act', 'journal']);

                            if (! $locked->act || $locked->act->status !== ShiftAct::STATUS_AWAITING_CLIENT) {
                                return;
                            }

                            if ($locked->disputes()->whereIn('status', ['open', 'in_review'])->exists()) {
                                return;
                            }

                            $locked->act->update([
                                'status' => ShiftAct::STATUS_RESOLVED,
                                'meta' => array_merge($locked->act->meta ?? [], [
                                    'auto_confirmed_at' => now()->toIso8601String(),
                                    'auto_confirmation_hours' => (int) config('legal.shift_auto_confirmation_hours', 24),
                                    'resolution_basis' => 'Заказчик не направил возражения в установленный срок; это системное решение, а не электронная подпись заказчика.',
                                ]),
                            ]);

                            $settlements->settle($locked);
                        });

                        $processed++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed++;
                        $this->error('Смена #' . $assignment->id . ': ' . $exception->getMessage());
                    }
                }
            });

        $this->info("Сформировано выплат: {$processed}; ошибок: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
