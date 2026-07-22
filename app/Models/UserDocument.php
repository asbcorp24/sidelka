<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'document_type', 'title', 'document_number', 'file_path', 'original_name',
        'mime_type', 'file_size', 'issued_at', 'expires_at', 'verification_status', 'is_required',
        'blocks_assignments', 'verified_at', 'verified_by_id', 'reminder_30_at', 'reminder_14_at',
        'reminder_3_at', 'expired_task_at', 'notes',
    ];

    protected $casts = [
        'issued_at' => 'date', 'expires_at' => 'date', 'verified_at' => 'datetime',
        'reminder_30_at' => 'datetime', 'reminder_14_at' => 'datetime',
        'reminder_3_at' => 'datetime', 'expired_task_at' => 'datetime',
        'is_required' => 'boolean', 'blocks_assignments' => 'boolean',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function verifiedBy(): BelongsTo { return $this->belongsTo(User::class, 'verified_by_id'); }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isBefore(today());
    }

    public function blocksCaregiver(): bool
    {
        return $this->is_required
            && $this->blocks_assignments
            && ($this->verification_status !== 'verified' || $this->isExpired());
    }
}
