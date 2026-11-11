<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['form_id', 'status', 'submitted_at', 'ip_address', 'validated_by', 'validated_at'])]
class FormSubmission extends Model
{
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

    public function displayName(): string
    {
        $nameField = $this->form->fields->firstWhere('is_identity_name', true);

        if ($nameField) {
            return $this->valueFor($nameField)?->value ?? '—';
        }

        return 'Soumission #'.$this->id;
    }
}
