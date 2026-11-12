<?php

use App\Models\Form;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.public')] class extends Component
{
    use WithFileUploads;

    public Form $form;

    public array $values = [];

    public bool $submitted = false;

    public ?int $referenceNumber = null;

    public function mount(Form $form): void
    {
        abort_unless($form->isOpen(), 404);

        $this->form = $form->load('fields');

        foreach ($this->form->fields as $field) {
            $this->values[$field->id] = $field->type === 'checkbox' && $field->hasOptions() ? [] : '';
        }
    }

    protected function rulesFor(): array
    {
        $rules = [];

        foreach ($this->form->fields as $field) {
            $key = "values.{$field->id}";

            $typeRule = match ($field->type) {
                'email' => 'email',
                'number' => 'numeric',
                'date' => 'date',
                'file', 'image' => 'file|max:5120',
                'checkbox' => 'array',
                default => 'string',
            };

            $rules[$key] = ($field->is_required ? 'required' : 'nullable').'|'.$typeRule;
        }

        return $rules;
    }

    public function submit(): void
    {
        abort_unless($this->form->isOpen(), 404);

        $this->validate($this->rulesFor());

        $submission = $this->form->submissions()->create([
            'status' => $this->form->auto_validate ? 'validated' : 'pending',
            'eligibility' => $this->form->auto_validate ? 'eligible' : null,
            'submitted_at' => now(),
            'ip_address' => request()->ip(),
            'validated_at' => $this->form->auto_validate ? now() : null,
        ]);

        foreach ($this->form->fields as $field) {
            $value = $this->values[$field->id] ?? null;
            $filePath = null;

            if ($field->isFileType() && $value) {
                $filePath = $value->store('submissions/'.$submission->id, 'public');
                $value = $value->getClientOriginalName();
            } elseif (is_array($value)) {
                $value = implode(', ', $value);
            }

            $submission->values()->create([
                'form_field_id' => $field->id,
                'value' => $value,
                'file_path' => $filePath,
            ]);
        }

        $this->referenceNumber = $submission->id;
        $this->submitted = true;
    }
}; ?>

<div class="max-w-2xl mx-auto py-10 px-4">
    <div class="rounded-2xl border border-border bg-card shadow-card p-6">
        @if ($submitted)
            <div class="text-center py-8">
                <span class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-success-soft text-success"><x-icon name="check" class="size-7" /></span>
                <h1 class="text-xl font-bold mb-2">Inscription envoyée !</h1>
                <p class="text-sm text-muted-foreground">{{ $form->confirmation_message ?: 'Votre inscription a été enregistrée. Elle sera examinée par un administrateur avant validation.' }}</p>
                <p class="mt-4 text-xs text-muted-foreground">Numéro de référence</p>
                <p class="text-lg font-bold tabular text-primary">AMS-{{ now()->year }}-{{ str_pad($referenceNumber, 4, '0', STR_PAD_LEFT) }}</p>
                <a href="{{ route('home') }}" wire:navigate class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">Retour à l'accueil</a>
            </div>
        @else
            <h1 class="text-xl font-bold mb-1">{{ $form->title }}</h1>
            <p class="text-muted-foreground mb-6 text-sm">{{ $form->description }}</p>

            <form wire:submit="submit" class="space-y-5">
                @foreach ($form->fields as $field)
                    <div>
                        <label class="text-sm font-medium">{{ $field->label }}@if ($field->is_required)<span class="text-destructive"> *</span>@endif</label>

                        @switch($field->type)
                            @case('textarea')
                            @case('address')
                                <textarea wire:model="values.{{ $field->id }}" rows="3" class="mt-1 block w-full rounded-lg border-border text-sm"></textarea>
                                @break
                            @case('select')
                            @case('country')
                            @case('institution')
                            @case('level')
                                <select wire:model="values.{{ $field->id }}" class="mt-1 block w-full rounded-lg border-border text-sm">
                                    <option value="">—</option>
                                    @foreach ($field->effectiveOptions() as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                                @break
                            @case('radio')
                                <div class="space-y-1 mt-1">
                                    @foreach ($field->effectiveOptions() as $option)
                                        <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model="values.{{ $field->id }}" value="{{ $option }}"> {{ $option }}</label>
                                    @endforeach
                                </div>
                                @break
                            @case('checkbox')
                                <div class="space-y-1 mt-1">
                                    @foreach ($field->effectiveOptions() as $option)
                                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="values.{{ $field->id }}" value="{{ $option }}"> {{ $option }}</label>
                                    @endforeach
                                </div>
                                @break
                            @case('image')
                                <input type="file" accept="image/*" wire:model="values.{{ $field->id }}" class="mt-1 block w-full text-sm">
                                @if ($values[$field->id] instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                                    <img src="{{ $values[$field->id]->temporaryUrl() }}" class="mt-2 h-24 rounded-lg border border-border object-cover">
                                @endif
                                @break
                            @case('file')
                                <input type="file" wire:model="values.{{ $field->id }}" class="mt-1 block w-full text-sm">
                                @break
                            @case('date')
                                <input type="date" wire:model="values.{{ $field->id }}" class="mt-1 block w-full rounded-lg border-border text-sm">
                                @break
                            @case('number')
                                <input type="number" wire:model="values.{{ $field->id }}" class="mt-1 block w-full rounded-lg border-border text-sm">
                                @break
                            @default
                                <input type="{{ $field->type === 'email' ? 'email' : 'text' }}" wire:model="values.{{ $field->id }}" class="mt-1 block w-full rounded-lg border-border text-sm">
                        @endswitch

                        @error('values.'.$field->id) <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                @endforeach

                <p class="text-xs text-muted-foreground">Vos données sont traitées uniquement dans le cadre du recensement de l'amicale et de la constitution du fichier électoral.</p>

                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-deep">Envoyer mon inscription</button>
            </form>
        @endif
    </div>
</div>
