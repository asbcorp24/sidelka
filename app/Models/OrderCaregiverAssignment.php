<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderCaregiverAssignment extends Model
{
    use HasFactory;

    public const STATUS_LABELS = [
        'applied' => 'Отклик',
        'reserved' => 'Резерв',
        'invited' => 'Приглашение',
        'accepted' => 'Подтверждено',
        'declined' => 'Отклонено',
        'completed' => 'Завершено',
        'completion_requested' => 'Ожидает подтверждения',
    ];

    public const STATUS_BADGE_CLASSES = [
        'applied' => 'text-bg-primary',
        'reserved' => 'text-bg-info',
        'invited' => 'text-bg-warning',
        'accepted' => 'text-bg-success',
        'declined' => 'text-bg-danger',
        'completed' => 'text-bg-success',
        'completion_requested' => 'text-bg-warning',
    ];

    protected $fillable = [
        'order_id', 'order_schedule_slot_id', 'caregiver_id', 'status', 'confirmed_at',
        'completion_requested_at', 'client_confirmed_at', 'completed_at', 'payout_generated_at',
        'notes', 'completion_note',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'completion_requested_at' => 'datetime',
        'client_confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'payout_generated_at' => 'datetime',
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function scheduleSlot(): BelongsTo { return $this->belongsTo(OrderScheduleSlot::class, 'order_schedule_slot_id'); }
    public function caregiver(): BelongsTo { return $this->belongsTo(User::class, 'caregiver_id'); }
    public function payout(): HasOne { return $this->hasOne(Payout::class, 'order_caregiver_assignment_id'); }
    public function act(): HasOne { return $this->hasOne(ShiftAct::class, 'order_caregiver_assignment_id'); }
    public function journal(): HasOne { return $this->hasOne(ShiftJournal::class, 'order_caregiver_assignment_id'); }
    public function report(): HasOne { return $this->hasOne(ShiftReport::class, 'order_caregiver_assignment_id'); }
    public function disputes(): HasMany { return $this->hasMany(ShiftDispute::class, 'order_caregiver_assignment_id'); }
    public function incidents(): HasMany { return $this->hasMany(SafetyIncident::class, 'order_caregiver_assignment_id'); }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return self::STATUS_BADGE_CLASSES[$this->status] ?? 'text-bg-light';
    }
}
