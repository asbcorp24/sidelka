<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('allows_multiple_caregivers')->default(false)->after('needs_today');
        });

        Schema::create('order_caregiver_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_schedule_slot_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('caregiver_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('invited');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['order_schedule_slot_id', 'caregiver_id'], 'slot_caregiver_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_caregiver_assignments');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('allows_multiple_caregivers');
        });
    }
};
