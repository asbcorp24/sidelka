<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftAct extends Model
{
    use HasFactory;

    public const STATUS_AWAITING_CLIENT = 'awaiting_client';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'public_id', 'order_id', 'order_caregiver_assignment_id', 'client_id', 'caregiver_id',
        'number', 'status', 'body_html', 'document_hash', 'gross_amount', 'commission_amount',
        'payout_amount', 'caregiver_confirmed_at', 'client_confirmed_at', 'caregiver_ip',
        'client_ip', 'caregiver_user_agent', 'client_user_agent', 'signed_at', 'disputed_at', 'meta',
    ];

    protected $casts = [
        'caregiver_confirmed_at' => 'datetime',
        'client_confirmed_at' => 'datetime',
        'signed_at' => 'datetime',
        'disputed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function getRouteKeyName(): string { return 'public_id'; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function assignment(): BelongsTo { return $this->belongsTo(OrderCaregiverAssignment::class, 'order_caregiver_assignment_id'); }
    public function client(): BelongsTo { return $this->belongsTo(User::class, 'client_id'); }
    public function caregiver(): BelongsTo { return $this->belongsTo(User::class, 'caregiver_id'); }
    public function disputes(): HasMany { return $this->hasMany(ShiftDispute::class); }
}
