{{--
    One suggestion in the host's inbox.

    Used in the list (ham/host/requests.blade.php) and as the answer to
    POST /host/requests/{id}/up-next, /fulfill and /decline, whose buttons
    `target="#request-{id}"` and swap the updated row in place.

    New → "I'll watch it" (Up next) / "Saw it!" (on the list) / "Not for me" / Edit.
    Up next → "Saw it!" / "Not for me" / Edit.

    Variable: $request — MediaRequest with client loaded
--}}
@php
    $isNew = $request->status === 'pending' && ! $request->up_next_at;
    $isUpNext = $request->status === 'pending' && $request->up_next_at;
    $ago = fn ($at) => $at && $at->lt(now()->subMinute()) ? $at->diffForHumans() : 'just now';
@endphp
<block id="request-{{ $request->id }}" style="bg-black bg-opacity-10 p-15 rounded-lg mb-10">
    <row spacing="10">
        @if ($isNew)
            <icon name="mail red-100"></icon>
        @elseif ($isUpNext)
            <icon name="bookmark white"></icon>
        @elseif ($request->status === 'fulfilled')
            <icon name="check_circle green-300"></icon>
        @else
            <icon name="do_not_disturb_on red-200"></icon>
        @endif
        <p style="font-base font-bold">{{ $request->title }}</p>
    </row>

    <p style="text-red-100 font-sm mt-5">
        From {{ $request->client->display_name }}
        @if ($request->year) · {{ $request->year }} @endif
        @if ($request->type) · {{ ucfirst($request->type) }} @endif
        · {{ $request->created_at->diffForHumans() }}
    </p>

    @if ($request->notes)
        <p style="text-white font-sm mt-5 italic">"{{ $request->notes }}"</p>
    @endif

    @if ($isNew || $isUpNext)
        @if ($isUpNext)
            <p style="text-white font-sm mt-5">Up next since {{ $ago($request->up_next_at) }}</p>
        @endif
        <inline-content spacing="8 8" style="mt-10">
            @if ($isNew)
                <button
                    action="/host/requests/{{ $request->id }}/up-next"
                    method="POST"
                    target="#request-{{ $request->id }}"
                    swap="outer"
                    style="filled #1F2937 sm">
                    I'll watch it
                </button>
            @endif
            <button
                action="/host/requests/{{ $request->id }}/fulfill"
                method="POST"
                target="#request-{{ $request->id }}"
                swap="outer"
                style="filled green sm">
                Saw it!
            </button>
            <button
                action="/host/requests/{{ $request->id }}/decline"
                method="POST"
                target="#request-{{ $request->id }}"
                swap="outer"
                style="outlined #FECACA sm">
                Not for me
            </button>
            <button
                action="/host/requests/{{ $request->id }}/edit.ham"
                target="#request-{{ $request->id }}"
                swap="outer"
                style="plain #FECACA sm">
                Edit
            </button>
        </inline-content>
    @elseif ($request->status === 'fulfilled')
        <p style="text-green-300 font-sm mt-5">Seen {{ $ago($request->fulfilled_at) }} · on your list</p>
    @else
        <p style="text-red-200 font-sm mt-5">Passed</p>
    @endif
</block>
