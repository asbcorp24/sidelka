<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_requests', function (Blueprint $table) {
            $table->unsignedInteger('lead_cost')->nullable()->after('budget_per_hour');
        });
    }

    public function down(): void
    {
        Schema::table('crm_requests', function (Blueprint $table) {
            $table->dropColumn('lead_cost');
        });
    }
};
