<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafetyIncidentUpdate extends Model
{
    use HasFactory;

    protected $fillable = ['safety_incident_id', 'author_id', 'body', 'status_from', 'status_to', 'is_internal'];
    protected $casts = ['is_internal' => 'boolean'];

    public function incident(): BelongsTo { return $this->belongsTo(SafetyIncident::class, 'safety_incident_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}
