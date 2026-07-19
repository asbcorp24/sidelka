<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('contract_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('legal_full_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('passport_series')->nullable();
            $table->string('passport_number')->nullable();
            $table->string('passport_issued_by')->nullable();
            $table->date('passport_issued_at')->nullable();
            $table->string('passport_department_code')->nullable();
            $table->string('registration_address')->nullable();
            $table->string('residence_address')->nullable();
            $table->string('contract_city')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('inn')->nullable();
            $table->string('snils')->nullable();
            $table->string('tax_status')->nullable();
            $table->boolean('is_self_employed')->default(false);
            $table->string('bank_recipient_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_bik')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('card_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('contract_profiles');
    }
};
