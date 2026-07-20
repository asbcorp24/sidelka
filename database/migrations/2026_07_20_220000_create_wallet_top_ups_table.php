<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_top_ups', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('sber');
            $table->string('order_number', 36)->unique();
            $table->string('provider_order_id', 64)->nullable()->unique();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('RUB');
            $table->string('status', 32)->default('pending');
            $table->text('payment_url')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->json('provider_payload')->nullable();
            $table->json('provider_status_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_top_ups');
    }
};
