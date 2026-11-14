{{--
    Library item detail — pushed via navigate from the library list.

    Variables:
      $item           — LibraryItem
      $current_client — Client
--}}
<block style="p-0">
    @include('ham.client.partials._title_main', ['item' => $item])

    <block style="px-20 pb-20">
        <row spacing="8" style="mb-20">
            <icon name="check_circle green-300"></icon>
            <p style="text-white font-base">{{ $current_client->role === 'host' ? "You've seen this" : ($current_client->host?->hostClient()?->display_name ?? 'Your host').' has seen this' }}</p>
        </row>

        @if ($current_client->role === 'client')
            <p style="text-red-100 font-sm mb-10">
                Missing a season or a better version? Ask your host.
            </p>
            <button
                action="navigate:/requests/new.ham?title={{ urlencode($item->title) }}{{ $item->year ? '&year='.$item->year : '' }}{{ $item->type ? '&type='.$item->type : '' }}"
                title="Suggest"
                style="outlined #FECACA mb-10">
                Ask about this {{ $item->type === 'series' ? 'series' : 'film' }}
            </button>
        @endif
    </block>
</block>
