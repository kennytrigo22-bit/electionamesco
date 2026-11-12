@props(['name', 'class' => 'size-9'])
@php
$initials = collect(preg_split('/[\s-]+/', trim($name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
$colors = ['bg-primary-soft text-primary-deep', 'bg-gold-soft text-gold-foreground', 'bg-destructive-soft text-destructive'];
$color = $colors[crc32($name) % count($colors)];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center justify-center rounded-full font-bold text-[11px] $color $class"]) }}>
    {{ $initials ?: '?' }}
</span>
