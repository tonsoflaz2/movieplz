{{--
    Client home body — embedded by ham/main.blade.php on cold launch,
    also returned by GET /home.ham on bottom-nav tap.

    Available variables:
      $current_client   — Client model (authed)
      $recent_requests  — Collection of MediaRequest (optional, home view only)
      $unread_fulfilled — int count of fulfilled-but-unseen requests (optional)
--}}
@php
    $recent_requests ??= collect();
    $unread_fulfilled ??= 0;
@endphp
<block style="p-20">
    <h1 style="font-2xl font-bold mb-5">
        Hi{{ $current_client ? ', '.$current_client->display_name : '' }}
    </h1>
    <p style="text-red-100 font-base mb-20">
        {{ $current_client?->host?->name ?? 'MoviePlz' }}
    </p>

    {{-- Unread-fulfilled banner. The outer polling box re-fetches the
         banner partial every 30s; the partial renders nothing if the
         count is zero, or a tappable banner if the count is > 0. --}}
    <block
        id="client-unread"
        trigger="every 30s"
        action="/home/unread.ham"
        target="#client-unread"
        swap="inner">
        @include('ham.client.partials._unread_count', ['count' => $unread_fulfilled ?? 0])
    </block>

    <block style="bg-black bg-opacity-10 p-15 rounded-lg mb-15">
        <row spacing="10">
            <icon name="search red-100"></icon>
            <p style="text-white font-base font-bold">Want something?</p>
        </row>
        <p style="text-red-100 font-sm mb-10 mt-5">
            Search the library first. If it's not there, suggest it.
        </p>
        <button action="navigate:/library.ham" title="Library" style="outlined #FECACA mr-10 mb-10">
            Browse library
        </button>
        <button action="navigate:/requests/new.ham" title="New Suggestion" style="filled #1F2937">
            Suggest something
        </button>
    </block>

    @if (!empty($recent_requests) && $recent_requests->isNotEmpty())
        <p style="font-lg font-bold mb-5 mt-10">Your recent suggestions</p>
        @foreach ($recent_requests as $req)
            <list-tile
                icon="{{ ['Seen' => 'check_circle', 'Passed' => 'do_not_disturb_on', 'Up next' => 'bookmark'][$req->label()] ?? 'schedule' }}"
                subtitle="{{ $req->label() }}{{ $req->year ? ' · '.$req->year : '' }}">
                {{ $req->title }}
            </list-tile>
        @endforeach
        <button action="navigate:/requests.ham" title="Suggestions" style="plain #FECACA mt-5">
            See all suggestions
        </button>
    @endif

    @include('ham.partials._disconnect', [
        'kept' => 'Your suggestions are kept, and you can reconnect with a code.',
    ])

    @include('ham.partials._account_actions', [
        'label' => 'Leave this library',
        'question' => "Leave ".($current_client?->host?->name ?? 'this library')."? Your name and suggestions are deleted, and you'll need a new code to come back.",
        'confirm' => 'Yes, leave',
        'action' => '/account/leave',
    ])
</block>
