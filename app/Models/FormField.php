<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['form_id', 'label', 'type', 'options', 'is_required', 'is_identity_name', 'order'])]
class FormField extends Model
{
    public const TYPES_WITH_OPTIONS = ['select', 'radio', 'checkbox'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_identity_name' => 'boolean',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, self::TYPES_WITH_OPTIONS, true);
    }
}
