<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Review $review) {
            $review->loadMissing('order');

            if (! $review->order || $review->order->status !== 'completed') {
                throw new \RuntimeException('Отзыв можно оставить только после завершения заказа.');
            }
        });
    }

    protected $fillable = [
        'order_id',
        'author_id',
        'subject_id',
        'subject_role',
        'rating',
        'comment',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }
}
