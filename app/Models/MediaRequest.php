<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The "requests" table. Named MediaRequest on the model side to avoid
 * a clash with Illuminate\Http\Request in controllers.
 */
class MediaRequest extends Model
{
    use HasFactory;

    protected $table = 'requests';

    protected $fillable = [
        'host_id',
        'client_id',
        'title',
        'year',
        'type',
        'notes',
        'status',
        'up_next_at',
        'fulfilled_at',
        'fulfilled_library_item_id',
        'seen_by_client_at',
    ];

    protected $casts = [
        'up_next_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'seen_by_client_at' => 'datetime',
        'year' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_DECLINED = 'declined';

    // How the app words a status: suggestions are things to watch. "Seen"
    // (fulfilled) puts the title on the host's list; "Up next" is a pending
    // suggestion the host has agreed to; a declined one is "Passed".
    public function label(): string
    {
        return match (true) {
            $this->status === self::STATUS_FULFILLED => 'Seen',
            $this->status === self::STATUS_DECLINED => 'Passed',
            $this->up_next_at !== null => 'Up next',
            default => 'Waiting',
        };
    }

    /** Pending suggestions the host hasn't answered yet. */
    public function scopeAwaitingHost($query)
    {
        return $query->where('status', self::STATUS_PENDING)->whereNull('up_next_at');
    }

    /** Pending suggestions the host said they'll watch. */
    public function scopeUpNext($query)
    {
        return $query->where('status', self::STATUS_PENDING)->whereNotNull('up_next_at');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function fulfilledLibraryItem(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class, 'fulfilled_library_item_id');
    }
}
