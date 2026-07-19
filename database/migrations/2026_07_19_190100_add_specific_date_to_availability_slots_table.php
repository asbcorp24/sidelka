<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('availability_slots', function (Blueprint $table) {
            $table->date('specific_date')->nullable()->after('weekday');
        });
    }

    public function down()
    {
        Schema::table('availability_slots', function (Blueprint $table) {
            $table->dropColumn('specific_date');
        });
    }
};
