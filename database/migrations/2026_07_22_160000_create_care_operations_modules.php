<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_role', 32)->nullable()->after('role')->index();
            $table->json('staff_permissions')->nullable()->after('staff_role');
            $table->boolean('staff_active')->default(true)->after('staff_permissions');
        });

        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->string('category', 48)->default('general')->after('description')->index();
            $table->string('source_type', 80)->nullable()->after('category');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('dedup_key', 191)->nullable()->after('source_id')->unique();
        });

        Schema::table('user_documents', function (Blueprint $table) {
            $table->boolean('is_required')->default(false)->after('verification_status');
            $table->boolean('blocks_assignments')->default(false)->after('is_required');
            $table->timestamp('verified_at')->nullable()->after('blocks_assignments');
            $table->foreignId('verified_by_id')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reminder_30_at')->nullable();
            $table->timestamp('reminder_14_at')->nullable();
            $table->timestamp('reminder_3_at')->nullable();
            $table->timestamp('expired_task_at')->nullable();
        });

        Schema::create('shift_acts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_caregiver_assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caregiver_id')->constrained('users')->cascadeOnDelete();
            $table->string('number', 96)->unique();
            $table->string('status', 32)->default('awaiting_client')->index();
            $table->longText('body_html');
            $table->char('document_hash', 64);
            $table->unsignedBigInteger('gross_amount')->default(0);
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->unsignedBigInteger('payout_amount')->default(0);
            $table->timestamp('caregiver_confirmed_at')->nullable();
            $table->timestamp('client_confirmed_at')->nullable();
            $table->ipAddress('caregiver_ip')->nullable();
            $table->ipAddress('client_ip')->nullable();
            $table->text('caregiver_user_agent')->nullable();
            $table->text('client_user_agent')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['caregiver_id', 'status']);
        });

        Schema::create('shift_disputes', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_caregiver_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_act_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opened_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('open')->index();
            $table->string('reason', 96);
            $table->text('description');
            $table->string('requested_action', 64)->nullable();
            $table->string('decision', 48)->nullable();
            $table->unsignedBigInteger('approved_gross_amount')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['assigned_to_id', 'status']);
        });

        Schema::create('shift_dispute_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });

        Schema::create('care_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 24)->default('active')->index();
            $table->string('patient_name')->nullable();
            $table->text('diagnosis_summary')->nullable();
            $table->text('allergies')->nullable();
            $table->text('medications')->nullable();
            $table->text('nutrition')->nullable();
            $table->text('mobility')->nullable();
            $table->text('hygiene')->nullable();
            $table->text('communication')->nullable();
            $table->text('risks')->nullable();
            $table->text('emergency_instructions')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();
        });

        Schema::create('care_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_plan_id')->constrained()->cascadeOnDelete();
            $table->string('category', 48)->index();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('schedule_text')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shift_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_caregiver_assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('caregiver_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->text('summary')->nullable();
            $table->text('observations')->nullable();
            $table->json('vitals')->nullable();
            $table->json('meals')->nullable();
            $table->json('medications')->nullable();
            $table->json('hygiene')->nullable();
            $table->json('mobility')->nullable();
            $table->text('client_comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        Schema::create('shift_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entry_type', 48)->index();
            $table->string('title');
            $table->text('value')->nullable();
            $table->string('unit', 32)->nullable();
            $table->timestamp('happened_at');
            $table->text('notes')->nullable();
            $table->boolean('is_alert')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_caregiver_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('incident_type', 64)->index();
            $table->string('severity', 16)->index();
            $table->string('status', 24)->default('open')->index();
            $table->timestamp('occurred_at');
            $table->text('description');
            $table->text('actions_taken')->nullable();
            $table->boolean('emergency_called')->default(false);
            $table->string('emergency_service_reference')->nullable();
            $table->timestamp('client_notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();

            $table->index(['assigned_to_id', 'status']);
            $table->index(['order_id', 'severity']);
        });

        Schema::create('safety_incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('safety_incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('status_from', 24)->nullable();
            $table->string('status_to', 24)->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_incident_updates');
        Schema::dropIfExists('safety_incidents');
        Schema::dropIfExists('shift_journal_entries');
        Schema::dropIfExists('shift_journals');
        Schema::dropIfExists('care_plan_items');
        Schema::dropIfExists('care_plans');
        Schema::dropIfExists('shift_dispute_messages');
        Schema::dropIfExists('shift_disputes');
        Schema::dropIfExists('shift_acts');

        Schema::table('user_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_id');
            $table->dropColumn([
                'is_required', 'blocks_assignments', 'verified_at',
                'reminder_30_at', 'reminder_14_at', 'reminder_3_at', 'expired_task_at',
            ]);
        });

        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->dropUnique(['dedup_key']);
            $table->dropColumn(['category', 'source_type', 'source_id', 'dedup_key']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['staff_role', 'staff_permissions', 'staff_active']);
        });
    }
};
