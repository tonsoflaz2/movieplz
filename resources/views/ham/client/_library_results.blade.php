{{--
    Library results — rows only, with infinite scroll.

    Rendered inside #library-results by library.blade.php, returned on
    its own by GET /library/search.ham for a search (swapped into
    #library-results) and for the next page (swapped over the
    #load-more-N sentinel). It must never carry the heading, filters or
    padding: each page lands inside the previous one's list.

    Variables:
      $items  — Collection of LibraryItem (page of 20)
      $query  — search term (may be empty)
      $type   — 'film', 'series', or null
      $offset — next page offset
--}}
@if ($items->isEmpty() && ($offset ?? 20) <= 20)
    <block style="p-15 bg-black bg-opacity-10 rounded-lg">
        <p style="text-white font-base font-bold mb-5">
            @if ($query !== '')
                No matches for "{{ $query }}"
            @else
                No titles yet
            @endif
        </p>
        <p style="text-red-100 font-sm mb-10">
            @if ($query !== '')
                Suggest it to your host — they'll see it right away.
            @elseif (($current_client->role ?? 'client') === 'host')
                Add titles from a Google Sheet or paste a list.
            @else
                Your host hasn't added any titles yet. You can still suggest something.
            @endif
        </p>
        @if ($query === '' && ($current_client->role ?? 'client') === 'host')
            <button action="navigate:/host/library-source.ham" title="Add titles" style="filled #1F2937">
                Add titles
            </button>
        @elseif ($query === '')
            <button action="navigate:/requests/new.ham" title="New Suggestion" style="filled #1F2937">
                Suggest something
            </button>
        @endif
        @if ($query !== '' && ($current_client->role ?? 'client') === 'client')
            <button
                action="navigate:/requests/new.ham?title={{ urlencode($query) }}"
                title="Suggest"
                style="filled #1F2937">
                Suggest "{{ $query }}"
            </button>
        @endif
    </block>
@else
    @foreach ($items as $item)
        <block style="bg-black bg-opacity-10 rounded-lg mb-8 p-0"
               action="navigate:/library/{{ $item->id }}.ham" title="{{ $item->title }}">
            <flex column_widths="1 4">
                @if ($item->poster_url)
                    <img id="poster_{{ $item->id }}_{{ substr(md5($item->poster_url), 0, 6) }}" persist src="{{ $item->poster('w185') }}" width="60" height="90" fit="cover" style="rounded-l-lg"/>
                @else
                    <block style="bg-black bg-opacity-20 rounded-l-lg h-90 w-60">
                        <center>
                            <icon name="{{ $item->type === 'series' ? 'tv' : 'movie' }} white size-24"></icon>
                        </center>
                    </block>
                @endif
                <block style="px-12 py-10">
                    <p style="font-base font-bold">{{ $item->title }}</p>
                    <p style="text-red-100 font-sm mt-2">{{ ucfirst($item->type) }}{{ $item->year ? ' · '.$item->year : '' }}</p>
                    @if ($item->overview)
                        <p style="text-red-200 font-xs mt-5">{{ Str::limit($item->overview, 90) }}</p>
                    @endif
                </block>
            </flex>
        </block>
    @endforeach

    {{-- Infinite scroll: load the next 20 when this sentinel is revealed --}}
    @if ($items->count() >= 20)
        @php
            $nextOffset = $offset ?? 20;
            $params = ['offset' => $nextOffset];
            if (!empty($query)) $params['q'] = $query;
            if (!empty($type)) $params['type'] = $type;
            $nextUrl = '/library/search.ham?' . http_build_query($params);
        @endphp
        <block
            id="load-more-{{ $nextOffset }}"
            action="{{ $nextUrl }}"
            target="#load-more-{{ $nextOffset }}"
            swap="outer"
            trigger="revealed">
            <center style="p-15">
                <indicator type="circular" color="white"></indicator>
            </center>
        </block>
    @endif
@endif
