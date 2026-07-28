<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_caregiver_assignment_id',
        'caregiver_id',
        'summary',
        'completed_tasks',
        'purchased_items',
        'health_changes',
        'photo_paths',
        'submitted_at',
    ];

    protected $casts = [
        'completed_tasks' => 'array',
        'purchased_items' => 'array',
        'photo_paths' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(OrderCaregiverAssignment::class, 'order_caregiver_assignment_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_id');
    }
}
