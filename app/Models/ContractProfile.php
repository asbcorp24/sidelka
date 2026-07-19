<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'legal_full_name',
        'birth_date',
        'passport_series',
        'passport_number',
        'passport_issued_by',
        'passport_issued_at',
        'passport_department_code',
        'registration_address',
        'residence_address',
        'contract_city',
        'emergency_contact_name',
        'emergency_contact_phone',
        'inn',
        'snils',
        'tax_status',
        'is_self_employed',
        'bank_recipient_name',
        'bank_name',
        'bank_bik',
        'bank_account',
        'card_number',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'passport_issued_at' => 'date',
        'is_self_employed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
