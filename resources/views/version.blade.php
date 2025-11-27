@if (config('app.version'))
    v{{ config('app.version') }}
@else
    @fa(code-fork) {{mb_strtoupper(substr(exec('git log --pretty="%H" -n1 HEAD'),0, 12))}}
@endif
