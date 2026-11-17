{{--
    GET /theme.ham — the theme picker, pushed from the home screen. Each
    card shows the theme in its own colors. The cards' colors are written in
    lowercase hex so the theme pass (App\Support\Theme) leaves them as they
    are; everything else on this screen is in the person's current theme.

    Tapping a card posts /theme?name=… and swaps in _theme_applied over
    #theme-picker, which restarts the app in the new colors.

    Variables:
      $current — the person's theme key
      $error   — optional
--}}
<block style="p-20" id="theme-picker">
    <h1 style="font-2xl font-bold mb-5">Theme</h1>
    <p style="text-red-100 font-base mb-15">Pick the colors for MoviePlz on this phone. Only you see them.</p>

    @if (!empty($error))
        <block style="bg-black bg-opacity-15 p-15 rounded-lg mb-15">
            <p style="text-white font-sm">{{ $error }}</p>
        </block>
    @endif

    @foreach ([true => 'Dark', false => 'Light'] as $dark => $heading)
        <p style="font-lg font-bold mt-10 mb-10">{{ $heading }}</p>
        @foreach (\App\Support\Theme::THEMES as $key => $t)
            @continue($t['dark'] !== (bool) $dark)
            @php
                $bg = strtolower($t['bg']);
                $bar = strtolower($t['bar']);
                $ink = $t['dark'] ? '#ffffff' : '#111111';
                $accent = strtolower($t['outline']);
            @endphp
            <block
                action="/theme?name={{ $key }}"
                method="POST"
                target="#theme-picker"
                swap="outer"
                style="bg-{{ $bg }} rounded-lg mb-10 overflow-hidden {{ $key === $current ? 'border-3 border-'.$accent : 'border border-'.$bar }}">
                <block style="bg-{{ $bar }} h-12"></block>
                <row spacing="10" style="p-15">
                    <block style="w-28 h-28 rounded-full bg-{{ $accent }}"></block>
                    <p style="font-base font-bold text-{{ $ink }}">{{ $t['name'] }}</p>
                    @if ($key === $current)
                        <spacer/>
                        <p style="font-sm text-{{ $ink }}">In use</p>
                    @endif
                </row>
            </block>
        @endforeach
    @endforeach
</block>
