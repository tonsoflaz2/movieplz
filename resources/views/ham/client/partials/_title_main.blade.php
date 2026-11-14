{{--
    A title page's poster, name and description, as one box (#title-{id})
    the host's Edit swaps in place (?in=page): the edit form, the TMDB
    matches, then this box again with the saved title.

    Variables:
      $item           — LibraryItem
      $current_client — the signed-in person (the view composer sets it)
--}}
<block id="title-{{ $item->id }}" style="p-0">
    @if ($item->poster_url)
        <img id="poster_{{ $item->id }}_{{ substr(md5($item->poster_url), 0, 6) }}" persist src="{{ $item->poster('w780') }}" width="800" height="500" fit="cover" style="rounded-0"/>
    @else
        <block style="h-160 bg-black bg-opacity-10">
            <center>
                <icon name="{{ $item->type === 'series' ? 'tv' : 'movie' }} white size-48"></icon>
            </center>
        </block>
    @endif

    <block style="px-20 pt-20">
        <h1 style="font-2xl font-bold mb-5">{{ $item->title }}</h1>
        <p style="text-red-100 font-base mb-15">
            {{ ucfirst($item->type) }}{{ $item->year ? ' · '.$item->year : '' }}
        </p>

        @if ($item->overview)
            <p style="text-red-100 font-base mb-15">{{ $item->overview }}</p>
        @endif

        @if ($current_client?->isHost())
            <inline-content spacing="8 8" style="mb-10">
                <button
                    action="/host/library/{{ $item->id }}/edit.ham?in=page"
                    target="#title-{{ $item->id }}"
                    swap="outer"
                    style="outlined #FECACA sm">
                    Edit
                </button>
                @if ($item->isAwaitingKeep())
                    <button
                        action="/host/library/{{ $item->id }}/keep"
                        method="POST"
                        target="#title-{{ $item->id }}"
                        swap="outer"
                        style="filled green sm">
                        Add to my collection
                    </button>
                @endif
            </inline-content>
            @if ($item->isAwaitingKeep())
                <p style="text-red-100 font-xs mb-10">Added in the app from a suggestion; not in your collection yet.</p>
            @endif
        @endif
    </block>
</block>
