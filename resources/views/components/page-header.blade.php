@props(['title', 'description' => null])
<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-start justify-between gap-4']) }}>
    <div>
        <h1 class="text-xl font-bold tracking-tight">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-muted-foreground">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
