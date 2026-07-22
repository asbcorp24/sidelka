<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LegalContractParty extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_token',
        'legal_contract_id',
        'user_id',
        'role',
        'name',
        'email',
        'phone',
        'is_required',
        'status',
        'signed_at',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'signed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $party) {
            $party->public_token = bin2hex(random_bytes(32));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LegalContract::class, 'legal_contract_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(LegalSignatureChallenge::class);
    }

    public function signature(): HasOne
    {
        return $this->hasOne(LegalContractSignature::class);
    }
}
