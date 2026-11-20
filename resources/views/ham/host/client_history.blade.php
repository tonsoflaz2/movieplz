{{--
    GET /host/clients/{id}.ham — one friend's suggestions, pushed from the
    Clients list. Grouped New, In progress, Added, Declined (newest first in
    each); the rows are the inbox's own, so pending ones can be answered
    here, and each answer swaps its row in place.

    Variables:
      $friend — Client (role='client')
      $total  — int, all their suggestions
      $groups — ['New' => Collection, 'In progress' => …, 'Added' => …, 'Declined' => …]
--}}
@php
    $summary = array_filter([
        $friend->host_nickname ? 'Calls themselves '.$friend->display_name : null,
        $total === 1 ? '1 suggestion' : $total.' suggestions',
        $total > 0 ? $groups['Added']->count().' added' : null,
        $total > 0 ? $groups['Declined']->count().' declined' : null,
        'joined '.$friend->created_at->diffForHumans(),
    ]);
@endphp
<block style="p-20">
    <h1 style="font-2xl font-bold mb-5">{{ $friend->nameForHost() }}</h1>
    <p style="text-red-100 font-sm mb-15">{{ implode(' · ', $summary) }}</p>

    @if ($total === 0)
        <block style="p-15 bg-black bg-opacity-10 rounded-lg">
            <p style="text-red-100 font-base">{{ $friend->nameForHost() }} hasn't suggested anything yet.</p>
        </block>
    @else
        @foreach ($groups as $heading => $requests)
            @continue($requests->isEmpty())
            <p style="font-lg font-bold mt-10 mb-10">{{ $heading }} · {{ $requests->count() }}</p>
            @foreach ($requests as $req)
                @include('ham.host.partials._request_row', ['request' => $req])
            @endforeach
        @endforeach
    @endif
</block>
