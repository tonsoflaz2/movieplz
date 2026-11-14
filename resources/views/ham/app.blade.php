{{--
    MoviePlz entry point — GET /app.ham

    The mobile shell fetches this on cold launch. Every other HAM route
    returns a partial that gets swapped into #main. Only this template
    wraps its content in a full `<app>` → `<screen>` with app-bar and
    bottom nav.

    Branches on the `$screen` variable (set by MainController):
      - "onboarding" → invite-code entry form, no bottom nav
      - "client"     → authenticated client home + client nav
      - "host"       → authenticated host home + host nav
--}}
<app>
    {{--
        splash_color: what the engine paints between the native launch
        screen and this document (cached from the last launch; without it,
        near-black). It is the launch screen's own red (hype.toml [ios]
        splash_color), the icon tile's edge, so the launch flows into the
        app's #DC2626 with no dark frame.
    --}}
    <config
        dark-mode="dark"
        splash_color="#F00709"
        bg_color="#DC2626"
        dark_bg_color="#DC2626"
        primary_color="#991B1B"
        default_text_color="#FFFFFF"
        selected_color="#FFFFFF"
        unselected_color="#FECACA"></config>
    <screen bg_color="#DC2626">
        <app-bar title="MoviePlz" bg_color="#991B1B" text_color="#FFFFFF" elevation="0"/>

        {{-- ?open= (a tapped notification) loads that screen into #main as it mounts. --}}
        <main id="main" action="{{ $open ?? match($screen) { 'client' => '/home.ham', 'host' => '/host/home.ham', default => '/' } }}"@if ($open) action-load="{{ $open }}"@endif>
            @if ($open)
            @elseif ($screen === 'onboarding')
                @include('ham.onboarding.invite')
            @elseif ($screen === 'host')
                @include('ham.host.home_body')
            @else
                @include('ham.client.home_body')
            @endif
        </main>

        {{--
            Mounted once someone has joined or created a library, so the
            permission prompt comes with a reason, not at first launch.
            Joining and setting up both restart the app into this, and it
            re-registers on every later launch. A tapped notification
            restarts the app on the screen its data.route names.
            ?for= is the signed-in person: the engine skips a registration
            whose endpoint and token it has already sent, so without it,
            leaving and joining again in the same run left the new person
            without the token. The server goes by the session, not ?for=.
        --}}
        @if ($screen !== 'onboarding')
            <notification register="/api/devices?for={{ $current_client->id }}" on-tap="app:/app.ham?open=$notify.data.route"/>
        @endif

        @if ($screen === 'host')
            <bottom-navigation-bar bg_color="#991B1B" selected_color="#FFFFFF" unselected_color="#D4D4D4">
                <bottom-nav-item label="Dashboard" icon="home active:fill active:700" action="/host/home.ham" selected="{{ ($open ?? '/host/home.ham') === '/host/home.ham' ? 'true' : 'false' }}"/>
                <bottom-nav-item label="Suggestions" icon="inbox active:fill active:700" action="/host/requests.ham" selected="{{ ($open ?? '/host/home.ham') === '/host/requests.ham' ? 'true' : 'false' }}"/>
                <bottom-nav-item label="Library" icon="movie active:fill active:700" action="/library.ham" selected="{{ ($open ?? '/host/home.ham') === '/library.ham' ? 'true' : 'false' }}"/>
                <bottom-nav-item label="Clients" icon="people active:fill active:700" action="/host/clients.ham" selected="{{ ($open ?? '/host/home.ham') === '/host/clients.ham' ? 'true' : 'false' }}"/>
            </bottom-navigation-bar>
        @elseif ($screen === 'client')
            <bottom-navigation-bar bg_color="#991B1B" selected_color="#FFFFFF" unselected_color="#D4D4D4">
                <bottom-nav-item label="Home" icon="home active:fill active:700" action="/home.ham" selected="{{ ($open ?? '/home.ham') === '/home.ham' ? 'true' : 'false' }}"/>
                <bottom-nav-item label="Library" icon="movie active:fill active:700" action="/library.ham" selected="{{ ($open ?? '/home.ham') === '/library.ham' ? 'true' : 'false' }}"/>
                <bottom-nav-item label="Suggestions" icon="inbox active:fill active:700" action="/requests.ham" selected="{{ ($open ?? '/home.ham') === '/requests.ham' ? 'true' : 'false' }}"/>
            </bottom-navigation-bar>
        @endif
    </screen>
</app>
