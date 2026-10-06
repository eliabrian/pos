<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VenueTable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;

class VenueTablePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return Filament::getTenant()->hasFeature('has_qr_order') && !$user->isSystemAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VenueTable $venueTable): bool
    {
        return Filament::getTenant()->hasFeature('has_qr_order') && !$user->isSystemAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return Filament::getTenant()->hasFeature('has_qr_order') && !$user->isSystemAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VenueTable $venueTable): bool
    {
        return Filament::getTenant()->hasFeature('has_qr_order') && !$user->isSystemAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VenueTable $venueTable): bool
    {
        return Filament::getTenant()->hasFeature('has_qr_order') && !$user->isSystemAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VenueTable $venueTable): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VenueTable $venueTable): bool
    {
        return false;
    }
}
