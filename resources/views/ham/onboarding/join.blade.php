{{--
    Client code entry form — pushed as its own screen via navigate.
    POST /onboarding/invite handles submission and answers with either
    this form again (with $error) or the name step, swapped over
    #join-form (outer), so the screen never nests inside itself.

    The code field is type="code": one box per digit, number keyboard,
    and it keeps a leading zero (a type="number" field turned 05404
    into 5404).
--}}
<block style="p-20" id="join-form">
    <h1 style="font-2xl font-bold mb-5">Enter your code</h1>
    <p style="text-red-100 font-base mb-20">
        Your host shared a 5-digit code with you — enter it
        below. Codes expire after 10 minutes.
    </p>

    @if (!empty($error))
        <block style="bg-black bg-opacity-15 p-10 rounded mb-15">
            <p style="text-white font-sm">{{ $error }}</p>
        </block>
    @endif

    <form>
        <input
            name="code"
            type="code"
            inputmode="numeric"
            length="5"
            value="{{ $code ?? '' }}"
            style="font-mono font-size-28 text-white mb-20"/>

        <button
            action="/onboarding/invite"
            method="POST"
            target="#join-form"
            swap="outer"
            style="filled #1F2937">
            Continue
        </button>
    </form>
</block>
