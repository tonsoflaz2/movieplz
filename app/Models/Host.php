<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Host extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'library_sheet_url'];

    protected $casts = [
        'library_synced_at' => 'datetime',
        'library_mirror' => 'boolean',
        'library_mirror_held' => 'integer',
    ];

    /** The longest library name. */
    public const NAME_MAX = 60;

    /** What a library is called until its host names it: "Christian's library". */
    public static function defaultName(string $person): string
    {
        return $person."'s library";
    }

    /**
     * A library name as typed: trimmed, with runs of spaces made one.
     * Empty when nothing was typed.
     */
    public static function cleanName(?string $typed): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $typed));
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class);
    }

    public function libraryItems(): HasMany
    {
        return $this->hasMany(LibraryItem::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(MediaRequest::class);
    }

    public function hostClient()
    {
        return $this->clients()->where('role', 'host')->first();
    }
}
