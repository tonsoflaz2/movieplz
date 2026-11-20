{{--
    GET /host/clients.ham — list of connected clients, partial into #main.

    Variables:
      $current_client — host Client
      $clients        — Collection of Client (role='client')
--}}
<block style="p-20">
    <h1 style="font-2xl font-bold mb-5">Connected clients</h1>
    <p style="text-red-100 font-sm mb-15">
        People who redeemed an invite code to your library. Tap someone to
        see their suggestions. Revoke access kicks them off: they'll need a
        new code to come back.
    </p>

    @if ($clients->isEmpty())
        <block style="p-15 bg-black bg-opacity-10 rounded-lg">
            <p style="text-red-100 font-base mb-10">
                Nobody's connected yet. Generate an invite code from the
                home screen to add someone.
            </p>
            <button
                action="/host/home.ham"
                target="#main"
                swap="inner"
                style="outlined #FECACA">
                Back to home
            </button>
        </block>
    @else
        @foreach ($clients as $client)
            @include('ham.host.partials._client_row', ['client' => $client])
        @endforeach
    @endif
</block>
