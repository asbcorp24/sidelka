<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('diagnosis')->nullable();
            $table->text('limitations')->nullable();
            $table->text('daily_routine')->nullable();
            $table->text('medications')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->text('care_features')->nullable();
            $table->timestamps();
        });

        Schema::create('caregiver_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caregiver_id')->constrained('users')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'caregiver_id']);
        });

        Schema::create('user_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 32)->default('complaint');
            $table->string('reason', 255);
            $table->text('details')->nullable();
            $table->string('status', 32)->default('new');
            $table->boolean('adds_to_blacklist')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shift_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_caregiver_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caregiver_id')->constrained('users')->cascadeOnDelete();
            $table->text('summary')->nullable();
            $table->json('completed_tasks')->nullable();
            $table->json('purchased_items')->nullable();
            $table->text('health_changes')->nullable();
            $table->json('photo_paths')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique('order_caregiver_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_reports');
        Schema::dropIfExists('user_reports');
        Schema::dropIfExists('caregiver_favorites');
        Schema::dropIfExists('patient_profiles');
    }
};
