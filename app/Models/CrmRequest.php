<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmRequest extends Model
{
    use HasFactory;

    public const STATUS_LABELS = [
        'new' => 'Новая',
        'qualification' => 'Квалификация',
        'searching' => 'Ищем сиделку',
        'caregiver_found' => 'Предложили сиделку',
        'awaiting_client' => 'Согласование с клиентом',
        'booked' => 'Согласовано',
        'active' => 'В работе',
        'completed' => 'Закрыта',
        'cancelled' => 'Отменена',
    ];

    public const PRIORITY_LABELS = [
        'low' => 'Низкий',
        'normal' => 'Обычный',
        'high' => 'Высокий',
        'urgent' => 'Срочно',
    ];

    protected $fillable = [
        'public_id',
        'source',
        'status',
        'priority',
        'responsible_user_id',
        'client_user_id',
        'caregiver_user_id',
        'order_id',
        'created_by_id',
        'caller_name',
        'caller_phone',
        'caller_email',
        'patient_name',
        'patient_age',
        'city',
        'address',
        'service_text',
        'schedule_text',
        'starts_at',
        'ends_at',
        'budget_per_hour',
        'lead_cost',
        'notes',
        'next_contact_at',
        'last_contact_at',
        'closed_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'next_contact_at' => 'datetime',
        'last_contact_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function caregiverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(CrmInteraction::class)->orderByDesc('happened_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class)->orderBy('status')->orderBy('due_at');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? $this->priority;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'new' => 'text-bg-danger',
            'qualification' => 'text-bg-warning',
            'searching' => 'text-bg-info',
            'caregiver_found', 'awaiting_client' => 'text-bg-primary',
            'booked', 'active' => 'text-bg-success',
            'completed' => 'text-bg-dark',
            'cancelled' => 'text-bg-secondary',
            default => 'text-bg-light',
        };
    }
}
