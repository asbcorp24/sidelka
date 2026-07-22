<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_id',
        'caregiver_id',
        'gross_amount',
        'commission_percent',
        'commission_amount',
        'amount',
        'currency',
        'status',
        'destination',
        'paid_at',
    ];

    protected $casts = [
        'commission_percent' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_id');
    }

    public function agentCommission(): HasOne
    {
        return $this->hasOne(AgentCommission::class);
    }
}
