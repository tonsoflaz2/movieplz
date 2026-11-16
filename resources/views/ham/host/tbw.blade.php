{{--
    GET /host/tbw.ham — TBW, pushed from the Dashboard: everything friends
    have asked this host to watch, by status.

      New       — suggestions not answered yet (the inbox's rows, with Edit)
      In progress — "In progress"
      Added       — titles added through a suggestion ("Added") that
                    aren't in the collection yet: Edit · Add to my
                    collection · Remove

    Variables:
      $new, $upNext — Collections of MediaRequest (client loaded)
      $seen         — Collection of LibraryItem awaiting keep (suggestions.client loaded)
      $forSheet     — Collection of LibraryItem kept in the app, not yet in
                      the linked Google Sheet (empty without a sheet)
--}}
<block style="p-20">
    <h1 style="font-2xl font-bold mb-5">TBW</h1>
    <p style="text-red-100 font-base mb-20">Everything your friends have asked you to watch.</p>

    @if ($new->isEmpty() && $upNext->isEmpty() && $seen->isEmpty())
        <block style="p-15 bg-black bg-opacity-10 rounded-lg mb-15">
            <p style="text-red-100 font-base">Nothing to watch yet. When friends suggest something, it shows up here.</p>
        </block>
    @endif

    @if ($new->isNotEmpty())
        <p style="font-lg font-bold mb-10">New · {{ $new->count() }}</p>
        @foreach ($new as $req)
            @include('ham.host.partials._request_row', ['request' => $req])
        @endforeach
    @endif

    @if ($upNext->isNotEmpty())
        <p style="font-lg font-bold mt-10 mb-10">In progress · {{ $upNext->count() }}</p>
        @foreach ($upNext as $req)
            @include('ham.host.partials._request_row', ['request' => $req])
        @endforeach
    @endif

    @if ($seen->isNotEmpty())
        <p style="font-lg font-bold mt-10 mb-5">Added, not in your collection yet · {{ $seen->count() }}</p>
        <p style="text-red-100 font-sm mb-10">
            You added these through a suggestion, in the app. Fix the name if needed, then add them to your collection.
        </p>
        @foreach ($seen as $item)
            @include('ham.host.partials._tbw_item', ['item' => $item])
        @endforeach
    @endif

    @if ($forSheet->isNotEmpty())
        {{-- MoviePlz reads the sheet but can't write to it: these are the
             titles kept in the app, as lines to paste into it. A title the
             sheet lists is picked up by the next sync and leaves this list. --}}
        @php
            $lines = $forSheet->map(fn ($i) => $i->title.', '.ucfirst($i->type).($i->year ? ', '.$i->year : ''))->implode("\n");
        @endphp
        <block var-sheet="{{ $lines }}" var-copied="false" style="bg-black bg-opacity-10 p-15 rounded-lg mt-15 mb-15">
            <p style="font-base font-bold mb-5">Not in your Google Sheet yet · {{ $forSheet->count() }}</p>
            <p style="text-red-100 font-sm mb-10">
                These are in your collection here. MoviePlz can't write to your sheet, so copy them and paste them in as new rows.
            </p>
            @foreach ($forSheet as $i)
                <p style="text-white font-sm">{{ $i->title }}{{ $i->year ? ' ('.$i->year.')' : '' }}</p>
            @endforeach
            <button @click="clipboard:copy:$sheet; $copied = true" style="outlined #FECACA sm mt-10">
                Copy titles for my sheet
            </button>
            <p @@show="$copied" style="text-red-100 font-xs mt-5">Copied, one title per line.</p>
        </block>
    @endif

    <block style="mb-150"></block>
</block>
