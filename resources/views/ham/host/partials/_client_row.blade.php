{{--
    Single connected-client row.

    Used by ham/host/clients.blade.php for the initial list and
    returned by POST /host/clients/{id}/revoke and /rename (and the
    rename form's Cancel) for swap-in-place (target="#client-{id}"
    swap="outer"). Shows the host's nickname for the friend, with the name
    they picked beside it; Rename swaps in _client_rename. Tapping the name
    opens their suggestion history (/host/clients/{id}.ham).

    Variable: $client — Client model
--}}
@php
    $suggestionCount = $client->requests_count ?? $client->requests()->count();
@endphp
<block id="client-{{ $client->id }}" style="bg-black bg-opacity-10 p-15 rounded-lg mb-10">
    <block action="navigate:/host/clients/{{ $client->id }}.ham" title="{{ $client->nameForHost() }}">
        <row spacing="10">
            <icon name="{{ $client->revoked_at ? 'block red-300' : ($client->disconnected_at ? 'person_off red-200' : 'person white') }}"></icon>
            <p style="font-base font-bold">{{ $client->nameForHost() }}</p>
            <spacer/>
            <icon name="chevron_right red-100"></icon>
        </row>
        @if ($client->host_nickname)
            <p style="text-red-100 font-xs mt-2">Calls themselves {{ $client->display_name }}</p>
        @endif

        <p style="text-red-100 font-sm mt-5">
            {{ $suggestionCount === 1 ? '1 suggestion' : $suggestionCount.' suggestions' }}
            · joined {{ $client->created_at->diffForHumans() }}
            @if ($client->last_seen_at)
                · last seen {{ $client->last_seen_at->diffForHumans() }}
            @endif
        </p>
    </block>

    @if ($client->disconnected_at && ! $client->revoked_at)
        {{-- Disconnected their phone: their suggestions stay, and they can
             reconnect with their own code unless their access is revoked. --}}
        <p style="text-red-100 font-sm mt-5">
            Disconnected {{ $client->disconnected_at->diffForHumans() }} · can reconnect
        </p>
    @endif

    @if ($client->revoked_at)
        <p style="text-red-300 font-sm mt-5">
            Revoked {{ $client->revoked_at->lt(now()->subMinute()) ? $client->revoked_at->diffForHumans() : 'just now' }}
        </p>
    @else
        {{-- Two-step revoke: the first tap only asks. (The engine this app
             ships with has no confirm= on buttons yet.) --}}
        <block var-asking="false">
            <block @@show="!$asking">
                <inline-content spacing="8 8" style="mt-10">
                    <button
                        action="/host/clients/{{ $client->id }}/rename.ham"
                        target="#client-{{ $client->id }}"
                        swap="outer"
                        icon="edit"
                        style="outlined #FECACA sm">
                        Rename
                    </button>
                    <button @click="$asking = true" style="outlined #FECACA sm">
                        Revoke access
                    </button>
                </inline-content>
            </block>
            <block @@show="$asking" style="mt-10">
                <p style="text-white font-sm mb-10">
                    Remove {{ $client->nameForHost() }}'s access? They'll need a new code to come back.
                </p>
                <inline-content spacing="8 8">
                    <button
                        action="/host/clients/{{ $client->id }}/revoke"
                        method="POST"
                        target="#client-{{ $client->id }}"
                        swap="outer"
                        style="filled #1F2937 sm">
                        Yes, revoke
                    </button>
                    <button @click="$asking = false" style="outlined #FECACA sm">
                        Cancel
                    </button>
                </inline-content>
            </block>
        </block>
    @endif
</block>
