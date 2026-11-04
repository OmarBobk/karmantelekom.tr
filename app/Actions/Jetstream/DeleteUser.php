<?php

namespace App\Actions\Jetstream;

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

        $user->deleteProfilePhoto();
        $user->tokens->each->delete();
        $user->delete();
    }
}
