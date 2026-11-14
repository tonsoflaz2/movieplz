{{--
    Picking a title's poster and description: TMDB's likeliest matches
    for its (new) name, best first. Swapped over the title's box after an
    edit that changed it, or from "Change poster".

    Variables:
      $item       — LibraryItem (already saved with its new name)
      $in, $box   — as in _title_edit
      $candidates — list of ['tmdb_id', 'media', 'title', 'year', 'poster_url', 'overview'],
                    [] when TMDB has none, null when TMDB couldn't be asked
      $notice     — optional
--}}
<block id="{{ $box }}" style="bg-black bg-opacity-10 p-15 rounded-lg mb-10">
    <p style="font-base font-bold mb-5">Which one is {{ $item->title }}{{ $item->year ? ' ('.$item->year.')' : '' }}?</p>

    @if (!empty($notice))
        <p style="text-white font-sm mb-10">{{ $notice }}</p>
    @endif

    @if ($candidates === null)
        <p style="text-red-100 font-sm mb-10">Couldn't reach The Movie Database. The poster will be looked up later.</p>
    @elseif ($candidates === [])
        <p style="text-red-100 font-sm mb-10">No matches on The Movie Database for that name.</p>
    @else
        <p style="text-red-100 font-sm mb-10">Pick the right one for its poster and description.</p>
        @foreach ($candidates as $i => $c)
            <block style="bg-black bg-opacity-15 rounded-lg mb-8 p-0">
                <flex column_widths="1 4">
                    @if ($c['poster_url'])
                        <img src="{{ str_replace('/w500/', '/w185/', $c['poster_url']) }}" width="60" height="90" fit="cover" style="rounded-l-lg"/>
                    @else
                        <block style="bg-black bg-opacity-20 rounded-l-lg h-90 w-60">
                            <center>
                                <icon name="{{ $c['media'] === 'tv' ? 'tv' : 'movie' }} white size-24"></icon>
                            </center>
                        </block>
                    @endif
                    <block style="px-12 py-10">
                        <p style="font-base font-bold">{{ $c['title'] }}</p>
                        <p style="text-red-100 font-sm mt-2">{{ $c['media'] === 'tv' ? 'Series' : 'Film' }}{{ $c['year'] ? ' · '.$c['year'] : '' }}</p>
                        @if ($c['overview'])
                            <p style="text-red-200 font-xs mt-5">{{ Str::limit($c['overview'], 90) }}</p>
                        @endif
                        <button
                            action="/host/library/{{ $item->id }}/match?in={{ $in }}&pick={{ $i }}"
                            method="POST"
                            target="#{{ $box }}"
                            swap="outer"
                            style="filled #1F2937 sm mt-8">
                            This one
                        </button>
                    </block>
                </flex>
            </block>
        @endforeach
    @endif

    <inline-content spacing="8 8" style="mt-5">
        @if ($candidates)
            <button
                action="/host/library/{{ $item->id }}/match?in={{ $in }}&pick=none"
                method="POST"
                target="#{{ $box }}"
                swap="outer"
                style="outlined #FECACA sm">
                No poster
            </button>
        @endif
        <button
            action="/host/library/{{ $item->id }}/view.ham?in={{ $in }}"
            target="#{{ $box }}"
            swap="outer"
            style="plain #FECACA sm">
            {{ $candidates ? 'Decide later' : 'Done' }}
        </button>
    </inline-content>
</block>
