<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['election_id', 'form_id', 'filters'])]
class ElectionSource extends Model
{
    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
