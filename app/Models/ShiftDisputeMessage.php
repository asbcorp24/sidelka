<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftDisputeMessage extends Model
{
    use HasFactory;

    protected $fillable = ['shift_dispute_id', 'author_id', 'body', 'attachment_path', 'is_internal'];
    protected $casts = ['is_internal' => 'boolean'];

    public function dispute(): BelongsTo { return $this->belongsTo(ShiftDispute::class, 'shift_dispute_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}
