<?php

namespace App\Livewire\Frontend;

use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class ShopCreationComponent extends Component
{
    public string $name = '';
    public string $phone = '';
    public string $address = '';
    public array $links = [];
    public array $newLink = ['type' => '', 'url' => ''];
    public bool $showModal = true;

    protected array $rules = [
        'name' => ['required', 'string', 'max:255', 'unique:shops,name'],
        'phone' => ['required', 'string', 'max:20'],
        'address' => ['required', 'string', 'max:500'],
        'links' => ['array'],
        'links.*' => ['required', 'max:255'],
    ];

    protected array $messages = [
        'name.required' => 'Shop name is required.',
        'name.unique' => 'This shop name is already taken.',
        'phone.required' => 'Phone number is required.',
        'address.required' => 'Address is required.',
    ];

    public function mount(): void
    {
        // Check if user already has a shop
        if (Auth::user()->ownedShop) {
            $this->showModal = false;
            $this->redirect(route('main'));
        }
    }

    public function addLink(): void
    {
        $this->validate([
            'newLink.type' => ['required', 'string', 'max:255'],
            'newLink.url' => ['required', 'url', 'max:255'],
        ]);

        // Convert type to lowercase and remove spaces for consistency
        $type = strtolower(str_replace(' ', '_', $this->newLink['type']));

        // Add the link to the links array
        $this->links[$type] = $this->newLink['url'];

        // Reset the newLink form
        $this->newLink = ['type' => '', 'url' => ''];

        // Reset validation errors for the newLink fields
        $this->resetValidation(['newLink.type', 'newLink.url']);
    }

    public function removeLink(string $type): void
    {
        unset($this->links[$type]);
    }

    public function createShop(): void
    {
        $this->validate();

        try {
            $shop = Shop::create([
                'name' => $this->name,
                'phone' => $this->phone,
                'address' => $this->address,
                'links' => $this->links,
                'owner_id' => Auth::id(), // Set the current user as the owner
            ]);

            $this->showModal = false;

            session()->flash('message', 'Shop created successfully! Welcome to your new shop.');

            // Redirect to main page or dashboard
            $this->redirect(route('main'));

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create shop. Please try again.');
        }
    }

    public function skipShopCreation(): void
    {
        // Redirect back to shop creation - they can't skip it
        $this->redirect(route('shop.create'));
    }

    #[Layout('layouts.frontend')]
    #[Title('Create Your Shop')]
    public function render()
    {
        return view('livewire.frontend.shop-creation-component');
    }
}
