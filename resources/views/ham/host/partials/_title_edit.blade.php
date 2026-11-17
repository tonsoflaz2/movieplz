{{--
    Editing a library title, swapped over its box: a TBW row (?in=row,
    #tbw-item-{id}) or the title page (?in=page, #title-{id}).
    GET /host/library/{id}/edit.ham, and POST …/edit again on an error.

    Variables:
      $item           — LibraryItem
      $in             — "row" | "page"
      $box            — the box's id
      $title, $year, $type — the fields
      $error          — optional
--}}
<block id="{{ $box }}" style="bg-black bg-opacity-10 p-15 rounded-lg mb-10">
    <p style="font-base font-bold mb-10">Edit {{ $item->title }}</p>

    @if ($item->origin === 'sheet' && $item->source_title && (\App\Support\SortTitle::display($item->source_title) !== $item->title || $item->source_year !== $item->year))
        <p style="text-red-100 font-xs mb-10">
            Edited in MoviePlz. Your Google Sheet still says "{{ $item->source_title }}{{ $item->source_year ? ' ('.$item->source_year.')' : '' }}".
        </p>
    @elseif ($item->origin === 'sheet' && $item->source_title)
        <p style="text-red-100 font-xs mb-10">From your Google Sheet. Changes stay in MoviePlz; the sheet isn't changed.</p>
    @endif

    <form>
        <input
            name="title"
            type="text"
            label="Title"
            icon="movie"
            max-length="200"
            value="{{ $title }}"
            style="mb-10"/>
        <input
            name="year"
            type="number"
            label="Year"
            placeholder="e.g. 1995"
            icon="calendar_today"
            value="{{ $year ?? '' }}"
            style="mb-10"/>
        <select name="type" label="Film or series" icon="category" style="mb-10">
            <option value="film" selected="{{ $type !== 'series' ? 'true' : 'false' }}">Film</option>
            <option value="series" selected="{{ $type === 'series' ? 'true' : 'false' }}">Series</option>
        </select>

        @if (!empty($error))
            <block style="bg-black bg-opacity-15 p-10 rounded mb-10">
                <p style="text-white font-sm">{{ $error }}</p>
            </block>
        @endif

        <inline-content spacing="8 8">
            <button
                action="/host/library/{{ $item->id }}/edit?in={{ $in }}"
                method="POST"
                target="#{{ $box }}"
                swap="outer"
                style="filled #1F2937 sm">
                Save
            </button>
            @if ($in === 'row' && $item->isAwaitingKeep())
                <button
                    action="/host/library/{{ $item->id }}/edit?in={{ $in }}&keep=1"
                    method="POST"
                    target="#{{ $box }}"
                    swap="outer"
                    style="filled green sm">
                    Save and add to my collection
                </button>
            @endif
            <button
                action="/host/library/{{ $item->id }}/view.ham?in={{ $in }}"
                target="#{{ $box }}"
                swap="outer"
                style="outlined #FECACA sm">
                Cancel
            </button>
        </inline-content>
    </form>

    <button
        action="/host/library/{{ $item->id }}/matches.ham?in={{ $in }}"
        target="#{{ $box }}"
        swap="outer"
        style="plain #FECACA sm mt-10">
        Change poster
    </button>
</block>
