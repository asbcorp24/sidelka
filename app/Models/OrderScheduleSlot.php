<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderScheduleSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_template_id',
        'scheduled_date',
        'starts_at',
        'ends_at',
        'label',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderTemplate(): BelongsTo
    {
        return $this->belongsTo(OrderTemplate::class);
    }
}
