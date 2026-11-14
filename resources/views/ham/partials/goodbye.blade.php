{{--
    After disconnecting a phone, a friend leaving or a host deleting their
    library. Swapped over #account-actions (or #disconnect-actions), it restarts the app into /app.ham as it mounts: with
    no session that is the welcome screen, which repeats the message. The
    message and "Start over" stay as the fallback if the restart is slow.

    Variables: $message — what happened; $target — the block's id
--}}
<block id="{{ $target ?? 'account-actions' }}" @on.load="app:/app.ham" style="bg-black bg-opacity-15 p-15 rounded-lg mt-20">
    <row spacing="10">
        <icon name="check_circle white"></icon>
        <p style="text-white font-base">{{ $message }}</p>
    </row>
    <button @click="app:/app.ham" style="filled #1F2937 mt-10">
        Start over
    </button>
</block>
