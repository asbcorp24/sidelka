<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('wallet_balance')->default(0)->after('reviews_count');
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('type');
            $table->integer('amount');
            $table->unsignedInteger('balance_after');
            $table->string('currency', 3)->default('RUB');
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('clinic_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city');
            $table->string('contact_phone')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('clinic_partner_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_partner_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('base_price')->default(0);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('order_clinic_partner_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_partner_service_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_at_booking')->default(0);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_clinic_partner_service');
        Schema::dropIfExists('clinic_partner_services');
        Schema::dropIfExists('clinic_partners');
        Schema::dropIfExists('wallet_transactions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('wallet_balance');
        });
    }
};
