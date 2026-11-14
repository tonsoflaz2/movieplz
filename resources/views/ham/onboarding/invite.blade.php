{{--
    Onboarding role choice — the first thing a new user sees.

    Both buttons use action="navigate:/path" to push a new screen
    onto the Navigator stack (with back button and slide animation).
--}}
<block style="p-20">
    @if ($goodbye ?? null)
        <block style="bg-black bg-opacity-15 p-15 rounded-lg mb-20">
            <row spacing="10">
                <icon name="check_circle white"></icon>
                <p style="text-white font-base">{{ $goodbye }}</p>
            </row>
        </block>
    @endif
    <h1 style="font-2xl font-bold mb-5">Welcome to MoviePlz</h1>
    <p style="text-red-100 font-base mb-30">
        A private place for friends to suggest movies and shows to each other.
    </p>

    <block style="bg-black bg-opacity-10 p-20 rounded-lg mb-15">
        <p style="font-xl font-bold mb-5">I'm setting up my library</p>
        <p style="text-red-100 font-sm mb-15">
            Keep a list of what you've watched, and let friends suggest
            what to watch next.
        </p>
        <button
            action="navigate:/onboarding/host-setup.ham"
            title="Host Setup"
            style="filled #1F2937">
            Set up as host
        </button>
    </block>

    <block style="bg-black bg-opacity-10 p-20 rounded-lg">
        <p style="font-xl font-bold mb-5">I was invited</p>
        <p style="text-red-100 font-sm mb-15">
            Someone gave you a 5-digit code so you can browse their
            library and suggest titles.
        </p>
        <button
            action="navigate:/onboarding/join.ham"
            title="Join"
            style="filled #1F2937">
            Join as a friend
        </button>
    </block>

    {{-- Back after disconnecting, or on a new phone: the reconnect code. --}}
    <block style="mt-20">
        <button
            action="navigate:/onboarding/reconnect.ham"
            title="Reconnect"
            style="plain #FECACA">
            Disconnected? Reconnect with your code
        </button>
    </block>
</block>
