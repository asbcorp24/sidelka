<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientFamilyMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'relationship',
        'phone',
        'email',
        'can_create_orders',
        'can_view_chats',
        'notes',
    ];

    protected $casts = [
        'can_create_orders' => 'boolean',
        'can_view_chats' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by_family_member_id');
    }
}
