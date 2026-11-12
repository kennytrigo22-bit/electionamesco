@props(['label', 'value', 'icon' => 'bar-chart', 'tone' => 'primary', 'hint' => null])
@php
$tones = [
    'primary' => 'bg-primary-soft text-primary-deep',
    'gold' => 'bg-gold-soft text-gold-foreground',
    'destructive' => 'bg-destructive-soft text-destructive',
    'muted' => 'bg-muted text-muted-foreground',
];
$toneClass = $tones[$tone] ?? $tones['primary'];
@endphp
<div class="rounded-xl border border-border bg-card p-4 shadow-card">
    <div class="flex items-center justify-between">
        <span class="text-xs font-medium text-muted-foreground">{{ $label }}</span>
        <span class="flex size-8 items-center justify-center rounded-lg {{ $toneClass }}">
            <x-icon :name="$icon" class="size-4" />
        </span>
    </div>
    <div class="mt-2 text-2xl font-bold tabular">{{ $value }}</div>
    @if ($hint)
        <div class="mt-1 text-xs text-muted-foreground">{{ $hint }}</div>
    @endif
</div>
