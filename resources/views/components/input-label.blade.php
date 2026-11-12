@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-xs font-medium text-muted-foreground']) }}>
    {{ $value ?? $slot }}
</label>
