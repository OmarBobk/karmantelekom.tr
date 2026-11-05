<?php

namespace App\Actions\Jetstream;

use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     *
     * @throws ValidationException
     */
    public function delete(User $user): void
    {
        if ($user->stores()->exists()) {
            throw ValidationException::withMessages([
                'password' => [__('Your account is assigned to one or more stores. Please ask the owner to remove your store assignments before deleting your account.')],
            ]);
        }

        if (InventoryMovement::query()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'password' => [__('Your account has recorded inventory history, which must be kept. Please ask the owner to deactivate your account instead.')],
            ]);
        }

        $user->deleteProfilePhoto();
        $user->tokens->each->delete();
        $user->delete();
    }
}
