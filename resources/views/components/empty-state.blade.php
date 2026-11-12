@props(['icon' => 'sparkles', 'title', 'description' => null])
<div class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
    <span class="flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary-deep">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <div class="text-sm font-semibold">{{ $title }}</div>
    @if ($description)
        <p class="max-w-sm text-sm text-muted-foreground">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-2">{{ $action }}</div>
    @endisset
</div>
