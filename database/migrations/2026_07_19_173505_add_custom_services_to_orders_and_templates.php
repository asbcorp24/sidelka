<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('custom_services')->nullable()->after('special_requirements');
        });

        Schema::table('order_templates', function (Blueprint $table) {
            $table->json('custom_services')->nullable()->after('special_requirements');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('custom_services');
        });

        Schema::table('order_templates', function (Blueprint $table) {
            $table->dropColumn('custom_services');
        });
    }
};
