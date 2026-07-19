<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Order extends Model
{
    use HasFactory;

    public const STATUS_LABELS = [
        'published' => 'Опубликован',
        'matched' => 'Ожидает подтверждения',
        'in_chat' => 'Согласование',
        'in_progress' => 'В работе',
        'completed' => 'Завершен',
        'cancelled' => 'Отменен',
    ];

    public const PAYMENT_STATUS_LABELS = [
        'pending' => 'Ожидает оплаты',
        'held' => 'Средства удержаны',
        'released' => 'Выплачено сиделке',
        'refunded' => 'Возвращено клиенту',
        'cancelled' => 'Оплата отменена',
    ];

    public const STATUS_BADGE_CLASSES = [
        'published' => 'text-bg-secondary',
        'matched' => 'text-bg-warning',
        'in_chat' => 'text-bg-info',
        'in_progress' => 'text-bg-primary',
        'completed' => 'text-bg-success',
        'cancelled' => 'text-bg-dark',
    ];

    public const PAYMENT_STATUS_BADGE_CLASSES = [
        'pending' => 'text-bg-secondary',
        'held' => 'text-bg-warning',
        'released' => 'text-bg-success',
        'refunded' => 'text-bg-info',
        'cancelled' => 'text-bg-dark',
    ];

    protected $fillable = [
        'client_id',
        'caregiver_id',
        'created_by_family_member_id',
        'title',
        'description',
        'city',
        'city_id',
        'address',
        'schedule_type',
        'shift_type_id',
        'recurrence_label',
        'status',
        'payment_status',
        'is_urgent',
        'needs_today',
        'allows_multiple_caregivers',
        'hourly_budget',
        'patient_age',
        'patient_name',
        'special_requirements',
        'custom_services',
        'starts_at',
        'ends_at',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_urgent' => 'boolean',
        'needs_today' => 'boolean',
        'allows_multiple_caregivers' => 'boolean',
        'custom_services' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_id');
    }

    public function familyMember(): BelongsTo
    {
        return $this->belongsTo(ClientFamilyMember::class, 'created_by_family_member_id');
    }

    public function cityRecord(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ShiftType::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withTimestamps();
    }

    public function clinicPartnerServices(): BelongsToMany
    {
        return $this->belongsToMany(ClinicPartnerService::class, 'order_clinic_partner_service')
            ->withPivot(['price_at_booking', 'discount_percent'])
            ->withTimestamps();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(OrderScheduleSlot::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(OrderCancellation::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(OrderExpense::class);
    }

    public function caregiverAssignments(): HasMany
    {
        return $this->hasMany(OrderCaregiverAssignment::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return self::PAYMENT_STATUS_LABELS[$this->payment_status] ?? $this->payment_status;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return self::STATUS_BADGE_CLASSES[$this->status] ?? 'text-bg-light';
    }

    public function getPaymentStatusBadgeClassAttribute(): string
    {
        return self::PAYMENT_STATUS_BADGE_CLASSES[$this->payment_status] ?? 'text-bg-light';
    }

    public function getScheduledHoursAttribute(): int
    {
        if ($this->relationLoaded('scheduleSlots') && $this->scheduleSlots->isNotEmpty()) {
            $minutes = $this->scheduleSlots->sum(function (OrderScheduleSlot $slot) {
                $start = Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->starts_at);
                $end = Carbon::parse($slot->scheduled_date->format('Y-m-d') . ' ' . $slot->ends_at);

                return max(0, $start->diffInMinutes($end));
            });

            return max(1, (int) ceil($minutes / 60));
        }

        if ($this->starts_at && $this->ends_at) {
            return max(1, (int) ceil($this->starts_at->diffInMinutes($this->ends_at) / 60));
        }

        return 1;
    }

    public function getBaseAmountAttribute(): int
    {
        return $this->hourly_budget * $this->scheduled_hours;
    }

    public function getApprovedExpensesAmountAttribute(): int
    {
        $expenses = $this->relationLoaded('expenses') ? $this->expenses : $this->expenses()->get();

        return (int) $expenses
            ->whereIn('status', ['approved', 'billed'])
            ->sum('line_total');
    }

    public function getClinicServicesAmountAttribute(): int
    {
        $services = $this->relationLoaded('clinicPartnerServices')
            ? $this->clinicPartnerServices
            : $this->clinicPartnerServices()->get();

        return (int) $services->sum(fn (ClinicPartnerService $service) => (int) $service->pivot->price_at_booking);
    }

    public function getTotalInvoiceAmountAttribute(): int
    {
        return $this->base_amount + $this->approved_expenses_amount + $this->clinic_services_amount;
    }
}
