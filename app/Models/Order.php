<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'caregiver_id',
        'created_by_family_member_id',
        'title',
        'description',
        'city',
        'address',
        'schedule_type',
        'recurrence_label',
        'status',
        'payment_status',
        'is_urgent',
        'needs_today',
        'hourly_budget',
        'patient_age',
        'patient_name',
        'special_requirements',
        'custom_services',
        'starts_at',
        'ends_at',
        'confirmed_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'is_urgent' => 'boolean',
        'needs_today' => 'boolean',
        'custom_services' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_id');
    }

    public function familyMember(): BelongsTo
    {
        return $this->belongsTo(ClientFamilyMember::class, 'created_by_family_member_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withTimestamps();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(OrderScheduleSlot::class);
    }
}
