{{--
    Host dashboard body. Embedded by ham/main.blade.php on cold launch,
    and also returned by GET /host/home.ham on bottom-nav tap.

    Variables:
      $current_client      — Client (role='host')
      $pending_count       — int
      $latest_pending      — Collection of MediaRequest (latest 5)
      $library_total       — int
      $film_count          — int
      $series_count        — int
      $active_client_count — int
      $tbw                 — ['new', 'up_next', 'seen', 'total'] (App\Support\Tbw::counts)
--}}
@php
    $pending_count ??= 0;
    $latest_pending ??= collect();
    $library_total ??= 0;
    $film_count ??= 0;
    $series_count ??= 0;
    $active_client_count ??= 0;
    $active_invite ??= null;
    $tbw ??= ['new' => 0, 'up_next' => 0, 'seen' => 0, 'total' => 0];
@endphp
<block style="p-20">
    @include('ham.host.partials._library_name', ['host' => $current_client?->host])

    {{-- Pending requests summary. Outer box navigates on tap; the
         nested #host-unread box polls every 30s and swaps its own
         contents with the fresh count text. Click bubbles to the
         outer box's default click trigger, so tapping anywhere in
         this card takes you to the requests inbox. --}}
    <block action="navigate:/host/requests.ham" title="Suggestions"
        style="bg-black bg-opacity-10 p-15 rounded-lg mb-15">
        <row spacing="10">
            <icon name="inbox white"></icon>
            <block
                id="host-unread"
                trigger="every 30s"
                action="/host/unread.ham"
                target="#host-unread"
                swap="inner">
                @include('ham.host.partials._unread_count', ['count' => $pending_count])
            </block>
        </row>
        @if ($latest_pending->isNotEmpty())
            <p style="text-red-100 font-sm mt-5">Tap to review</p>
        @endif
    </block>

    {{-- TBW: everything friends have asked to watch, by status. --}}
    <block action="navigate:/host/tbw.ham" title="TBW"
        style="bg-black bg-opacity-10 p-15 rounded-lg mb-15">
        <row spacing="10">
            <icon name="bookmarks white"></icon>
            <p style="font-base font-bold">TBW · {{ $tbw['total'] }}</p>
        </row>
        <p style="text-red-100 font-sm mt-5">
            @if ($tbw['total'] === 0)
                Everything friends ask you to watch shows up here.
            @else
                {{ $tbw['new'] }} new · {{ $tbw['up_next'] }} in progress · {{ $tbw['seen'] }} added, not in your collection
            @endif
        </p>
    </block>

    {{-- Library stats. Tapping them opens the Library tab: the app restarts
         on it (?open=, as a tapped notification does), so the tab bar
         shows Library selected. "Add titles" sits outside the tappable
         part, so it never restarts the app. --}}
    <block style="bg-black bg-opacity-10 p-15 rounded-lg mb-15">
        <block action="app:/app.ham?open=/library.ham">
            <row spacing="10" style="mb-5">
                <icon name="movie white"></icon>
                <p style="font-base font-bold">Library</p>
                <spacer/>
                <icon name="chevron_right red-100"></icon>
            </row>
            <p style="text-white font-sm">
                {{ $library_total }} total ·
                {{ $film_count }} {{ Str::plural('film', $film_count) }} ·
                {{ $series_count }} {{ Str::plural('series', $series_count) }}
            </p>
            <p style="text-red-100 font-sm mt-5">
                {{ $active_client_count }} {{ Str::plural('friend', $active_client_count) }} connected
            </p>
        </block>
        <button action="navigate:/host/library-source.ham" title="Add titles"
            style="{{ $library_total === 0 ? 'filled #1F2937' : 'outlined #FECACA' }} sm mt-10">
            {{ $library_total === 0 ? 'Add your titles' : 'Add titles' }}
        </button>
    </block>

    {{-- Invite code — one at a time --}}
    <block style="mb-15">
        <p style="font-base font-bold mb-10">Invite a friend</p>
        <block id="invite-code-display">
            @if (!empty($active_invite))
                @include('ham.host.partials._invite_code', ['invite' => $active_invite])
            @else
                <p style="text-red-100 font-sm mb-10">
                    Each friend needs their own code. Codes are single-use
                    and expire after 10 minutes.
                </p>
            @endif
        </block>
        <button
            action="/host/invite-code"
            method="POST"
            target="#invite-code-display"
            swap="inner"
            style="outlined #FECACA mt-10">
            Generate new code
        </button>
    </block>

    {{-- Navigation shortcuts --}}
    <button action="navigate:/host/requests.ham" title="Suggestions" style="filled #1F2937 mb-10">
        View all suggestions
    </button>

    @include('ham.partials._theme_button')

    @include('ham.partials._disconnect', [
        'kept' => 'Your library, friends and suggestions are kept, and you can reconnect with a code.',
    ])

    @include('ham.partials._account_actions', [
        'label' => 'Delete my library',
        'question' => 'Delete '.($current_client?->host?->name ?? 'your library').'? Its titles, friends and suggestions are deleted. This cannot be undone.',
        'confirm' => 'Yes, delete it',
        'action' => '/host/delete-library',
    ])
</block>
