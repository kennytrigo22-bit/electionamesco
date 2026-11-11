<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ballot_id', 'label', 'description', 'photo_path', 'order'])]
class Candidate extends Model
{
    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }
}
