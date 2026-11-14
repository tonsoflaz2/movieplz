{{--
    GET /requests/new.ham — request creation form, partial into #main.

    On validation failure, POST /requests re-renders this same partial
    with $error set, swapped over #request-form (this page is usually
    pushed, so #main would be the screen underneath).

    Variables:
      $current_client — Client
      $title, $year, $type, $notes — optional prefills
      $error          — optional server-side validation error message
--}}
<block style="p-20" id="request-form">
    <h1 style="font-2xl font-bold mb-5">Suggest a title</h1>
    <p style="text-red-100 font-sm mb-15">
        Your host sees your suggestion and can add it to the library.
    </p>

    <form>
        <input
            name="title"
            type="text"
            label="Title"
            placeholder="e.g. Poor Things"
            icon="movie"
            max-length="200"
            value="{{ $title ?? '' }}"
            style="mb-10"/>

        <input
            name="year"
            type="number"
            label="Year (optional)"
            placeholder="e.g. 2023"
            icon="calendar_today"
            value="{{ $year ?? '' }}"
            style="mb-10"/>

        <select name="type" label="Film or series (optional)" icon="category" style="mb-10">
            <option value="" selected="{{ empty($type) ? 'true' : 'false' }}">Either</option>
            <option value="film" selected="{{ ($type ?? '') === 'film' ? 'true' : 'false' }}">Film</option>
            <option value="series" selected="{{ ($type ?? '') === 'series' ? 'true' : 'false' }}">Series</option>
        </select>

        <textarea
            name="notes"
            label="Notes (optional)"
            placeholder="e.g. season 2 specifically"
            rows="3"
            style="mb-10">{{ $notes ?? '' }}</textarea>

        @if (!empty($error))
            <block style="bg-black bg-opacity-15 p-10 rounded mb-10">
                <p style="text-white font-sm">{{ $error }}</p>
            </block>
        @endif

        <button
            action="/requests"
            method="POST"
            target="#request-form"
            swap="outer"
            style="filled #1F2937">
            Send suggestion
        </button>
    </form>
</block>
