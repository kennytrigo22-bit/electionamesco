<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['election_id', 'ballot_id', 'form_submission_id', 'name', 'email', 'phone', 'token', 'used_at', 'email_sent_at'])]
class ElectionVoter extends Model
{
    protected static function booted(): void
    {
        static::creating(function (ElectionVoter $voter) {
            $voter->token ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function hasVoted(): bool
    {
        return $this->used_at !== null;
    }

    public function displayName(): string
    {
        return $this->submission?->displayName() ?: $this->name;
    }

    public function voteUrl(): string
    {
        return route('vote.show', ['token' => $this->token]);
    }
}
