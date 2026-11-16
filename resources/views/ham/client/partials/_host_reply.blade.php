{{--
    What the host wrote back to a suggestion, under it in the friend's list.
    Nothing when they didn't write one.

    Variable: $req — MediaRequest
--}}
@if ($req->host_reply)
    <p style="text-white font-sm mt-5">Reply: "{{ $req->host_reply }}"</p>
@endif
