<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarePlanItem extends Model
{
    use HasFactory;

    protected $fillable = ['care_plan_id', 'category', 'title', 'instructions', 'schedule_text', 'is_required', 'sort_order'];
    protected $casts = ['is_required' => 'boolean'];

    public function carePlan(): BelongsTo { return $this->belongsTo(CarePlan::class); }
}
