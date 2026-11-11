<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ballot_id', 'choice_data', 'submitted_at'])]
class Vote extends Model
{
    protected function casts(): array
    {
        return [
            'choice_data' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }
}
