<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Проверка email вводится для новых регистраций. Существующие аккаунты
        // считаем подтвержденными, чтобы не заблокировать доступ после обновления.
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Откат намеренно не снимает подтверждение с существующих пользователей.
    }
};
