{{--
    HAM error partial.

    Returned by the global exception renderer (bootstrap/app.php) when
    anything throws on a HAM route. Also used inline by controllers for
    "this didn't work" cases that don't need a specific form to be
    re-rendered.

    The status code is always 200 — the mobile shell treats any non-2xx
    response as a framework-level error and discards the body.

    $restart = true adds a "Start over" button that reboots the app into
    /app.ham (a partial can't redirect), for an ended session.
--}}
<block style="p-15 bg-black bg-opacity-15 rounded-lg">
    <row spacing="10">
        <icon name="error white"></icon>
        <p style="text-white font-base">{{ $message ?? 'Something went wrong.' }}</p>
    </row>
    @if (!empty($restart))
        <button @click="app:/app.ham" style="filled #1F2937 mt-10">
            Start over
        </button>
    @endif
</block>
