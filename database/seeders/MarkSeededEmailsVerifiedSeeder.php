<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class MarkSeededEmailsVerifiedSeeder extends Seeder
{
    public function run(): void
    {
        User::query()
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }
}
