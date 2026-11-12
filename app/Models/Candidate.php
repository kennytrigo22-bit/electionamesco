<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ballot_id', 'label', 'description', 'photo_path', 'members', 'order'])]
class Candidate extends Model
{
    protected function casts(): array
    {
        return [
            'members' => 'array',
        ];
    }

    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }
}
