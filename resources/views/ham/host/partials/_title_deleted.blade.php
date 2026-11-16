{{--
    POST /host/library/{id}/delete's answer, swapped over the title page's
    #title-{id}.

    Variables: $id, $title
--}}
<block id="title-{{ $id }}" style="px-20 pt-20">
    <row spacing="10" style="mb-10">
        <icon name="delete white"></icon>
        <p style="font-base font-bold">Deleted {{ $title }} from your library.</p>
    </row>
    <button action="navigate:/library.ham" title="Library" style="outlined #FECACA sm">
        Back to the library
    </button>
</block>
