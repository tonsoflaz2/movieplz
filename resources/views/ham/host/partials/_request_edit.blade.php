{{--
    Editing a pending suggestion (TBW's New and In progress, and the inbox),
    swapped over its row, #request-{id}. Saving answers with the row;
    the friend sees the corrected title in their suggestions.

    Variables:
      $request — MediaRequest (client loaded)
      $title, $year, $type — the fields ($type null: either)
      $error   — optional
--}}
<block id="request-{{ $request->id }}" style="bg-black bg-opacity-10 p-15 rounded-lg mb-10">
    <p style="font-base font-bold mb-5">Edit {{ $request->client?->display_name ? $request->client->display_name."'s" : 'this' }} suggestion</p>
    <p style="text-red-100 font-xs mb-10">Fix a typo, the year or the name. {{ $request->client?->display_name ?? 'Your friend' }} sees the change.</p>

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
            label="Year (optional)"
            placeholder="e.g. 1995"
            icon="calendar_today"
            value="{{ $year ?? '' }}"
            style="mb-10"/>
        <select name="type" label="Film or series (optional)" icon="category" style="mb-10">
            <option value="" selected="{{ empty($type) ? 'true' : 'false' }}">Either</option>
            <option value="film" selected="{{ $type === 'film' ? 'true' : 'false' }}">Film</option>
            <option value="series" selected="{{ $type === 'series' ? 'true' : 'false' }}">Series</option>
        </select>

        @if (!empty($error))
            <block style="bg-black bg-opacity-15 p-10 rounded mb-10">
                <p style="text-white font-sm">{{ $error }}</p>
            </block>
        @endif

        <inline-content spacing="8 8">
            <button
                action="/host/requests/{{ $request->id }}/edit"
                method="POST"
                target="#request-{{ $request->id }}"
                swap="outer"
                style="filled #1F2937 sm">
                Save
            </button>
            <button
                action="/host/requests/{{ $request->id }}/row.ham"
                target="#request-{{ $request->id }}"
                swap="outer"
                style="outlined #FECACA sm">
                Cancel
            </button>
        </inline-content>
    </form>
</block>
