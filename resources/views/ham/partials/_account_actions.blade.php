{{--
    Leave / delete, at the bottom of the home screens. Two steps: the first
    tap only asks. Answered by partials/goodbye, swapped over
    #account-actions. The bottom margin keeps the confirm buttons clear of
    the tab bar when this is the last thing on the page.

    Variables:
      $label    — the first button ("Leave this library")
      $question — what the second step asks
      $confirm  — the confirming button ("Yes, leave")
      $action   — the POST url
--}}
<block id="account-actions" var-leaving="false" style="mt-30 mb-150">
    <block @@show="!$leaving">
        <button @click="$leaving = true" style="plain #FECACA sm">
            {{ $label }}
        </button>
    </block>
    <block @@show="$leaving" style="bg-black bg-opacity-15 p-15 rounded-lg">
        <p style="text-white font-sm mb-10">{{ $question }}</p>
        <inline-content spacing="8 8">
            <button
                action="{{ $action }}"
                method="POST"
                target="#account-actions"
                swap="outer"
                style="filled #1F2937 sm">
                {{ $confirm }}
            </button>
            <button @click="$leaving = false" style="outlined #FECACA sm">
                Cancel
            </button>
        </inline-content>
    </block>
</block>
