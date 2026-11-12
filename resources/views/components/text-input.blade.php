@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-lg border-border text-sm focus:border-primary focus:ring-primary/20']) }}>
