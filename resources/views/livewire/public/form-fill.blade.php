<?php

use App\Models\Form;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.guest')] class extends Component
{
    use WithFileUploads;

    public Form $form;

    public array $values = [];

    public bool $submitted = false;

    public function mount(Form $form): void
    {
        abort_unless($form->status === 'open', 404);

        $this->form = $form->load('fields');

        foreach ($this->form->fields as $field) {
            $this->values[$field->id] = $field->type === 'checkbox' ? [] : '';
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
                'file' => 'file|max:5120',
                'checkbox' => 'array',
                default => 'string',
            };

            $rules[$key] = ($field->is_required ? 'required' : 'nullable').'|'.$typeRule;
        }

        return $rules;
    }

    public function submit(): void
    {
        abort_unless($this->form->status === 'open', 404);

        $this->validate($this->rulesFor());

        $submission = $this->form->submissions()->create([
            'status' => 'pending',
            'submitted_at' => now(),
            'ip_address' => request()->ip(),
        ]);

        foreach ($this->form->fields as $field) {
            $value = $this->values[$field->id] ?? null;
            $filePath = null;

            if ($field->type === 'file' && $value) {
                $filePath = $value->store('submissions/'.$submission->id, 'local');
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

        $this->submitted = true;
    }
}; ?>

<div class="max-w-2xl mx-auto py-10 px-4">
    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
        @if ($submitted)
            <div class="text-center py-8">
                <h1 class="text-xl font-semibold mb-2">Merci !</h1>
                <p class="text-gray-500">Votre inscription a été enregistrée. Elle sera examinée par un administrateur avant validation.</p>
            </div>
        @else
            <h1 class="text-xl font-semibold mb-1">{{ $form->title }}</h1>
            <p class="text-gray-500 mb-6">{{ $form->description }}</p>

            <form wire:submit="submit" class="space-y-5">
                @foreach ($form->fields as $field)
                    <div>
                        <x-input-label :value="$field->label.($field->is_required ? ' *' : '')" />

                        @switch($field->type)
                            @case('textarea')
                                <textarea wire:model="values.{{ $field->id }}" rows="3" class="block w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm"></textarea>
                                @break
                            @case('select')
                                <select wire:model="values.{{ $field->id }}" class="block w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                                    <option value="">—</option>
                                    @foreach ($field->options ?? [] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                                @break
                            @case('radio')
                                <div class="space-y-1 mt-1">
                                    @foreach ($field->options ?? [] as $option)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="radio" wire:model="values.{{ $field->id }}" value="{{ $option }}">
                                            {{ $option }}
                                        </label>
                                    @endforeach
                                </div>
                                @break
                            @case('checkbox')
                                <div class="space-y-1 mt-1">
                                    @foreach ($field->options ?? [] as $option)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" wire:model="values.{{ $field->id }}" value="{{ $option }}">
                                            {{ $option }}
                                        </label>
                                    @endforeach
                                </div>
                                @break
                            @case('file')
                                <input type="file" wire:model="values.{{ $field->id }}" class="block w-full mt-1 text-sm">
                                @break
                            @case('date')
                                <x-text-input type="date" wire:model="values.{{ $field->id }}" class="block w-full mt-1" />
                                @break
                            @case('number')
                                <x-text-input type="number" wire:model="values.{{ $field->id }}" class="block w-full mt-1" />
                                @break
                            @default
                                <x-text-input type="{{ $field->type === 'email' ? 'email' : 'text' }}" wire:model="values.{{ $field->id }}" class="block w-full mt-1" />
                        @endswitch

                        <x-input-error :messages="$errors->get('values.'.$field->id)" class="mt-2" />
                    </div>
                @endforeach

                <x-primary-button type="submit">Envoyer mon inscription</x-primary-button>
            </form>
        @endif
    </div>
</div>
