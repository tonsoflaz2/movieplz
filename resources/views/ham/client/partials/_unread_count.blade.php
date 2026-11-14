{{--
    Tiny polling partial — client fulfilled-but-unseen count.

    Initial render: included directly inside the home body.
    Polling: GET /home/unread.ham replaces this partial every 30s.

    Variable: $count — int
--}}
@if ($count > 0)
    <block
        action="navigate:/requests.ham"
        title="Your suggestions"
        style="bg-green-800 p-15 rounded-lg mb-15">
        <row spacing="10">
            <icon name="check_circle green-300"></icon>
            <p style="text-green-100 font-bold">
                @if ($count === 1)
                    Your host saw 1 of your suggestions — tap to see it.
                @else
                    Your host saw {{ $count }} of your suggestions — tap to see them.
                @endif
            </p>
        </row>
    </block>
@endif
