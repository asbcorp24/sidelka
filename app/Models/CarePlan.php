<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'created_by_id', 'version', 'status', 'patient_name', 'diagnosis_summary',
        'allergies', 'medications', 'nutrition', 'mobility', 'hygiene', 'communication', 'risks',
        'emergency_instructions', 'emergency_contact_name', 'emergency_contact_phone', 'notes',
        'effective_from', 'effective_to',
    ];

    protected $casts = ['effective_from' => 'datetime', 'effective_to' => 'datetime'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_id'); }
    public function items(): HasMany { return $this->hasMany(CarePlanItem::class)->orderBy('sort_order'); }
    public function journals(): HasMany { return $this->hasMany(ShiftJournal::class); }
}
