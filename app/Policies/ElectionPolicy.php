<?php

namespace App\Policies;

use App\Models\User;

class ElectionPolicy
{
    public function manage(User $user): bool
    {
        return $user->isCei();
    }

    public function viewResults(User $user): bool
    {
        return $user->isCei();
    }
}
