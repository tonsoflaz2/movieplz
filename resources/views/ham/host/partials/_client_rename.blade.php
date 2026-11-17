{{--
    Renaming a friend, swapped over their #client-{id} row. The name is the
    host's own nickname for them: only the host sees it. Blank goes back
    to the name they picked.

    Variables:
      $client — Client
      $value  — the name in the field
      $error  — optional
--}}
<block id="client-{{ $client->id }}" style="bg-black bg-opacity-10 p-15 rounded-lg mb-10">
    <form>
        <input
            name="nickname"
            type="text"
            label="Name"
            icon="person"
            max-length="{{ \App\Models\Client::NAME_MAX }}"
            value="{{ $value }}"
            helper="Only you see this. They call themselves {{ $client->display_name }}; leave it blank to use that."
            style="mb-10"/>

        @if (!empty($error))
            <block style="bg-black bg-opacity-15 p-10 rounded mb-10">
                <p style="text-white font-sm">{{ $error }}</p>
            </block>
        @endif

        <inline-content spacing="8 8">
            <button
                action="/host/clients/{{ $client->id }}/rename"
                method="POST"
                target="#client-{{ $client->id }}"
                swap="outer"
                style="filled #1F2937 sm">
                Save
            </button>
            <button
                action="/host/clients/{{ $client->id }}/row.ham"
                target="#client-{{ $client->id }}"
                swap="outer"
                style="outlined #FECACA sm">
                Cancel
            </button>
        </inline-content>
    </form>
</block>
