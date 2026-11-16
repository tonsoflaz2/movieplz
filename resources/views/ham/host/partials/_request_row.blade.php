{{--
    One suggestion in the host's inbox.

    Used in the list (ham/host/requests.blade.php), in TBW, and as the
    answer to POST /host/requests/{id}/up-next, /fulfill and /decline,
    whose buttons `target="#request-{id}"` and swap the updated row in place.

    New → "In progress" / "Added" / "Declined" / Edit.
    In progress → "Added" / "Declined" / Edit.

    "Add to library?" (ticked to start) and the reply go with whichever
    button is tapped (they sit in the same <form>). Added puts the title in
    the library only while the box is ticked; the hidden add_sent field lets
    the server tell an unticked box from a post that has no box at all. The
    friend sees the reply with the suggestion and in the notification.

    Variables:
      $request — MediaRequest with client loaded
      $error   — optional, e.g. a reply that's too long
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
            <icon name="autorenew white"></icon>
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
            <p style="text-white font-sm mt-5">In progress since {{ $ago($request->up_next_at) }}</p>
        @endif
        @if ($request->host_reply)
            <p style="text-red-100 font-sm mt-5">You replied: "{{ $request->host_reply }}"</p>
        @endif
        <form>
            <inline-content spacing="8 8" style="mt-10">
                @if ($isNew)
                    <button
                        action="/host/requests/{{ $request->id }}/up-next"
                        method="POST"
                        target="#request-{{ $request->id }}"
                        swap="outer"
                        style="filled #1F2937 sm">
                        In progress
                    </button>
                @endif
                <button
                    action="/host/requests/{{ $request->id }}/fulfill"
                    method="POST"
                    target="#request-{{ $request->id }}"
                    swap="outer"
                    style="filled green sm">
                    Added
                </button>
                <button
                    action="/host/requests/{{ $request->id }}/decline"
                    method="POST"
                    target="#request-{{ $request->id }}"
                    swap="outer"
                    style="outlined #FECACA sm">
                    Declined
                </button>
                <button
                    action="/host/requests/{{ $request->id }}/edit.ham"
                    target="#request-{{ $request->id }}"
                    swap="outer"
                    style="plain #FECACA sm">
                    Edit
                </button>
            </inline-content>

            <input type="hidden" name="add_sent" value="1"/>
            <checkbox name="add" checked="true" label="Add to library?" style="mt-10"/>

            <textarea
                name="reply"
                label="Reply (optional)"
                placeholder="Anything you want {{ $request->client->display_name }} to know"
                rows="2"
                style="mt-5"></textarea>
        </form>

        @if (!empty($error))
            <block style="bg-black bg-opacity-15 p-10 rounded mt-10">
                <p style="text-white font-sm">{{ $error }}</p>
            </block>
        @endif
    @elseif ($request->status === 'fulfilled')
        @if ($request->wasAddedToLibrary())
            <p style="text-green-300 font-sm mt-5">Added {{ $ago($request->fulfilled_at) }} · in your library</p>
        @else
            <p style="text-green-300 font-sm mt-5">Added {{ $ago($request->fulfilled_at) }} · not in your library</p>
        @endif
        @if ($request->host_reply)
            <p style="text-red-100 font-sm mt-5">You replied: "{{ $request->host_reply }}"</p>
        @endif
    @else
        <p style="text-red-200 font-sm mt-5">Declined</p>
        @if ($request->host_reply)
            <p style="text-red-100 font-sm mt-5">You replied: "{{ $request->host_reply }}"</p>
        @endif
    @endif
</block>
