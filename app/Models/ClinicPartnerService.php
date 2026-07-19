<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClinicPartnerService extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_partner_id',
        'name',
        'description',
        'base_price',
        'discount_percent',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(ClinicPartner::class, 'clinic_partner_id');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_clinic_partner_service')
            ->withPivot(['price_at_booking', 'discount_percent'])
            ->withTimestamps();
    }
}
