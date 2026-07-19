<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCaregiverAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_schedule_slot_id',
        'caregiver_id',
        'status',
        'confirmed_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scheduleSlot(): BelongsTo
    {
        return $this->belongsTo(OrderScheduleSlot::class, 'order_schedule_slot_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_id');
    }
}
