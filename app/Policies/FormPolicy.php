<?php

namespace App\Policies;

use App\Models\User;

class FormPolicy
{
    public function manage(User $user): bool
    {
        return $user->isBe();
    }

    public function validateSubmissions(User $user): bool
    {
        return $user->isBe();
    }
}
