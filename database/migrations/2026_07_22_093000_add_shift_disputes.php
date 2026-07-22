<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_caregiver_assignments', function (Blueprint $table) {
            $table->timestamp('disputed_at')->nullable()->after('completion_requested_at');
            $table->text('dispute_reason')->nullable()->after('completion_note');
        });
    }

    public function down(): void
    {
        Schema::table('order_caregiver_assignments', function (Blueprint $table) {
            $table->dropColumn(['disputed_at', 'dispute_reason']);
        });
    }
};
