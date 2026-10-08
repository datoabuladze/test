@props(['label', 'value', 'icon' => 'chart', 'hint' => null])
<div class="card p-4">
    <div class="flex items-center justify-between text-xs text-ink-3">{{ $label }}<x-icon :name="$icon" class="size-4 text-brand"/></div>
    <div class="mt-1 font-display text-2xl font-black tabular-nums">{{ $value }}</div>
    @if ($hint)<div class="mt-0.5 text-[11px] text-ink-3">{{ $hint }}</div>@endif
</div>
