@props(['title', 'description' => null])

<div {{ $attributes->class('page-heading') }}>
    <h1>{{ $title }}</h1>
    @if($description)<p class="lede">{{ $description }}</p>@endif
    @if($slot->isNotEmpty())<div class="actions">{{ $slot }}</div>@endif
</div>
