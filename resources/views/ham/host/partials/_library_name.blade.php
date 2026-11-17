{{--
    The dashboard's heading: the library's name, with Rename. Rename swaps
    in _library_name_edit over #library-name; saving or cancelling answers
    with this again.

    Variable: $host — Host (null shows "Your library")
--}}
<block id="library-name">
    <row spacing="8" style="mb-5">
        <h1 style="font-2xl font-bold">{{ $host?->name ?? 'Your library' }}</h1>
        @if ($host)
            <button
                action="/host/library-name/edit.ham"
                target="#library-name"
                swap="outer"
                icon="edit"
                style="plain #FECACA sm">
                Rename
            </button>
        @endif
    </row>
    <p style="text-red-100 font-base mb-15">
        Signed in as host
    </p>
</block>
