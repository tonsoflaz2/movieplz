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
