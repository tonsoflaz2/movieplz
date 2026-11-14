{{--
    Request confirmation screen — navigated to after successful POST.
    Shows what was requested and a "Done" button to go back.
--}}
<block style="p-20">
    <icon name="check_circle green-300 size-48"></icon>
    <h1 style="font-2xl font-bold mt-15 mb-5">Suggestion sent</h1>
    <p style="text-red-100 font-base mb-20">
        Your host will see this in their inbox.
    </p>

    <block style="bg-black bg-opacity-10 p-15 rounded-lg mb-20">
        <p style="font-lg font-bold">{{ $media_request->title }}</p>
        @if ($media_request->year || $media_request->type)
            <p style="text-red-100 font-sm mt-5">
                {{ trim(($media_request->type ? ucfirst($media_request->type) : '').($media_request->year ? ' · '.$media_request->year : ''), ' ·') }}
            </p>
        @endif
        @if ($media_request->notes)
            <p style="text-red-100 font-sm mt-10">"{{ $media_request->notes }}"</p>
        @endif
    </block>

    <button @click="root; reload-current" style="filled #1F2937">
        Done
    </button>
</block>
