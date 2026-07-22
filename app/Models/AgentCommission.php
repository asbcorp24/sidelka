<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_id',
        'payout_id',
        'caregiver_id',
        'gross_amount',
        'percent',
        'amount',
        'currency',
        'status',
        'recognized_at',
    ];

    protected $casts = [
        'percent' => 'decimal:2',
        'recognized_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_id');
    }
}
