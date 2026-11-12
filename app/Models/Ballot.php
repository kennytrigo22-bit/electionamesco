<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['election_id', 'title', 'description', 'type', 'max_choices', 'allow_blank', 'allow_abstention', 'order'])]
class Ballot extends Model
{
    public const TYPE_SINGLE_CHOICE = 'single_choice';

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public const TYPE_RANKED = 'ranked';

    public const TYPE_YESNO = 'yesno';

    public const TYPE_LABELS = [
        self::TYPE_SINGLE_CHOICE => 'Choix unique',
        self::TYPE_MULTIPLE_CHOICE => 'Choix multiple',
        self::TYPE_RANKED => 'Classement (ranked choice)',
        self::TYPE_YESNO => 'Oui / Non',
    ];

    protected function casts(): array
    {
        return [
            'allow_blank' => 'boolean',
            'allow_abstention' => 'boolean',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class)->orderBy('order');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function candidacies(): HasMany
    {
        return $this->hasMany(Candidacy::class);
    }
}
