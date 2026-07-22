<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('source', 32)->default('phone');
            $table->string('status', 32)->default('new');
            $table->string('priority', 16)->default('normal');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('caregiver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('caller_name');
            $table->string('caller_phone', 64)->index();
            $table->string('caller_email')->nullable();
            $table->string('patient_name')->nullable();
            $table->unsignedTinyInteger('patient_age')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('address')->nullable();
            $table->text('service_text');
            $table->text('schedule_text')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('budget_per_hour')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('next_contact_at')->nullable()->index();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['responsible_user_id', 'status']);
        });

        Schema::create('crm_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('person_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->string('result', 64)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('happened_at')->useCurrent();
            $table->timestamps();

            $table->index(['crm_request_id', 'happened_at']);
            $table->index(['person_user_id', 'happened_at']);
        });

        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('person_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['assigned_to_id', 'status']);
            $table->index(['person_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_interactions');
        Schema::dropIfExists('crm_requests');
    }
};
