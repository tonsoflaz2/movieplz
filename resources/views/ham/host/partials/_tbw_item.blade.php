{{--
    A title in TBW's "Seen, not in your collection yet": seen through a
    suggestion and added in the app. Edit · Add to my collection · Remove.

    The list's row, and the answer to keep, edit (when done), match and
    view (?in=row), swapped over #tbw-item-{id}. Once kept, it stays in
    place as "In your collection".

    Variable: $item — LibraryItem with suggestions.client loaded
--}}
@php
    $from = $item->suggestions->map(fn ($s) => $s->client?->display_name)->filter()->unique()->implode(', ');
    $seenAt = $item->suggestions->max('fulfilled_at') ?? $item->created_at;
@endphp
<block id="tbw-item-{{ $item->id }}" var-removing="false" style="bg-black bg-opacity-10 rounded-lg mb-10 p-0">
    <flex column_widths="1 4">
        @if ($item->poster_url)
            <img id="poster_{{ $item->id }}_{{ substr(md5($item->poster_url), 0, 6) }}" src="{{ $item->poster('w185') }}" width="60" height="90" fit="cover" style="rounded-l-lg"/>
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
            <p style="text-red-100 font-xs mt-2">
                {{ $from !== '' ? 'Suggested by '.$from.' · ' : '' }}seen {{ $seenAt->lt(now()->subMinute()) ? $seenAt->diffForHumans() : 'just now' }}
            </p>
        </block>
    </flex>

    <block style="px-12 pb-12">
        @if ($item->in_collection)
            <row spacing="8" style="mt-5">
                <icon name="check_circle green-300"></icon>
                <p style="text-green-300 font-sm">In your collection</p>
            </row>
        @else
            <block @@show="!$removing">
                <inline-content spacing="8 8" style="mt-5">
                    <button
                        action="/host/library/{{ $item->id }}/edit.ham?in=row"
                        target="#tbw-item-{{ $item->id }}"
                        swap="outer"
                        style="outlined #FECACA sm">
                        Edit
                    </button>
                    <button
                        action="/host/library/{{ $item->id }}/keep"
                        method="POST"
                        target="#tbw-item-{{ $item->id }}"
                        swap="outer"
                        style="filled green sm">
                        Add to my collection
                    </button>
                    <button @click="$removing = true" style="plain #FECACA sm">
                        Remove
                    </button>
                </inline-content>
            </block>
            <block @@show="$removing" style="mt-5">
                <p style="text-white font-sm mb-8">Remove {{ $item->title }} from your library? The suggestion still says you saw it.</p>
                <inline-content spacing="8 8">
                    <button
                        action="/host/library/{{ $item->id }}/remove"
                        method="POST"
                        target="#tbw-item-{{ $item->id }}"
                        swap="outer"
                        style="filled #1F2937 sm">
                        Yes, remove
                    </button>
                    <button @click="$removing = false" style="outlined #FECACA sm">
                        Cancel
                    </button>
                </inline-content>
            </block>
        @endif
    </block>
</block>
