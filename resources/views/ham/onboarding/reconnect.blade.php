{{--
    Reconnect — pushed from the welcome screen. POST /onboarding/reconnect
    answers with this form again (with $error) or the welcome-back partial,
    swapped over #reconnect-form.

    Variables (optional): $error, $code — the previous attempt
--}}
<block style="p-20" id="reconnect-form">
    <h1 style="font-2xl font-bold mb-5">Reconnect</h1>
    <p style="text-red-100 font-base mb-20">
        Enter the reconnect code you saved when you disconnected. It looks
        like ABCD-EFGH-JKMN. It takes you back to your library, on this phone
        or a new one.
    </p>

    @if (!empty($error))
        <block style="bg-black bg-opacity-15 p-10 rounded mb-15">
            <p style="text-white font-sm">{{ $error }}</p>
        </block>
    @endif

    <form>
        <input
            name="code"
            type="text"
            label="Reconnect code"
            placeholder="ABCD-EFGH-JKMN"
            icon="key"
            autocapitalize="characters"
            max-length="20"
            value="{{ $code ?? '' }}"
            style="mb-20"/>

        <button
            action="/onboarding/reconnect"
            method="POST"
            target="#reconnect-form"
            swap="outer"
            style="filled #1F2937">
            Reconnect
        </button>
    </form>
</block>
