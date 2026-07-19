<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'description',
        'requires_medical_training',
        'hourly_surcharge',
    ];

    protected $casts = [
        'requires_medical_training' => 'boolean',
    ];

    public function caregiverProfiles(): BelongsToMany
    {
        return $this->belongsToMany(CaregiverProfile::class, 'caregiver_profile_service')->withTimestamps();
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class)->withTimestamps();
    }
}
