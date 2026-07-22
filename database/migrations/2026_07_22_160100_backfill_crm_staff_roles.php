<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'crm')
            ->whereNull('staff_role')
            ->update([
                'staff_role' => 'manager',
                'staff_permissions' => json_encode([], JSON_UNESCAPED_UNICODE),
                'staff_active' => true,
            ]);
    }

    public function down(): void
    {
        // Должность не сбрасывается, чтобы откат не лишал сотрудников настроенного доступа.
    }
};
