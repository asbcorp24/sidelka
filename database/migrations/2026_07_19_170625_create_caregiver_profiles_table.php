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
        Schema::create('caregiver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('experience_years')->default(0);
            $table->unsignedInteger('hourly_rate_from');
            $table->unsignedInteger('shift_rate_from')->nullable();
            $table->string('employment_format')->default('hourly');
            $table->string('education')->nullable();
            $table->text('bio')->nullable();
            $table->text('medical_skills')->nullable();
            $table->text('household_skills')->nullable();
            $table->boolean('ready_for_night')->default(false);
            $table->boolean('ready_for_live_in')->default(false);
            $table->boolean('documents_verified')->default(false);
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
        Schema::dropIfExists('caregiver_profiles');
    }
};
