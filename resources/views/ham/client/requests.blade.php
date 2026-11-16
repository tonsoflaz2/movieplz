{{--
    GET /requests.ham — client's request history, partial into #main.

    Visiting this view marks all fulfilled-but-unseen requests as seen
    (handled in Client\RequestController::index) so the unread banner
    clears on next home render.

    Variables:
      $current_client — Client
      $up_next, $fulfilled, $pending, $declined — Collections of MediaRequest
                        (In progress, Added, Waiting, Declined)

    The host's reply, when they wrote one, shows under each suggestion.
--}}
<block style="p-20">
    <h1 style="font-2xl font-bold mb-15">Your suggestions</h1>

    @if ($pending->isEmpty() && $up_next->isEmpty() && $fulfilled->isEmpty() && $declined->isEmpty())
        <block style="p-15 bg-black bg-opacity-10 rounded-lg">
            <p style="text-red-100 font-base">You haven't suggested anything yet.</p>
            <button
                action="navigate:/requests/new.ham"
                title="New Suggestion"
                style="filled #1F2937 mt-10">
                Suggest something
            </button>
        </block>
    @else
        @if ($up_next->isNotEmpty())
            <p style="font-lg font-bold mb-10 mt-10">In progress ({{ $up_next->count() }})</p>
            @foreach ($up_next as $req)
                <block style="bg-black bg-opacity-10 p-15 rounded-lg mb-10">
                    <row spacing="10">
                        <icon name="autorenew white"></icon>
                        <p style="font-base font-bold">{{ $req->title }}</p>
                    </row>
                    <p style="text-red-100 font-sm mt-5">{{ 'In progress since '.$req->up_next_at->diffForHumans() }}{{ $req->year ? ' · '.$req->year : '' }}</p>
                    @include('ham.client.partials._host_reply', ['req' => $req])
                </block>
            @endforeach
        @endif
        @if ($fulfilled->isNotEmpty())
            <p style="font-lg font-bold mb-10 mt-10">Added ({{ $fulfilled->count() }})</p>
            @foreach ($fulfilled as $req)
                <block style="bg-black bg-opacity-10 p-15 rounded-lg mb-10">
                    <row spacing="10">
                        <icon name="check_circle green-300"></icon>
                        <p style="font-base font-bold">{{ $req->title }}</p>
                    </row>
                    <p style="text-red-100 font-sm mt-5">{{ $req->label().' '.($req->fulfilled_at?->diffForHumans() ?? 'recently') }}{{ $req->wasAddedToLibrary() ? ' · in the library' : '' }}{{ $req->year ? ' · '.$req->year : '' }}</p>
                    @include('ham.client.partials._host_reply', ['req' => $req])
                </block>
            @endforeach
        @endif
        @if ($pending->isNotEmpty())
            <p style="font-lg font-bold mb-10 mt-10">Waiting ({{ $pending->count() }})</p>
            @foreach ($pending as $req)
                <block style="bg-black bg-opacity-10 p-15 rounded-lg mb-10">
                    <row spacing="10">
                        <icon name="schedule red-100"></icon>
                        <p style="font-base font-bold">{{ $req->title }}</p>
                    </row>
                    <p style="text-red-100 font-sm mt-5">{{ 'Suggested '.$req->created_at->diffForHumans() }}{{ $req->year ? ' · '.$req->year : '' }}</p>
                    @include('ham.client.partials._host_reply', ['req' => $req])
                </block>
            @endforeach
        @endif
        @if ($declined->isNotEmpty())
            <p style="font-lg font-bold mb-10 mt-10">Declined ({{ $declined->count() }})</p>
            @foreach ($declined as $req)
                <block style="bg-black bg-opacity-10 p-15 rounded-lg mb-10">
                    <row spacing="10">
                        <icon name="do_not_disturb_on red-200"></icon>
                        <p style="font-base font-bold">{{ $req->title }}</p>
                    </row>
                    <p style="text-red-100 font-sm mt-5">{{ 'Declined' }}{{ $req->year ? ' · '.$req->year : '' }}</p>
                    @include('ham.client.partials._host_reply', ['req' => $req])
                </block>
            @endforeach
        @endif
    @endif
</block>
