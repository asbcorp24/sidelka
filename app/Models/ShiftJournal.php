<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftJournal extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_caregiver_assignment_id', 'order_id', 'care_plan_id', 'caregiver_id', 'status',
        'arrived_at', 'left_at', 'summary', 'observations', 'vitals', 'meals', 'medications',
        'hygiene', 'mobility', 'client_comment', 'submitted_at', 'accepted_at',
    ];

    protected $casts = [
        'arrived_at' => 'datetime', 'left_at' => 'datetime', 'submitted_at' => 'datetime',
        'accepted_at' => 'datetime', 'vitals' => 'array', 'meals' => 'array',
        'medications' => 'array', 'hygiene' => 'array', 'mobility' => 'array',
    ];

    public function assignment(): BelongsTo { return $this->belongsTo(OrderCaregiverAssignment::class, 'order_caregiver_assignment_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function carePlan(): BelongsTo { return $this->belongsTo(CarePlan::class); }
    public function caregiver(): BelongsTo { return $this->belongsTo(User::class, 'caregiver_id'); }
    public function entries(): HasMany { return $this->hasMany(ShiftJournalEntry::class)->orderBy('happened_at'); }
    public function incidents(): HasMany { return $this->hasMany(SafetyIncident::class); }
}
