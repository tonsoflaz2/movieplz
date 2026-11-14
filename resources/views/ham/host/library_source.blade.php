{{--
    GET /host/library-source.ham — where the host's titles come from.
    Pushed from the dashboard; every form answers with this screen again,
    swapped over #library-source (outer).

    Variables:
      $host   — Host
      $total  — int, titles in the library
      $link   — the sheet link to show in the field (saved or just typed)
      $notice — optional success message
      $error  — optional error message
--}}
<block style="p-20" id="library-source">
    <h1 style="font-2xl font-bold mb-5">Add titles</h1>
    <p style="text-red-100 font-base mb-15">
        {{ $total }} {{ Str::plural('title', $total) }} in {{ $host->name }}.
    </p>

    @if ($notice)
        <block style="bg-green-800 p-15 rounded-lg mb-15">
            <row spacing="10">
                <icon name="check_circle green-300"></icon>
                <p style="text-green-100 font-sm font-bold">{{ $notice }}</p>
            </row>
        </block>
    @endif
    @if ($error)
        <block style="bg-black bg-opacity-15 p-15 rounded-lg mb-15">
            <row spacing="10">
                <icon name="error white"></icon>
                <p style="text-white font-sm">{{ $error }}</p>
            </row>
        </block>
    @endif

    {{-- Google Sheet --}}
    <block style="bg-black bg-opacity-10 p-15 rounded-lg mb-15">
        <row spacing="10">
            <icon name="table_chart white"></icon>
            <p style="font-base font-bold">Google Sheet</p>
        </row>
        <p style="text-red-100 font-sm mt-5 mb-10">
            Keep your list in a Google Sheet and MoviePlz stays in step with it:
            new rows show up every few minutes, with posters and details.
        </p>

        <expansion-panel title="How to set up the sheet" icon="help" expanded="{{ $host->library_sheet_url ? 'false' : 'true' }}" style="mb-10">
            <block style="px-5 pb-10">
                <p style="text-white font-sm font-bold mb-5">1. Put these headings in row 1</p>
                <block style="bg-black bg-opacity-15 rounded p-10 mb-5">
                    <p style="text-white font-sm font-mono">Title  |  Type  |  Year</p>
                    <p style="text-red-100 font-sm font-mono">Heat  |  film  |  1995</p>
                    <p style="text-red-100 font-sm font-mono">The Bear  |  series  |  2022</p>
                </block>
                <p style="text-red-100 font-sm mb-10">
                    Only Title is required. Type is film or series (blank means film).
                    The year helps find the right poster. Columns can be in any order,
                    and other columns are ignored. The first tab is used.
                </p>
                <p style="text-white font-sm font-bold mb-5">2. One title per row</p>
                <p style="text-red-100 font-sm mb-10">No blank rows needed, and no merged cells.</p>
                <p style="text-white font-sm font-bold mb-5">3. Share it</p>
                <p style="text-red-100 font-sm mb-10">
                    Share → General access → "Anyone with the link" → Viewer.
                    You don't need File → Share → "Publish to the web".
                </p>
                <p style="text-white font-sm font-bold mb-5">4. Paste the link below</p>
                <p style="text-red-100 font-sm">Copy it from the Share window (Copy link).</p>
            </block>
        </expansion-panel>
        @if ($host->library_sheet_url)
            <row spacing="8" style="mb-10">
                <icon name="link green-300"></icon>
                <p style="text-white font-sm">Linked: {{ Str::limit(preg_replace('#^https?://#', '', $host->library_sheet_url), 42) }}</p>
            </row>
        @endif
        <form>
            <input
                name="sheet_url"
                type="text"
                label="{{ $host->library_sheet_url ? 'Change sheet link' : 'Sheet link' }}"
                placeholder="https://docs.google.com/spreadsheets/d/…"
                icon="link"
                value="{{ $link ?? '' }}"
                style="mb-10"/>
            <inline-content spacing="8 8">
                <button
                    action="/host/library-source"
                    method="POST"
                    target="#library-source"
                    swap="outer"
                    style="filled #1F2937 sm">
                    {{ $host->library_sheet_url ? 'Save link' : 'Link sheet' }}
                </button>
                @if ($host->library_sheet_url)
                    <button
                        action="/host/library-sync"
                        method="POST"
                        target="#library-source"
                        swap="outer"
                        style="outlined #FECACA sm">
                        Check for new titles
                    </button>
                @endif
            </inline-content>
        </form>
        @if ($host->library_synced_at)
            <p style="text-red-200 font-xs mt-10">
                Last checked {{ $host->library_synced_at->lt(now()->subMinute()) ? $host->library_synced_at->diffForHumans() : 'just now' }}.
            </p>
        @endif
    </block>

    {{-- Paste a list --}}
    <block style="bg-black bg-opacity-10 p-15 rounded-lg">
        <row spacing="10">
            <icon name="content_paste white"></icon>
            <p style="font-base font-bold">Paste a list</p>
        </row>
        <p style="text-red-100 font-sm mt-5 mb-10">
            One title per line. Add a year or "series" if you like:
            "Dune (2021)", "The Bear, series".
        </p>
        <form>
            <textarea
                name="titles"
                label="Titles"
                placeholder="Dune (2021)"
                rows="5"
                style="mb-10"></textarea>
            <button
                action="/host/library-paste"
                method="POST"
                target="#library-source"
                swap="outer"
                style="filled #1F2937 sm">
                Add these titles
            </button>
        </form>
    </block>
</block>
