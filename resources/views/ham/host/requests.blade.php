{{--
    GET /host/requests.ham — full request inbox, partial into #main.

    Variables:
      $current_client — host Client
      $filter         — "new" | "up_next" | "seen" | "passed"
                        (shown as New, In progress, Added, Declined)
      $requests       — Collection of MediaRequest (filtered)
--}}
<block style="p-20">
    <h1 style="font-2xl font-bold mb-15">Suggestions</h1>

    <inline-content spacing="8 8" style="mb-15">
        <button action="/host/requests.ham?status=new" target="#main" swap="inner"
                style="{{ $filter === 'new' ? 'filled #1F2937' : 'outlined #FECACA' }} sm">
            New
        </button>
        <button action="/host/requests.ham?status=up_next" target="#main" swap="inner"
                style="{{ $filter === 'up_next' ? 'filled #1F2937' : 'outlined #FECACA' }} sm">
            In progress
        </button>
        <button action="/host/requests.ham?status=seen" target="#main" swap="inner"
                style="{{ $filter === 'seen' ? 'filled #1F2937' : 'outlined #FECACA' }} sm">
            Added
        </button>
        <button action="/host/requests.ham?status=passed" target="#main" swap="inner"
                style="{{ $filter === 'passed' ? 'filled #1F2937' : 'outlined #FECACA' }} sm">
            Declined
        </button>
    </inline-content>

    @if ($requests->isEmpty())
        <block style="p-15 bg-black bg-opacity-10 rounded-lg">
            <p style="text-red-100 font-base">
                {{ [
                    'new' => 'No new suggestions.',
                    'up_next' => 'Nothing in progress. Tap "In progress" on a suggestion to keep it here.',
                    'seen' => 'Nothing added yet.',
                    'passed' => 'Nothing declined.',
                ][$filter] }}
            </p>
        </block>
    @else
        @foreach ($requests as $req)
            @include('ham.host.partials._request_row', ['request' => $req])
        @endforeach
    @endif
</block>
