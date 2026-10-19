@props(['name', 'checked' => false, 'description' => null])

<div class="switch-field">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="switch">
        <input type="checkbox" role="switch" name="{{ $name }}" value="1" @checked($checked) @if($description) aria-describedby="{{ $name }}-description" @endif {{ $attributes }}>
        <span class="switch-track" aria-hidden="true"></span>
        <span>{{ $slot }}</span>
    </label>
    @if($description)<small id="{{ $name }}-description">{{ $description }}</small>@endif
</div>
