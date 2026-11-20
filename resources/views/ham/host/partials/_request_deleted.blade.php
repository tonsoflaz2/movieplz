{{--
    POST /host/requests/{id}/delete's answer, swapped over #request-{id}.

    Variables: $id, $title, $from (the friend's name, as the host calls them)
--}}
<block id="request-{{ $id }}" style="bg-black bg-opacity-10 p-15 rounded-lg mb-10">
    <p style="text-red-100 font-sm">Deleted {{ $from }}'s suggestion of {{ $title }}.</p>
</block>
