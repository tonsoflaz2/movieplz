{{--
    POST /theme's answer, swapped over #theme-picker. It restarts the app
    as it mounts (trigger="load"), so the new theme reaches the app bar and
    tabs; the button does the same if the restart doesn't start by itself.

    Variable: $theme — the chosen theme (App\Support\Theme::THEMES entry)
--}}
<block style="p-20" id="theme-picker">
    <block action="app:/app.ham" trigger="load"></block>
    <h1 style="font-2xl font-bold mb-5">{{ $theme['name'] }}</h1>
    <p style="text-red-100 font-base mb-15">Switching MoviePlz to {{ $theme['name'] }}…</p>
    <button @click="app:/app.ham" style="filled #1F2937">
        Restart now
    </button>
</block>
