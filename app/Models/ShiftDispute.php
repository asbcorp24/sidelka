<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftDispute extends Model
{
    use HasFactory;

    public const STATUS_LABELS = [
        'open' => 'Открыт',
        'in_review' => 'На рассмотрении',
        'resolved' => 'Решён',
        'cancelled' => 'Отменён',
    ];

    protected $fillable = [
        'public_id', 'order_id', 'order_caregiver_assignment_id', 'shift_act_id', 'opened_by_id',
        'assigned_to_id', 'status', 'reason', 'description', 'requested_action', 'decision',
        'approved_gross_amount', 'resolution', 'opened_at', 'resolved_at',
    ];

    protected $casts = ['opened_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function getRouteKeyName(): string { return 'public_id'; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function assignment(): BelongsTo { return $this->belongsTo(OrderCaregiverAssignment::class, 'order_caregiver_assignment_id'); }
    public function act(): BelongsTo { return $this->belongsTo(ShiftAct::class, 'shift_act_id'); }
    public function openedBy(): BelongsTo { return $this->belongsTo(User::class, 'opened_by_id'); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to_id'); }
    public function messages(): HasMany { return $this->hasMany(ShiftDisputeMessage::class); }
}
