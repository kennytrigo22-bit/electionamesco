<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title', 'description', 'slug', 'status', 'opens_at', 'closes_at', 'created_by',
    'max_responses', 'allow_multiple_submissions', 'auto_validate', 'confirmation_message',
])]
class Form extends Model
{
    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'allow_multiple_submissions' => 'boolean',
            'auto_validate' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function isOpen(): bool
    {
        if ($this->status !== 'open') {
            return false;
        }

        if ($this->max_responses && $this->submissions()->count() >= $this->max_responses) {
            return false;
        }

        return true;
    }
}
