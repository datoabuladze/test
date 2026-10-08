@props(['name', 'label', 'model' => null, 'textarea' => false, 'rows' => 4, 'required' => false, 'help' => null])
{{-- Tabbed inputs for a JSON translatable attribute: name[en], name[ka], ... --}}
@php $values = old($name, $model?->getAttribute($name) ?? []); $values = is_array($values) ? $values : []; @endphp
<div x-data="disclosure" class="space-y-1.5">
    <div class="flex items-center justify-between">
        <span class="label !mb-0">{{ $label }} @if($required)<span class="text-bad">*</span>@endif</span>
    </div>
    <div class="grid gap-2 {{ $textarea ? '' : 'sm:grid-cols-2' }}">
        @foreach (config('platform.locales') as $code => $meta)
            <label class="block">
                <span class="mb-0.5 block text-[11px] font-semibold uppercase tracking-wide text-ink-3">{{ $code }} · {{ $meta['name'] }}</span>
                @if ($textarea)
                    <textarea name="{{ $name }}[{{ $code }}]" rows="{{ $rows }}" class="input font-mono text-xs" lang="{{ $code }}" @if($required && $code === 'en') required @endif>{{ $values[$code] ?? '' }}</textarea>
                @else
                    <input name="{{ $name }}[{{ $code }}]" value="{{ $values[$code] ?? '' }}" class="input" lang="{{ $code }}" @if($required && $code === 'en') required @endif>
                @endif
            </label>
        @endforeach
    </div>
    @if ($help)<p class="help">{{ $help }}</p>@endif
    @error($name.'.en')<p class="error">{{ $message }}</p>@enderror
</div>
