<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('client_family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('can_create_orders')->default(true);
            $table->boolean('can_view_chats')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('client_family_members');
    }
};
