<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalContractEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_contract_id',
        'actor_user_id',
        'event',
        'data',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LegalContract::class, 'legal_contract_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
