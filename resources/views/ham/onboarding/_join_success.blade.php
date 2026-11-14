{{--
    Shown after successful client onboarding, swapped over #join-form.
    "Start browsing" reboots the app into /app.ham, which now sees the
    session and opens the client home with its bottom nav.
--}}
<block style="p-20" id="join-form">
    <icon name="check_circle green-300 size-48"></icon>
    <h1 style="font-2xl font-bold mt-15 mb-5">You're in!</h1>
    <p style="text-red-100 font-base mb-20">
        Welcome to {{ $current_client->host->name ?? 'the library' }}.
    </p>
    <button @click="app:/app.ham" style="filled #1F2937">
        Start browsing
    </button>
</block>
