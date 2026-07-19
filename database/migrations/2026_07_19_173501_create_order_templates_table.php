<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('order_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('city');
            $table->string('address')->nullable();
            $table->string('schedule_type')->default('hourly');
            $table->string('recurrence_label')->nullable();
            $table->unsignedInteger('hourly_budget');
            $table->unsignedSmallInteger('patient_age')->nullable();
            $table->string('patient_name')->nullable();
            $table->text('special_requirements')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_templates');
    }
};
