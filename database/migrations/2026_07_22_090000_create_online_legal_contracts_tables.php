<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legal_contracts')) {
            Schema::create('legal_contracts', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->string('type', 40)->index();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('number', 80)->unique();
                $table->unsignedInteger('version')->default(1);
                $table->string('title');
                $table->string('status', 32)->default('awaiting_signatures')->index();
                $table->longText('body_html');
                $table->char('document_hash', 64);
                $table->json('meta')->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->dateTime('signed_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'type', 'status'], 'lc_order_type_status_idx');
            });
        }

        if (! Schema::hasTable('legal_contract_parties')) {
            Schema::create('legal_contract_parties', function (Blueprint $table) {
                $table->id();
                $table->char('public_token', 64)->unique();
                $table->foreignId('legal_contract_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('role', 32)->index();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->boolean('is_required')->default(true);
                $table->string('status', 24)->default('pending')->index();
                $table->dateTime('signed_at')->nullable();
                $table->timestamps();

                $table->index(['legal_contract_id', 'role'], 'lcp_contract_role_idx');
                $table->index(['user_id', 'status'], 'lcp_user_status_idx');
            });
        }

        if (! Schema::hasTable('legal_signature_challenges')) {
            Schema::create('legal_signature_challenges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('legal_contract_party_id')->constrained()->cascadeOnDelete();
                $table->string('code_hash');
                $table->string('channel', 16);
                $table->string('destination');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedTinyInteger('max_attempts')->default(5);
                $table->string('provider_message_id')->nullable();
                $table->ipAddress('request_ip')->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->dateTime('expires_at');
                $table->dateTime('consumed_at')->nullable();
                $table->timestamps();

                $table->index(['legal_contract_party_id', 'expires_at'], 'lsc_party_exp_idx');
            });
        }

        // MySQL мог создать таблицу до падения на слишком длинном имени индекса.
        // В таком случае повторный запуск добавляет только отсутствующий короткий индекс.
        $this->ensureIndex(
            'legal_signature_challenges',
            'lsc_party_exp_idx',
            ['legal_contract_party_id', 'expires_at']
        );

        if (! Schema::hasTable('legal_contract_signatures')) {
            Schema::create('legal_contract_signatures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('legal_contract_id')->constrained()->cascadeOnDelete();
                $table->foreignId('legal_contract_party_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('method', 32)->default('simple_code');
                $table->string('channel', 16);
                $table->string('destination')->nullable();
                $table->char('document_hash', 64);
                $table->dateTime('signed_at');
                $table->ipAddress('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->json('evidence')->nullable();
                $table->timestamps();

                $table->index(['legal_contract_id', 'signed_at'], 'lcs_contract_signed_idx');
            });
        }

        if (! Schema::hasTable('legal_contract_events')) {
            Schema::create('legal_contract_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('legal_contract_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event', 48);
                $table->json('data')->nullable();
                $table->ipAddress('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index(['legal_contract_id', 'created_at'], 'lce_contract_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_contract_events');
        Schema::dropIfExists('legal_contract_signatures');
        Schema::dropIfExists('legal_signature_challenges');
        Schema::dropIfExists('legal_contract_parties');
        Schema::dropIfExists('legal_contracts');
    }

    private function ensureIndex(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $exists = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?',
            [$table, $index]
        );

        if ((int) ($exists->aggregate ?? 0) === 0) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $index) {
                $blueprint->index($columns, $index);
            });
        }
    }
};
