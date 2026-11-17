{{--
    Host setup — navigated to from role choice. Asks the host's name and,
    optionally, the library's (blank: "{name}'s library"), then POST
    /onboarding/host-setup answers with the first invite code, swapped
    over #host-setup (outer). On a validation error it answers with this
    form again, with $error and the typed $display_name and $library_name.
--}}
<block style="p-20" id="host-setup">
    <h1 style="font-2xl font-bold mb-5">Set up your library</h1>
    <p style="text-red-100 font-base mb-20">
        Friends you invite can see what you've watched and suggest what to watch next.
        You'll add your titles next — from a Google Sheet or by pasting a list.
    </p>

    @if (!empty($error))
        <block style="bg-black bg-opacity-15 p-10 rounded mb-15">
            <p style="text-white font-sm">{{ $error }}</p>
        </block>
    @endif

    <form>
        <input
            name="display_name"
            type="text"
            label="Your name"
            placeholder="e.g. Christian"
            icon="person"
            max-length="40"
            value="{{ $display_name ?? '' }}"
            style="mb-15"/>

        <input
            name="library_name"
            type="text"
            label="Library name (optional)"
            placeholder="e.g. Movie Night"
            helper="Leave it blank and it's called “your name's library”. You can change it anytime."
            icon="local_movies"
            max-length="{{ \App\Models\Host::NAME_MAX }}"
            value="{{ $library_name ?? '' }}"
            style="mb-15"/>

        <button
            action="/onboarding/host-setup"
            method="POST"
            target="#host-setup"
            swap="outer"
            style="filled #1F2937">
            Create my library
        </button>
    </form>
</block>
