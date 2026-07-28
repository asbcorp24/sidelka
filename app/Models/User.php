<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use App\Support\CrmPermissions;
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
        'name', 'email', 'email_verified_at', 'role', 'staff_role', 'staff_permissions', 'staff_active',
        'phone', 'city', 'city_id', 'avatar', 'about', 'rating', 'reviews_count', 'is_verified',
        'last_seen_at', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'is_verified' => 'boolean',
        'staff_active' => 'boolean',
        'staff_permissions' => 'array',
        'rating' => 'decimal:2',
    ];

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    public function caregiverProfile(): HasOne { return $this->hasOne(CaregiverProfile::class); }
    public function cityRecord(): BelongsTo { return $this->belongsTo(City::class, 'city_id'); }
    public function clientOrders(): HasMany { return $this->hasMany(Order::class, 'client_id'); }
    public function caregiverOrders(): HasMany { return $this->hasMany(Order::class, 'caregiver_id'); }
    public function caregiverAssignments(): HasMany { return $this->hasMany(OrderCaregiverAssignment::class, 'caregiver_id'); }
    public function sentMessages(): HasMany { return $this->hasMany(Message::class, 'sender_id'); }
    public function writtenReviews(): HasMany { return $this->hasMany(Review::class, 'author_id'); }
    public function receivedReviews(): HasMany { return $this->hasMany(Review::class, 'subject_id'); }
    public function familyMembers(): HasMany { return $this->hasMany(ClientFamilyMember::class, 'client_id'); }
    public function orderTemplates(): HasMany { return $this->hasMany(OrderTemplate::class, 'client_id'); }
    public function contractProfile(): HasOne { return $this->hasOne(ContractProfile::class); }
    public function documents(): HasMany { return $this->hasMany(UserDocument::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class, 'client_id'); }
    public function payouts(): HasMany { return $this->hasMany(Payout::class, 'caregiver_id'); }
    public function refunds(): HasMany { return $this->hasMany(Refund::class, 'client_id'); }
    public function notificationsFeed(): HasMany { return $this->hasMany(MarketplaceNotification::class); }
    public function socialAccounts(): HasMany { return $this->hasMany(SocialAccount::class); }
    public function walletTransactions(): HasMany { return $this->hasMany(WalletTransaction::class); }
    public function walletTopUps(): HasMany { return $this->hasMany(WalletTopUp::class); }
    public function legalContractParties(): HasMany { return $this->hasMany(LegalContractParty::class); }
    public function crmRequestsResponsible(): HasMany { return $this->hasMany(CrmRequest::class, 'responsible_user_id'); }
    public function crmInteractions(): HasMany { return $this->hasMany(CrmInteraction::class, 'person_user_id'); }
    public function crmTasks(): HasMany { return $this->hasMany(CrmTask::class, 'person_user_id'); }
    public function shiftActsAsClient(): HasMany { return $this->hasMany(ShiftAct::class, 'client_id'); }
    public function shiftActsAsCaregiver(): HasMany { return $this->hasMany(ShiftAct::class, 'caregiver_id'); }
    public function reportedIncidents(): HasMany { return $this->hasMany(SafetyIncident::class, 'reported_by_id'); }
    public function assignedIncidents(): HasMany { return $this->hasMany(SafetyIncident::class, 'assigned_to_id'); }
    public function favoriteCaregivers(): HasMany { return $this->hasMany(CaregiverFavorite::class, 'client_id'); }
    public function favoritedByClients(): HasMany { return $this->hasMany(CaregiverFavorite::class, 'caregiver_id'); }
    public function patientProfiles(): HasMany { return $this->hasMany(PatientProfile::class, 'client_id'); }
    public function sentReports(): HasMany { return $this->hasMany(UserReport::class, 'reporter_id'); }
    public function receivedReports(): HasMany { return $this->hasMany(UserReport::class, 'reported_user_id'); }
    public function shiftReports(): HasMany { return $this->hasMany(ShiftReport::class, 'caregiver_id'); }

    public function isCaregiver(): bool { return $this->role === 'caregiver'; }
    public function isClient(): bool { return $this->role === 'client'; }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isCrm(): bool { return $this->role === 'crm'; }

    public function hasStaffPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isCrm() || ! $this->staff_active) {
            return false;
        }

        $permissions = array_values(array_unique(array_merge(
            CrmPermissions::forRole($this->staff_role),
            $this->staff_permissions ?? [],
        )));

        return in_array($permission, $permissions, true);
    }

    public function staffRoleLabel(): string
    {
        return CrmPermissions::ROLE_LABELS[$this->staff_role] ?? 'Сотрудник CRM';
    }
}
