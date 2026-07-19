<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'role',
        'phone',
        'city',
        'avatar',
        'about',
        'rating',
        'reviews_count',
        'is_verified',
        'last_seen_at',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'is_verified' => 'boolean',
        'rating' => 'decimal:2',
    ];

    public function caregiverProfile(): HasOne
    {
        return $this->hasOne(CaregiverProfile::class);
    }

    public function clientOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    public function caregiverOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'caregiver_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function writtenReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'author_id');
    }

    public function receivedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'subject_id');
    }

    public function familyMembers(): HasMany
    {
        return $this->hasMany(ClientFamilyMember::class, 'client_id');
    }

    public function orderTemplates(): HasMany
    {
        return $this->hasMany(OrderTemplate::class, 'client_id');
    }

    public function contractProfile(): HasOne
    {
        return $this->hasOne(ContractProfile::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UserDocument::class);
    }

    public function isCaregiver(): bool
    {
        return $this->role === 'caregiver';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
