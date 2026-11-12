<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['form_id', 'status', 'eligibility', 'submitted_at', 'ip_address', 'validated_by', 'validated_at'])]
class FormSubmission extends Model
{
    public const STATUS_LABELS = [
        'pending' => 'En attente', 'validated' => 'Validé', 'rejected' => 'Rejeté', 'suspended' => 'Suspendu',
    ];

    public const ELIGIBILITY_LABELS = [
        'eligible' => 'Éligible', 'non_eligible' => 'Non éligible', 'verification' => 'À vérifier',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function values(): HasMany
    {
        return $this->hasMany(FormSubmissionValue::class);
    }

    public function valueFor(FormField $field): ?FormSubmissionValue
    {
        return $this->values->firstWhere('form_field_id', $field->id);
    }

    public function valueOfType(string $type): ?string
    {
        $field = $this->form->fields->firstWhere('type', $type);

        return $field ? $this->valueFor($field)?->value : null;
    }

    public function displayName(): string
    {
        $nameFields = $this->form->fields->where('is_identity_name', true);

        if ($nameFields->isNotEmpty()) {
            $name = $nameFields
                ->map(fn (FormField $field) => $this->valueFor($field)?->value)
                ->filter()
                ->implode(' ');

            if ($name !== '') {
                return $name;
            }
        }

        return 'Soumission #'.$this->id;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function eligibilityLabel(): string
    {
        return self::ELIGIBILITY_LABELS[$this->eligibility] ?? 'Non déterminée';
    }

    public function possibleDuplicate(): ?self
    {
        $name = $this->displayName();

        if ($name === '—') {
            return null;
        }

        return static::whereKeyNot($this->id)
            ->whereHas('values', fn ($q) => $q->where('value', $name))
            ->first();
    }
}
