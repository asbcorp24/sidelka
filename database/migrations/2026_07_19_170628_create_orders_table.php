<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caregiver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('city')->index();
            $table->string('address')->nullable();
            $table->string('schedule_type')->default('hourly');
            $table->string('status')->default('published')->index();
            $table->string('payment_status')->default('pending');
            $table->unsignedInteger('hourly_budget');
            $table->unsignedSmallInteger('patient_age')->nullable();
            $table->string('patient_name')->nullable();
            $table->text('special_requirements')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
