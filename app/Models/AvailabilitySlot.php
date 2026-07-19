<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilitySlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'caregiver_profile_id',
        'weekday',
        'specific_date',
        'starts_at',
        'ends_at',
        'is_recurring',
        'notes',
    ];

    protected $casts = [
        'is_recurring' => 'boolean',
        'specific_date' => 'date',
    ];

    public function caregiverProfile(): BelongsTo
    {
        return $this->belongsTo(CaregiverProfile::class);
    }
}
