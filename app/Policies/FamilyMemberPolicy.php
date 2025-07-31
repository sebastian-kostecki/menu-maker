<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FamilyMember;
use App\Models\User;

class FamilyMemberPolicy
{
    /**
     * Determine whether the user can view any models.
     * Users can only view their own family members.
     */
    public function viewAny(User $user): bool
    {
        return true; // Filtering by user_id is handled in the controller
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FamilyMember $familyMember): bool
    {
        return $user->id === $familyMember->user_id;
    }

    /**
     * Determine whether the user can create models.
     * Any authenticated user can create family members.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * Users can only update their own family members.
     */
    public function update(User $user, FamilyMember $familyMember): bool
    {
        return $user->id === $familyMember->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     * Users can only delete their own family members.
     */
    public function delete(User $user, FamilyMember $familyMember): bool
    {
        return $user->id === $familyMember->user_id;
    }
}
