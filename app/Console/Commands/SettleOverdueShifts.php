<?php

namespace App\Console\Commands;

use App\Models\OrderCaregiverAssignment;
use App\Services\OrderFinanceService;
use Illuminate\Console\Command;
use Throwable;

class SettleOverdueShifts extends Command
{
    protected $signature = 'shifts:settle-overdue';

    protected $description = 'Подтвердить отработанные смены без возражений клиента и сформировать выплаты';

    public function handle(OrderFinanceService $financeService): int
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
            ->with(['order', 'scheduleSlot', 'caregiver'])
            ->orderBy('id')
            ->chunkById(100, function ($assignments) use ($financeService, &$processed, &$failed) {
                foreach ($assignments as $assignment) {
                    try {
                        $financeService->releaseAssignmentPayout($assignment);
                        $processed++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed++;
                        $this->error(
                            'Смена #' . $assignment->id . ': ' . $exception->getMessage()
                        );
                    }
                }
            });

        $this->info("Сформировано выплат: {$processed}; ошибок: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
