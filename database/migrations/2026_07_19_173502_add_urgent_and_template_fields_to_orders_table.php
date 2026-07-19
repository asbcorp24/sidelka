<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_urgent')->default(false)->after('payment_status');
            $table->boolean('needs_today')->default(false)->after('is_urgent');
            $table->string('recurrence_label')->nullable()->after('schedule_type');
            $table->foreignId('created_by_family_member_id')->nullable()->after('client_id')->constrained('client_family_members')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_family_member_id');
            $table->dropColumn(['is_urgent', 'needs_today', 'recurrence_label']);
        });
    }
};
