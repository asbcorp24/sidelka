<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'role',
        'phone',
        'city',
        'city_id',
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

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    public function caregiverProfile(): HasOne
    {
        return $this->hasOne(CaregiverProfile::class);
    }

    public function cityRecord(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function clientOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    public function caregiverOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'caregiver_id');
    }

    public function caregiverAssignments(): HasMany
    {
        return $this->hasMany(OrderCaregiverAssignment::class, 'caregiver_id');
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'client_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'caregiver_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'client_id');
    }

    public function notificationsFeed(): HasMany
    {
        return $this->hasMany(MarketplaceNotification::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function walletTopUps(): HasMany
    {
        return $this->hasMany(WalletTopUp::class);
    }

    public function legalContractParties(): HasMany
    {
        return $this->hasMany(LegalContractParty::class);
    }

    public function crmRequestsResponsible(): HasMany
    {
        return $this->hasMany(CrmRequest::class, 'responsible_user_id');
    }

    public function crmInteractions(): HasMany
    {
        return $this->hasMany(CrmInteraction::class, 'person_user_id');
    }

    public function crmTasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'person_user_id');
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

    public function isCrm(): bool
    {
        return $this->role === 'crm';
    }
}
