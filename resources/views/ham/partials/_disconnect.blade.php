{{--
    "Disconnect this phone", on both home screens above leave/delete.
    The first tap fetches the second step (partials/_disconnect_confirm,
    which shows the person's reconnect code) over #disconnect-actions;
    confirming answers with partials/goodbye over the same block.

    Variable: $kept — what disconnecting keeps, in a sentence
--}}
<block id="disconnect-actions" style="mt-30">
    <button
        action="/account/disconnect.ham"
        target="#disconnect-actions"
        swap="outer"
        style="plain #FECACA sm">
        Disconnect this phone
    </button>
    <p style="text-red-100 font-xs">{{ $kept }}</p>
</block>
