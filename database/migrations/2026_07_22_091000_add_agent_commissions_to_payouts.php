<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->unsignedBigInteger('gross_amount')->nullable()->after('caregiver_id');
            $table->decimal('commission_percent', 5, 2)->default(0)->after('gross_amount');
            $table->unsignedBigInteger('commission_amount')->default(0)->after('commission_percent');
            $table->string('external_reference')->nullable()->after('destination');
        });

        Schema::create('agent_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('caregiver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('gross_amount');
            $table->decimal('percent', 5, 2);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('RUB');
            $table->string('status', 24)->default('recognized');
            $table->timestamp('recognized_at')->nullable();
            $table->timestamps();

            $table->unique(['payment_id', 'caregiver_id']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commissions');

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn([
                'gross_amount',
                'commission_percent',
                'commission_amount',
                'external_reference',
            ]);
        });
    }
};
