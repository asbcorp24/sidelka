<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shift_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('city')->constrained('cities')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('city')->constrained('cities')->nullOnDelete();
            $table->foreignId('shift_type_id')->nullable()->after('schedule_type')->constrained('shift_types')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('confirmed_at');
        });

        Schema::table('order_templates', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('city')->constrained('cities')->nullOnDelete();
            $table->foreignId('shift_type_id')->nullable()->after('schedule_type')->constrained('shift_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('shift_type_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('shift_type_id');
            $table->dropColumn('cancelled_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });

        Schema::dropIfExists('shift_types');
        Schema::dropIfExists('cities');
    }
};
