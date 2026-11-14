{{--
    Tiny polling partial — host pending-requests count.

    Initial render: included directly inside the home body's box.
    Polling: GET /host/unread.ham replaces this partial every 30s.

    Variable: $count — int
--}}
@if ($count === 0)
    <p style="text-white font-base font-bold">Inbox is empty</p>
@elseif ($count === 1)
    <p style="text-white font-base font-bold">1 new suggestion</p>
@else
    <p style="text-white font-base font-bold">{{ $count }} new suggestions</p>
@endif
