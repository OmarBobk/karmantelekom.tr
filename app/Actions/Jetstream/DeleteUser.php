<?php

namespace App\Actions\Jetstream;

use App\Models\InventoryMovement;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
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

        if ($this->hasFinancialRecords($user)) {
            throw ValidationException::withMessages([
                'password' => [__('Your account has recorded purchase or supplier payment history, which must be kept. Please ask the owner to deactivate your account instead.')],
            ]);
        }

        $user->deleteProfilePhoto();
        $user->tokens->each->delete();
        $user->delete();
    }

    /**
     * Whether the user is the actor on any purchase, receipt, supplier payment, or supplier ledger entry.
     */
    private function hasFinancialRecords(User $user): bool
    {
        return Purchase::query()->where(fn ($query) => $query
            ->where('created_by', $user->id)
            ->orWhere('posted_by', $user->id)
            ->orWhere('cancelled_by', $user->id)
            ->orWhere('reversed_by', $user->id))->exists()
            || PurchaseReceipt::query()->where('user_id', $user->id)->exists()
            || SupplierPayment::query()->where(fn ($query) => $query
                ->where('user_id', $user->id)
                ->orWhere('reversed_by', $user->id))->exists()
            || SupplierLedgerEntry::query()->where('user_id', $user->id)->exists();
    }
}
