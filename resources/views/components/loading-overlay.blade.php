<div wire:loading.delay.default class="fixed inset-x-0 top-0 z-[100] h-1 overflow-hidden bg-primary-soft">
    <div class="h-full w-1/3 animate-[loading-bar_1.1s_ease-in-out_infinite] bg-primary"></div>
</div>
<div wire:loading.delay.longer class="fixed bottom-5 right-5 z-[100] flex items-center gap-2 rounded-full border border-border bg-card px-4 py-2 text-xs font-semibold text-muted-foreground shadow-elevated">
    <svg class="size-4 animate-spin text-primary" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path>
    </svg>
    Chargement…
</div>
