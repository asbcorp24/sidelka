<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caregiver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind')->default('base_order');
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('RUB');
            $table->string('status')->default('pending');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('caregiver_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('RUB');
            $table->string('status')->default('pending');
            $table->string('destination')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('RUB');
            $table->string('status')->default('pending');
            $table->string('reason');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cancelled_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('stage');
            $table->string('reason');
            $table->text('details')->nullable();
            $table->unsignedInteger('refund_amount')->default(0);
            $table->unsignedInteger('payout_amount')->default(0);
            $table->timestamps();
        });

        Schema::create('order_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caregiver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind')->default('purchase');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('line_total');
            $table->string('status')->default('pending_approval');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('billed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_notifications');
        Schema::dropIfExists('order_expenses');
        Schema::dropIfExists('order_cancellations');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('payments');
    }
};
