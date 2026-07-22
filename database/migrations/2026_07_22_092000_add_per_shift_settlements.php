<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_caregiver_assignments', function (Blueprint $table) {
            $table->timestamp('completion_requested_at')->nullable()->after('confirmed_at');
            $table->timestamp('client_confirmed_at')->nullable()->after('completion_requested_at');
            $table->timestamp('payout_generated_at')->nullable()->after('completed_at');
            $table->text('completion_note')->nullable()->after('notes');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->foreignId('order_caregiver_assignment_id')
                ->nullable()
                ->after('order_id')
                ->constrained('order_caregiver_assignments')
                ->nullOnDelete();

            $table->unique('order_caregiver_assignment_id', 'payouts_assignment_unique');
        });

        Schema::table('agent_commissions', function (Blueprint $table) {
            $table->dropUnique('agent_commissions_payment_id_caregiver_id_unique');
            $table->foreignId('order_caregiver_assignment_id')
                ->nullable()
                ->after('order_id')
                ->constrained('order_caregiver_assignments')
                ->nullOnDelete();

            $table->unique(
                ['payment_id', 'caregiver_id', 'order_caregiver_assignment_id'],
                'agent_commissions_payment_caregiver_assignment_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('agent_commissions', function (Blueprint $table) {
            $table->dropUnique('agent_commissions_payment_caregiver_assignment_unique');
            $table->dropConstrainedForeignId('order_caregiver_assignment_id');
            $table->unique(['payment_id', 'caregiver_id']);
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropUnique('payouts_assignment_unique');
            $table->dropConstrainedForeignId('order_caregiver_assignment_id');
        });

        Schema::table('order_caregiver_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'completion_requested_at',
                'client_confirmed_at',
                'payout_generated_at',
                'completion_note',
            ]);
        });
    }
};
