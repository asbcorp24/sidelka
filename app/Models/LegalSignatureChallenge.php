<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalSignatureChallenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_contract_party_id',
        'code_hash',
        'channel',
        'destination',
        'attempts',
        'max_attempts',
        'provider_message_id',
        'request_ip',
        'sent_at',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = ['code_hash'];

    protected $casts = [
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(LegalContractParty::class, 'legal_contract_party_id');
    }
}
