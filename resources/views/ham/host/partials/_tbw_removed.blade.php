{{--
    POST /host/library/{id}/remove's answer, swapped over #tbw-item-{id}.

    Variables: $id, $title
--}}
<block id="tbw-item-{{ $id }}" style="bg-black bg-opacity-10 p-15 rounded-lg mb-10">
    <p style="text-red-100 font-sm">Removed {{ $title }} from your library.</p>
</block>
