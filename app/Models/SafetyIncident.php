<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SafetyIncident extends Model
{
    use HasFactory;

    public const TYPE_LABELS = [
        'fall' => 'Падение',
        'deterioration' => 'Ухудшение состояния',
        'missed_medication' => 'Пропуск лекарства',
        'aggression' => 'Агрессивное поведение',
        'no_access' => 'Нет доступа к подопечному',
        'caregiver_no_show' => 'Сиделка не прибыла',
        'emergency_call' => 'Вызов экстренной помощи',
        'property' => 'Повреждение имущества',
        'other' => 'Другое',
    ];

    public const SEVERITY_LABELS = [
        'low' => 'Низкая',
        'medium' => 'Средняя',
        'high' => 'Высокая',
        'critical' => 'Критическая',
    ];

    protected $fillable = [
        'public_id', 'order_id', 'order_caregiver_assignment_id', 'shift_journal_id',
        'reported_by_id', 'assigned_to_id', 'incident_type', 'severity', 'status',
        'occurred_at', 'description', 'actions_taken', 'emergency_called',
        'emergency_service_reference', 'client_notified_at', 'resolved_at', 'resolution',
    ];

    protected $casts = [
        'occurred_at' => 'datetime', 'client_notified_at' => 'datetime',
        'resolved_at' => 'datetime', 'emergency_called' => 'boolean',
    ];

    public function getRouteKeyName(): string { return 'public_id'; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function assignment(): BelongsTo { return $this->belongsTo(OrderCaregiverAssignment::class, 'order_caregiver_assignment_id'); }
    public function journal(): BelongsTo { return $this->belongsTo(ShiftJournal::class, 'shift_journal_id'); }
    public function reportedBy(): BelongsTo { return $this->belongsTo(User::class, 'reported_by_id'); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to_id'); }
    public function updates(): HasMany { return $this->hasMany(SafetyIncidentUpdate::class); }
}
