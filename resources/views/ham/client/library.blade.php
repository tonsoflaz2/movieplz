{{--
    GET /library.ham — full library browse, partial swapped into #main.

    The heading, type filter and search bar live here, outside
    #library-results, so a search or the next page never re-renders them
    (re-rendering the search bar would drop the keyboard mid-word).
    The filter buttons swap this whole screen into #main so their
    selected state and the search bar's type follow along.

    Variables:
      $current_client — Client
      $items          — Collection of LibraryItem
      $query          — current search term (empty initially)
      $type           — 'film', 'series', or null (all)
--}}
@php
    $hostId = $current_client->host_id;
    $allCount = App\Models\LibraryItem::where('host_id', $hostId)->count();
    $filmCount = App\Models\LibraryItem::where('host_id', $hostId)->where('type', 'film')->count();
    $seriesCount = App\Models\LibraryItem::where('host_id', $hostId)->where('type', 'series')->count();
@endphp
<block style="p-20">
    <h1 style="font-2xl font-bold mb-15">Library</h1>

    <inline-content spacing="8 8" style="mb-15">
        <button action="/library.ham" target="#main" swap="inner"
                style="{{ empty($type) ? 'filled #1F2937' : 'outlined #FECACA' }} sm">
            All ({{ $allCount }})
        </button>
        <button action="/library.ham?type=film" target="#main" swap="inner"
                style="{{ ($type ?? '') === 'film' ? 'filled #1F2937' : 'outlined #FECACA' }} sm">
            Films ({{ $filmCount }})
        </button>
        <button action="/library.ham?type=series" target="#main" swap="inner"
                style="{{ ($type ?? '') === 'series' ? 'filled #1F2937' : 'outlined #FECACA' }} sm">
            Series ({{ $seriesCount }})
        </button>
    </inline-content>

    <form>
        {{-- An <input>, not <search-bar>: the engine's search-bar is a bare
             field that ignores name/action/trigger. The <form> is what
             makes the GET carry q= (a lone input sends no value). --}}
        <input
            name="q"
            type="text"
            variant="plain"
            icon="search"
            enterkeyhint="search"
            placeholder="Search {{ $type ? ($type === 'film' ? 'films' : 'series') : 'titles' }}…"
            action="/library/search.ham{{ !empty($type) ? '?type='.$type : '' }}"
            method="GET"
            target="#library-results"
            swap="inner"
            trigger="change:delay-300ms"
            style="h-48 rounded-full bg-#B91C1C text-white mb-15"/>
    </form>

    <block id="library-results">
        @include('ham.client._library_results')
    </block>
</block>
