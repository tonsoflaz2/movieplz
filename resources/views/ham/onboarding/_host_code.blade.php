{{--
    Returned by POST /onboarding/host-setup, swapped over #host-setup:
    the library exists, here is the first invite code. "Go to my library"
    reboots the app into /app.ham, which now opens the host dashboard.
--}}
<block style="p-20" id="host-setup">
    <h1 style="font-2xl font-bold mb-5">{{ $current_client->host->name }} is ready</h1>
    <p style="text-red-100 font-base mb-20">
        Invite your first friend with this code. Next, add your titles from
        your dashboard: link a Google Sheet or paste a list.
    </p>

    <block id="invite-code-display">
        @include('ham.host.partials._invite_code', ['invite' => $invite])
    </block>

    <p style="text-red-100 font-sm mt-15 mb-20">
        Tell your friend to open MoviePlz, tap "Join as a friend", and enter
        this code. You can make more codes anytime.
    </p>

    <button @click="app:/app.ham" style="filled #1F2937">
        Go to my library
    </button>
</block>
