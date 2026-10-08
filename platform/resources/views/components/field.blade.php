@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null, 'required' => false, 'autocomplete' => null])
<div>
    <label for="f-{{ $name }}" class="label">{{ $label }}</label>
    <input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}"
           @if ($required) required @endif @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           @error($name) aria-invalid="true" aria-describedby="e-{{ $name }}" @enderror
           {{ $attributes->merge(['class' => 'input']) }}>
    @if ($help)<p class="help">{{ $help }}</p>@endif
    @error($name)<p id="e-{{ $name }}" class="error">{{ $message }}</p>@enderror
</div>
