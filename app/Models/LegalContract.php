<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalContract extends Model
{
    use HasFactory;

    public const TYPE_CLIENT_AGENCY = 'client_agency';
    public const TYPE_CAREGIVER_AGENCY = 'caregiver_agency';
    public const TYPE_ORDER_SERVICE = 'order_service';

    public const STATUS_AWAITING = 'awaiting_signatures';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'public_id',
        'type',
        'order_id',
        'created_by_id',
        'number',
        'version',
        'title',
        'status',
        'body_html',
        'document_hash',
        'meta',
        'sent_at',
        'signed_at',
        'cancelled_at',
        'expires_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'sent_at' => 'datetime',
        'signed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(LegalContractParty::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(LegalContractSignature::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LegalContractEvent::class);
    }

    public function isFullySigned(): bool
    {
        $parties = $this->relationLoaded('parties') ? $this->parties : $this->parties()->get();

        return $parties->where('is_required', true)->isNotEmpty()
            && $parties->where('is_required', true)->every(fn (LegalContractParty $party) => $party->status === 'signed');
    }
}
