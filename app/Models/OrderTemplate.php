<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'title',
        'description',
        'city',
        'address',
        'schedule_type',
        'recurrence_label',
        'hourly_budget',
        'patient_age',
        'patient_name',
        'special_requirements',
        'custom_services',
        'is_urgent',
    ];

    protected $casts = [
        'is_urgent' => 'boolean',
        'custom_services' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'order_template_service')->withTimestamps();
    }

    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(OrderScheduleSlot::class);
    }
}
