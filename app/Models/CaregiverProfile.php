<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaregiverProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'experience_years',
        'hourly_rate_from',
        'shift_rate_from',
        'employment_format',
        'education',
        'bio',
        'medical_skills',
        'household_skills',
        'ready_for_night',
        'ready_for_live_in',
        'documents_verified',
    ];

    protected $casts = [
        'ready_for_night' => 'boolean',
        'ready_for_live_in' => 'boolean',
        'documents_verified' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'caregiver_profile_service')
            ->withPivot('capability_status')
            ->withTimestamps();
    }

    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(AvailabilitySlot::class);
    }

    public function availableServices()
    {
        return $this->services->where('pivot.capability_status', 'can_do')->values();
    }

    public function restrictedServices()
    {
        return $this->services->where('pivot.capability_status', 'cannot_do')->values();
    }
}
