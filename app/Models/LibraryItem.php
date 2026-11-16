<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_id',
        'title',
        'sort_title',
        'type',
        'year',
        'tmdb_id',
        'poster_url',
        'overview',
        'tmdb_checked_at',
        'added_at',
        'origin',
        'in_collection',
        'source_title',
        'source_year',
        'edited_at',
    ];

    protected $casts = [
        'added_at' => 'datetime',
        'tmdb_checked_at' => 'datetime',
        'edited_at' => 'datetime',
        'in_collection' => 'boolean',
        'source_year' => 'integer',
        'year' => 'integer',
    ];

    public const ORIGIN_SHEET = 'sheet';
    public const ORIGIN_PASTE = 'paste';
    public const ORIGIN_APP = 'app';

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    /** The suggestions that put this title in the library ("Added"). */
    public function suggestions(): HasMany
    {
        return $this->hasMany(MediaRequest::class, 'fulfilled_library_item_id');
    }

    /** Added in the app and not kept yet: what TBW lists under "Seen". */
    public function scopeAwaitingKeep(Builder $query): Builder
    {
        return $query->where('origin', self::ORIGIN_APP)->where('in_collection', false);
    }

    public function isAwaitingKeep(): bool
    {
        return $this->origin === self::ORIGIN_APP && ! $this->in_collection;
    }

    /**
     * The poster at a TMDB size: w185 for list rows (a w500 is ~5x the
     * bytes and kept rows blank for seconds), w780 for the title page.
     * Posters are stored at w500.
     */
    public function poster(string $size): ?string
    {
        return $this->poster_url ? str_replace('/w500/', "/{$size}/", $this->poster_url) : null;
    }

    /**
     * TMDB details already known for this title in any library, so a title
     * many hosts share is looked up once. Null when none is known.
     */
    public static function knownDetails(string $title, ?int $year, string $type): ?array
    {
        $known = static::query()
            ->where('title', $title)
            ->where('year', $year)
            ->where('type', $type)
            ->whereNotNull('tmdb_id')
            ->first(['tmdb_id', 'poster_url', 'overview']);

        return $known ? [
            'tmdb_id' => $known->tmdb_id,
            'poster_url' => $known->poster_url,
            'overview' => $known->overview,
            'tmdb_checked_at' => now(),
        ] : null;
    }

    /**
     * Simple LIKE-based search over title + sort_title. The library is
     * small (<5k rows per host in practice), so a proper FTS index is
     * overkill for v1. Upgrade to FTS5 if it ever matters.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $q) use ($needle) {
            $q->where('title', 'like', $needle)
                ->orWhere('sort_title', 'like', $needle);
        });
    }
}
