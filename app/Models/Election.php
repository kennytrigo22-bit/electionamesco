<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'status', 'opens_at', 'closes_at', 'created_by'])]
class Election extends Model
{
    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ElectionSource::class);
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(Ballot::class)->orderBy('order');
    }

    public function voters(): HasMany
    {
        return $this->hasMany(ElectionVoter::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
