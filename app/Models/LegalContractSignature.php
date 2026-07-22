<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalContractSignature extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_contract_id',
        'legal_contract_party_id',
        'user_id',
        'method',
        'channel',
        'destination',
        'document_hash',
        'signed_at',
        'ip_address',
        'user_agent',
        'evidence',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'evidence' => 'array',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LegalContract::class, 'legal_contract_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(LegalContractParty::class, 'legal_contract_party_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
