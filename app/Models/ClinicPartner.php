<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicPartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'city',
        'contact_phone',
        'website',
        'description',
        'discount_percent',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(ClinicPartnerService::class);
    }
}
