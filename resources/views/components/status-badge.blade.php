@props(['tone' => 'muted'])
@php
$tones = [
    'success' => 'bg-success-soft text-success',
    'gold' => 'bg-gold-soft text-gold-foreground',
    'destructive' => 'bg-destructive-soft text-destructive',
    'primary' => 'bg-primary-soft text-primary-deep',
    'muted' => 'bg-muted text-muted-foreground',
];
$class = $tones[$tone] ?? $tones['muted'];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold $class"]) }}>
    {{ $slot }}
</span>
