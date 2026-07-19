<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'caregiver_id',
        'approved_by_id',
        'kind',
        'title',
        'description',
        'quantity',
        'unit_price',
        'line_total',
        'status',
        'purchased_at',
        'approved_at',
        'billed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'purchased_at' => 'datetime',
        'approved_at' => 'datetime',
        'billed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
