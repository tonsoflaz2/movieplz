<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invite extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_id',
        'code',
        'expires_at',
        'redeemed_at',
        'redeemed_by_client_id',
        'grants_host',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'grants_host' => 'boolean',
    ];

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function redeemedByClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'redeemed_by_client_id');
    }

    public function isUsable(): bool
    {
        return $this->redeemed_at === null
            && $this->expires_at->isFuture();
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('redeemed_at')
            ->where('expires_at', '>', now());
    }
}
