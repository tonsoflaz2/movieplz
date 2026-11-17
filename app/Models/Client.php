<?php

namespace App\Models;

use App\Support\ReconnectCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_id',
        'invite_id',
        'role',
        'display_name',
        'device_token',
        'push_token',
        'push_platform',
        'push_environment',
        'revoked_at',
        'disconnected_at',
        'last_seen_at',
    ];

    protected $hidden = ['reconnect_code', 'reconnect_code_hash'];

    protected $casts = [
        'revoked_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'reconnect_code' => 'encrypted',
    ];

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function invite(): BelongsTo
    {
        return $this->belongsTo(Invite::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(MediaRequest::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** The longest name, and nickname, a person can have. */
    public const NAME_MAX = 40;

    /**
     * The name the host sees: the nickname they gave this friend, or else
     * the name the friend picked. Only for the host's screens and
     * notifications; the friend always sees their own display_name.
     */
    public function nameForHost(): string
    {
        return $this->host_nickname ?? $this->display_name;
    }

    public function isHost(): bool
    {
        return $this->role === 'host';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function scopeClientRole(Builder $query): Builder
    {
        return $query->where('role', 'client');
    }

    public function isDisconnected(): bool
    {
        return $this->disconnected_at !== null;
    }

    /** Pushes go to a connected, unrevoked person with a token. */
    public function canBePushed(): bool
    {
        return $this->push_token && ! $this->isDisconnected() && ! $this->isRevoked();
    }

    /** Their reconnect code, made the first time it's asked for. */
    public function reconnectCode(): string
    {
        if (! $this->reconnect_code) {
            $code = ReconnectCode::generate();
            $this->forceFill([
                'reconnect_code' => $code,
                'reconnect_code_hash' => ReconnectCode::hash($code),
            ])->save();
        }

        return $this->reconnect_code;
    }

    /** The person a typed-in reconnect code belongs to, if it's still good. */
    public static function findByReconnectCode(string $input): ?self
    {
        if (! ReconnectCode::looksValid($input)) {
            return null;
        }

        return self::query()
            ->with('host')
            ->where('reconnect_code_hash', ReconnectCode::hash($input))
            ->whereNull('revoked_at')
            ->first();
    }
}
