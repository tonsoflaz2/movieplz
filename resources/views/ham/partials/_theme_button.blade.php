{{--
    "Theme: Classic" on the home screens (host and friend), opening the
    theme picker.
--}}
<button action="navigate:/theme.ham" title="Theme" icon="palette" style="outlined #FECACA sm mt-15">
    Theme: {{ \App\Support\Theme::get($current_client?->theme)['name'] }}
</button>
