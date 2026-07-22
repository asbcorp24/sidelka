<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderCaregiverAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_schedule_slot_id',
        'caregiver_id',
        'status',
        'confirmed_at',
        'completion_requested_at',
        'client_confirmed_at',
        'completed_at',
        'payout_generated_at',
        'notes',
        'completion_note',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'completion_requested_at' => 'datetime',
        'client_confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'payout_generated_at' => 'datetime',
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

    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class, 'order_caregiver_assignment_id');
    }
}
