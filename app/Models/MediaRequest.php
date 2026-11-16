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
        'host_reply',
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

    // How the app words a status. The host answers a suggestion with
    // "In progress" (still pending, up_next_at set), "Added" (fulfilled;
    // the title goes in the library unless "Add to library?" was unticked)
    // or "Declined".
    public function label(): string
    {
        return match (true) {
            $this->status === self::STATUS_FULFILLED => 'Added',
            $this->status === self::STATUS_DECLINED => 'Declined',
            $this->up_next_at !== null => 'In progress',
            default => 'Waiting',
        };
    }

    /** Added, with its title put in the library (the box was ticked, and the title is still there). */
    public function wasAddedToLibrary(): bool
    {
        return $this->status === self::STATUS_FULFILLED && $this->fulfilled_library_item_id !== null;
    }

    /** Pending suggestions the host hasn't answered yet. */
    public function scopeAwaitingHost($query)
    {
        return $query->where('status', self::STATUS_PENDING)->whereNull('up_next_at');
    }

    /** Pending suggestions the host marked In progress. */
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
