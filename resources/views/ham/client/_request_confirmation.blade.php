{{--
    Returned by POST /requests on success.
    Auto-navigates to the confirmation screen on mount, replacing the
    form page so Back can't return to it and send it twice.
--}}
<block
    action="navigate:/requests/confirmed/{{ $media_request->id }}.ham"
    title="Suggestion Sent"
    swap="replace"
    trigger="load">
</block>
