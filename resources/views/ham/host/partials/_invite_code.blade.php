{{--
    Single-use invite code display with live expiry countdown.

    Swapped into #invite-code-display by POST /host/invite-code, and
    included on the host-ready onboarding screen.

    Polls itself every 30s to update the remaining-time text.

    Variable: $invite — Invite model (with code + expires_at)
--}}
<block style="bg-black bg-opacity-10 p-20 rounded-lg"
    action="/host/invite-status/{{ $invite->id }}.ham"
    target="#invite-code-display"
    swap="inner"
    trigger="every 30s">
    @if ($invite->isUsable())
        <p style="text-white font-sm font-bold mb-10">
            Code for one friend — read it to them now
        </p>
        <center>
            <p style="text-white font-4xl font-bold mb-10">
                {{ substr($invite->code, 0, 1) }}
                {{ substr($invite->code, 1, 1) }}
                {{ substr($invite->code, 2, 1) }}
                {{ substr($invite->code, 3, 1) }}
                {{ substr($invite->code, 4, 1) }}
            </p>
        </center>
        <p style="text-red-100 font-sm" align="center">
            @php
                $remaining = (int) now()->diffInMinutes($invite->expires_at, false);
                $remainingSec = (int) now()->diffInSeconds($invite->expires_at, false);
            @endphp
            @if ($remaining > 1)
                Expires in about {{ $remaining }} minutes. Single-use.
            @elseif ($remainingSec > 0)
                Expires in less than a minute. Single-use.
            @else
                This code has expired.
            @endif
        </p>
    @elseif ($invite->redeemed_at)
        <block style="bg-green-800 p-15 rounded">
            <p style="text-green-100 font-sm font-bold">Code used!</p>
            <p style="text-green-200 font-sm mt-5">
                Your friend joined. Generate a new code for the next person.
            </p>
        </block>
    @else
        <block style="bg-black bg-opacity-15 p-15 rounded">
            <p style="text-white font-sm font-bold">Code expired</p>
            <p style="text-red-100 font-sm mt-5">
                Generate a new code when you're ready to invite someone.
            </p>
        </block>
    @endif
</block>
