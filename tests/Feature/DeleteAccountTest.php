<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Livewire\Livewire;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_accounts_can_be_deleted(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());

        $component = Livewire::test(DeleteUserForm::class)
            ->set('password', 'password')
            ->call('deleteUser');

        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_before_account_can_be_deleted(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());

        Livewire::test(DeleteUserForm::class)
            ->set('password', 'wrong-password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }

    public function test_account_with_store_memberships_cannot_be_deleted(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        Storage::fake(config('jetstream.profile_photo_disk', 'public'));
        Storage::disk(config('jetstream.profile_photo_disk', 'public'))->put('profile-photos/avatar.jpg', 'photo');

        $user = User::factory()->create([
            'password' => 'correct-password',
            'profile_photo_path' => 'profile-photos/avatar.jpg',
        ]);
        $token = $user->createToken('Test Token')->accessToken;
        $store = Store::factory()->create();
        $user->stores()->attach($store);

        $this->actingAs($user);

        $component = Livewire::test(DeleteUserForm::class)
            ->set('password', 'correct-password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        $this->assertSame(
            __('Your account is assigned to one or more stores. Please ask the owner to remove your store assignments before deleting your account.'),
            $component->errors()->first('password'),
        );

        $this->assertNotNull($user->fresh());
        $this->assertDatabaseHas('store_user', ['store_id' => $store->id, 'user_id' => $user->id]);
        $this->assertModelExists($token);
        Storage::disk(config('jetstream.profile_photo_disk', 'public'))->assertExists('profile-photos/avatar.jpg');
    }
}
