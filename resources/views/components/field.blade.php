@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null, 'required' => false, 'autocomplete' => 'off'])
<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" autocomplete="{{ $autocomplete }}" @if($required) required @endif @error($name) aria-invalid="true" aria-describedby="{{ $name }}-e" @enderror {{ $attributes }}>
    @if($hint)<div class="hint">{{ $hint }}</div>@endif
    @error($name)<div class="err" id="{{ $name }}-e" role="alert">{{ $message }}</div>@enderror
</div>
