@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])

<div class="form-field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if($type !== 'password') value="{{ old($name, $value) }}" @endif @if($hint || $errors->has($name)) aria-describedby="{{ $name }}-help" @endif @error($name) aria-invalid="true" @enderror {{ $attributes }}>
    @if($errors->has($name))<p class="field-error" id="{{ $name }}-help">{{ $errors->first($name) }}</p>
    @elseif($hint)<small id="{{ $name }}-help">{{ $hint }}</small>@endif
</div>
