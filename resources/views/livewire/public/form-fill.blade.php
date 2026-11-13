<?php

use App\Models\ActivityLog;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Cookie;
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

    public int $step = 0;

    public string $duplicateError = '';

    public function mount(Form $form): void
    {
        abort_unless($form->isOpen(), 404);

        $this->form = $form->load(['sections.fields', 'fields']);

        foreach ($this->form->fields as $field) {
            $this->values[$field->id] = $field->type === 'checkbox' && $field->hasOptions() ? [] : '';
        }
    }

    public function visibleFields($section)
    {
        return $section->fields->filter(fn ($field) => $field->isVisibleFor($this->values));
    }

    protected function rulesFor($fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
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

    public function nextStep(): void
    {
        $this->validate($this->rulesFor($this->visibleFields($this->form->sections[$this->step])));
        $this->step++;
    }

    public function prevStep(): void
    {
        $this->step = max(0, $this->step - 1);
    }

    public function submit(): void
    {
        abort_unless($this->form->isOpen(), 404);

        $this->validate($this->rulesFor($this->visibleFields($this->form->sections[$this->step])));

        $deviceCookie = 'amesco_submitted_'.$this->form->id;

        if ($this->form->one_response_per_person) {
            $alreadySubmittedOnThisDevice = request()->cookie($deviceCookie) !== null;

            $alreadySubmittedFromThisNetwork = FormSubmission::where('form_id', $this->form->id)
                ->where('ip_address', request()->ip())
                ->exists();

            $alreadySubmittedWithThisEmail = false;
            $emailField = $this->form->fields->firstWhere('type', 'email');

            if ($emailField) {
                $email = trim((string) ($this->values[$emailField->id] ?? ''));

                if ($email !== '') {
                    $alreadySubmittedWithThisEmail = FormSubmission::where('form_id', $this->form->id)
                        ->whereHas('values', fn ($q) => $q->where('form_field_id', $emailField->id)->where('value', $email))
                        ->exists();
                }
            }

            if ($alreadySubmittedOnThisDevice || $alreadySubmittedFromThisNetwork || $alreadySubmittedWithThisEmail) {
                $this->duplicateError = 'Une inscription a déjà été envoyée depuis cet appareil ou cette adresse e-mail. Une seule réponse par personne est autorisée pour ce formulaire.';

                return;
            }
        }

        $submission = $this->form->submissions()->create([
            'status' => $this->form->auto_validate ? 'validated' : 'pending',
            'eligibility' => $this->form->auto_validate ? 'eligible' : null,
            'submitted_at' => now(),
            'ip_address' => request()->ip(),
            'validated_at' => $this->form->auto_validate ? now() : null,
        ]);

        foreach ($this->form->fields as $field) {
            if (! $field->isVisibleFor($this->values)) {
                continue;
            }

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

        if ($this->form->one_response_per_person) {
            Cookie::queue(Cookie::forever($deviceCookie, (string) $submission->id));
        }

        $submission = $submission->fresh(['form.fields', 'values.field']);
        $summary = $submission->values
            ->filter(fn ($v) => ! $v->field->isFileType())
            ->map(fn ($v) => "{$v->field->label} : {$v->value}")
            ->implode(' ; ');

        ActivityLog::record(
            'submission.created',
            "Inscription « {$submission->displayName()} » reçue pour « {$this->form->title} » (IP {$submission->ip_address}) — {$summary}",
            $submission,
        );

        $this->referenceNumber = $submission->id;
        $this->submitted = true;
    }
}; ?>

<div class="max-w-2xl mx-auto py-10 px-4">
    @if ($submitted)
        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-card">
            <div class="tricolor h-2"></div>
            <div class="text-center py-10 px-6">
                <span class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-success-soft text-success"><x-icon name="check" class="size-7" /></span>
                <h1 class="text-xl font-bold mb-2">Inscription envoyée !</h1>
                <p class="text-sm text-muted-foreground">{{ $form->confirmation_message ?: 'Votre inscription a été enregistrée. Elle sera examinée par un administrateur avant validation.' }}</p>
                <p class="mt-4 text-xs text-muted-foreground">Numéro de référence</p>
                <p class="text-lg font-bold tabular text-primary">AMS-{{ now()->year }}-{{ str_pad($referenceNumber, 4, '0', STR_PAD_LEFT) }}</p>
                <a href="{{ route('home') }}" wire:navigate class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-deep">Retour à l'accueil</a>
            </div>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-card">
            <div class="tricolor h-2"></div>
            <div class="p-6 border-b border-border">
                <img src="{{ asset('images/logo.png') }}" alt="AM.E.S.CO" class="h-10 w-auto mb-4">
                <h1 class="text-xl font-bold mb-1">{{ $form->title }}</h1>
                <p class="text-muted-foreground text-sm">{{ $form->description }}</p>
            </div>

            @if ($form->show_progress_bar)
                <div class="px-6 pt-5">
                    <div class="flex items-center gap-1.5 mb-2">
                        @foreach ($form->sections as $i => $section)
                            <span class="h-1.5 flex-1 rounded-full {{ $i <= $step ? 'bg-primary' : 'bg-muted' }}"></span>
                        @endforeach
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-primary">Étape {{ $step + 1 }} sur {{ $form->sections->count() }}</p>
                </div>
            @endif

            <div class="p-6">
                @php $section = $form->sections[$step]; @endphp
                <h2 class="text-lg font-bold mb-1">{{ $section->title }}</h2>
                @if ($section->description)<p class="text-sm text-muted-foreground mb-4">{{ $section->description }}</p>@endif

                @if ($duplicateError)
                    <div class="mb-4 rounded-lg bg-destructive-soft text-destructive text-sm px-3 py-2">{{ $duplicateError }}</div>
                @endif

                <form wire:submit="{{ $step + 1 === $form->sections->count() ? 'submit' : 'nextStep' }}" class="space-y-5">
                    @foreach ($this->visibleFields($section) as $field)
                        <div wire:key="field-{{ $field->id }}">
                            <label class="text-sm font-medium">{{ $field->label }}@if ($field->is_required)<span class="text-destructive"> *</span>@endif</label>

                            @switch($field->type)
                                @case('textarea')
                                @case('address')
                                    <textarea wire:model.live="values.{{ $field->id }}" rows="3" class="mt-1 block w-full rounded-lg border-border text-sm"></textarea>
                                    @break
                                @case('select')
                                @case('country')
                                @case('institution')
                                @case('level')
                                    <select wire:model.live="values.{{ $field->id }}" class="mt-1 block w-full rounded-lg border-border text-sm">
                                        <option value="">—</option>
                                        @foreach ($field->effectiveOptions() as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    @break
                                @case('radio')
                                    <div class="space-y-1 mt-1">
                                        @foreach ($field->effectiveOptions() as $option)
                                            <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model.live="values.{{ $field->id }}" value="{{ $option }}"> {{ $option }}</label>
                                        @endforeach
                                    </div>
                                    @break
                                @case('checkbox')
                                    <div class="space-y-1 mt-1">
                                        @foreach ($field->effectiveOptions() as $option)
                                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="values.{{ $field->id }}" value="{{ $option }}"> {{ $option }}</label>
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
                                    <input type="date" wire:model.live="values.{{ $field->id }}" class="mt-1 block w-full rounded-lg border-border text-sm">
                                    @break
                                @case('number')
                                    <input type="number" wire:model.live="values.{{ $field->id }}" class="mt-1 block w-full rounded-lg border-border text-sm">
                                    @break
                                @default
                                    <input type="{{ $field->type === 'email' ? 'email' : 'text' }}" wire:model.live="values.{{ $field->id }}" class="mt-1 block w-full rounded-lg border-border text-sm">
                            @endswitch

                            @error('values.'.$field->id) <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                    @endforeach

                    @if ($step + 1 === $form->sections->count())
                        <p class="text-xs text-muted-foreground">Vos données sont traitées uniquement dans le cadre du recensement de l'amicale et de la constitution du fichier électoral.</p>
                    @endif

                    <div class="flex items-center justify-between pt-2">
                        @if ($step > 0)
                            <button type="button" wire:click="prevStep" class="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted">Précédent</button>
                        @else
                            <span></span>
                        @endif
                        <button type="submit" wire:loading.attr="disabled" wire:target="submit,nextStep" class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-deep disabled:opacity-50">
                            {{ $step + 1 === $form->sections->count() ? 'Envoyer mon inscription' : 'Continuer' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
