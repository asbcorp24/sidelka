<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftJournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_journal_id', 'created_by_id', 'entry_type', 'title', 'value', 'unit',
        'happened_at', 'notes', 'is_alert',
    ];

    protected $casts = ['happened_at' => 'datetime', 'is_alert' => 'boolean'];

    public function journal(): BelongsTo { return $this->belongsTo(ShiftJournal::class, 'shift_journal_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_id'); }
}
