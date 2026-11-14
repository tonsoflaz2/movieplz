{{--
    The second step of "Disconnect this phone" (GET /account/disconnect.ham),
    swapped over #disconnect-actions: the reconnect code to keep, then
    confirm or cancel. Cancel hides the panel here (no request); the next
    visit to the home screen starts from the first step again.

    Variables:
      $code           — the person's reconnect code, XXXX-XXXX-XXXX
      $current_client — the signed-in person
--}}
<block id="disconnect-actions" var-open="true" var-copied="false" style="mt-30">
    <block @@show="!$open">
        <button @click="$open = true" style="plain #FECACA sm">
            Disconnect this phone
        </button>
    </block>
    <block @@show="$open" style="bg-black bg-opacity-15 p-15 rounded-lg">
        <p style="text-white font-sm mb-10">
            @if ($current_client->isHost())
                Disconnect this phone from {{ $current_client->host->name ?? 'your library' }}? Your library, friends and suggestions are kept.
            @else
                Disconnect this phone from {{ $current_client->host->name ?? 'this library' }}? Your suggestions are kept.
            @endif
            To come back, here or on another phone, tap Reconnect on the welcome screen and enter your reconnect code:
        </p>
        <p style="font-mono font-xl font-bold text-white mb-10">{{ $code }}</p>
        <button @click="clipboard:copy:{{ $code }}; $copied = true" style="outlined #FECACA sm mb-5">
            Copy code
        </button>
        <p @@show="$copied" style="text-red-100 font-xs mb-5">Copied. Keep it somewhere safe: it's the only way back in.</p>
        <inline-content spacing="8 8" style="mt-10">
            <button
                action="/account/disconnect"
                method="POST"
                target="#disconnect-actions"
                swap="outer"
                style="filled #1F2937 sm">
                Disconnect
            </button>
            <button @click="$open = false" style="outlined #FECACA sm">
                Cancel
            </button>
        </inline-content>
    </block>
</block>
