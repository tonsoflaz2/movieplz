{{--
    Renaming the library, swapped over the dashboard's #library-name.
    Friends see the new name the next time their screens load.

    Variables:
      $host  — Host
      $value — the name in the field (the current one, or what was typed)
      $error — optional
--}}
<block id="library-name" style="mb-15">
    <form>
        <input
            name="name"
            type="text"
            label="Library name"
            icon="local_movies"
            max-length="{{ \App\Models\Host::NAME_MAX }}"
            value="{{ $value }}"
            style="mb-10"/>

        @if (!empty($error))
            <block style="bg-black bg-opacity-15 p-10 rounded mb-10">
                <p style="text-white font-sm">{{ $error }}</p>
            </block>
        @endif

        <inline-content spacing="8 8">
            <button
                action="/host/library-name"
                method="POST"
                target="#library-name"
                swap="outer"
                style="filled #1F2937 sm">
                Save
            </button>
            <button
                action="/host/library-name.ham"
                target="#library-name"
                swap="outer"
                style="outlined #FECACA sm">
                Cancel
            </button>
        </inline-content>
    </form>
</block>
