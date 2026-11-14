{{--
    After a reconnect code is accepted, swapped over #reconnect-form.
    "Continue" reboots the app into /app.ham, which now sees the session
    and opens the host dashboard or the friend's home.
--}}
<block style="p-20" id="reconnect-form">
    <icon name="check_circle green-300 size-48"></icon>
    <h1 style="font-2xl font-bold mt-15 mb-5">Welcome back, {{ $current_client->display_name }}</h1>
    <p style="text-red-100 font-base mb-20">
        This phone is connected to {{ $current_client->host->name ?? 'your library' }} again.
    </p>
    <button @click="app:/app.ham" style="filled #1F2937">
        Continue
    </button>
</block>
