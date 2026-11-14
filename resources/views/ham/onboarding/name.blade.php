{{--
    Display-name entry screen.

    Returned as a #main swap after a successful code redemption in
    InviteController::redeem. The POST /onboarding/name handler creates
    the Client row, stashes client_id in the session, and returns the
    home body (client or host depending on the invite's grants_host
    flag).

    $invite is the pending Invite model.
    $error and $display_name are optional (set on validation failure).
--}}
<block style="p-20" id="join-form">
    <h1 style="font-2xl font-bold mb-10">
        @if ($invite->grants_host)
            Claim your host
        @else
            One more thing
        @endif
    </h1>
    <p style="text-red-100 font-base mb-20">
        @if ($invite->grants_host)
            You're about to start your own list. What
            should we call your library?
        @else
            What should the host call you? This shows up next to any
            suggestions you make.
        @endif
    </p>

    <form>
        <input
            name="display_name"
            type="text"
            label="{{ $invite->grants_host ? 'Your name' : 'Your name' }}"
            placeholder="{{ $invite->grants_host ? 'e.g. Christian' : 'e.g. Alice' }}"
            icon="person"
            max-length="40"
            value="{{ $display_name ?? '' }}"
            style="mb-10"/>

        @if (!empty($error))
            <block style="bg-black bg-opacity-15 p-10 rounded mb-10">
                <p style="text-white font-sm">{{ $error }}</p>
            </block>
        @endif

        <button
            action="/onboarding/name"
            method="POST"
            target="#join-form"
            swap="outer"
            style="filled #1F2937 mb-10">
            @if ($invite->grants_host)
                Claim host
            @else
                Continue
            @endif
        </button>
    </form>
</block>
