<?php

namespace App\Support;

use App\Models\LibraryItem;
use App\Models\MediaRequest;

/**
 * TBW: everything friends have asked a host to watch. New suggestions,
 * In progress, and titles the host added through a suggestion in the app that
 * aren't in their collection yet.
 */
class Tbw
{
    /** @return array{new: int, up_next: int, seen: int, total: int} */
    public static function counts(int $hostId): array
    {
        $new = MediaRequest::query()->where('host_id', $hostId)->awaitingHost()->count();
        $upNext = MediaRequest::query()->where('host_id', $hostId)->upNext()->count();
        $seen = LibraryItem::query()->where('host_id', $hostId)->awaitingKeep()->count();

        return ['new' => $new, 'up_next' => $upNext, 'seen' => $seen, 'total' => $new + $upNext + $seen];
    }
}
