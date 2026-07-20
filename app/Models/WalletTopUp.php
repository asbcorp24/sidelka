<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTopUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'user_id',
        'provider',
        'order_number',
        'provider_order_id',
        'amount',
        'amount_minor',
        'currency',
        'status',
        'payment_url',
        'error_code',
        'error_message',
        'provider_payload',
        'provider_status_payload',
        'paid_at',
        'failed_at',
        'expires_at',
    ];

    protected $casts = [
        'provider_payload' => 'array',
        'provider_status_payload' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Создаётся',
            'awaiting_payment' => 'Ожидает оплаты',
            'paid' => 'Оплачено',
            'failed' => 'Ошибка',
            'cancelled' => 'Отменено',
            'expired' => 'Истёк срок',
            default => $this->status,
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'text-bg-success',
            'pending', 'awaiting_payment' => 'text-bg-warning',
            'cancelled', 'expired' => 'text-bg-secondary',
            'failed' => 'text-bg-danger',
            default => 'text-bg-light',
        };
    }
}
