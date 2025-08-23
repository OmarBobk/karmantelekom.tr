<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class ShopOwnerProfile extends Component
{
    public string $activeTab = 'basic';
    public ?Shop $shop = null;
    public array $metrics = [];
    public \Illuminate\Database\Eloquent\Collection $recentOrders;
    public array $topProducts = [];

    // Modal and form properties
    public bool $showEditModal = false;
    public bool $showPasswordModal = false;
    public string $shopName = '';
    public string $shopAddress = '';
    public string $shopPhone = '';
    public string $shopTaxNumber = '';
    public string $ownerName = '';

    // Password change properties
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $confirmPassword = '';

    // Address management properties
    public bool $showAddressModal = false;
    public bool $isEditingAddress = false;
    public ?int $editingAddressId = null;
    public string $addressLabel = '';
    public string $addressLine = '';
    public string $addressCity = '';
    public string $addressPostalCode = '';
    public string $addressState = '';
    public ?float $addressLatitude = null;
    public ?float $addressLongitude = null;
    public bool $addressIsPrimary = false;

    // Turkish cities and districts data
    public array $turkishCities = [];
    public array $cityDistricts = [];

    // Order management properties
    public ?\App\Models\Order $selectedOrder = null;
    public bool $showOrderDetailsModal = false;

    public function mount(): void
    {
        $user = Auth::user();

        if (!$user || !$user->isShopOwner()) {
            abort(403, 'Access denied. Only shop owners can view this page.');
        }

        $this->shop = $user->ownedShop;

        if (!$this->shop) {
            abort(404, 'Shop not found.');
        }

        try {
            $this->loadMetrics();
            $this->loadRecentOrders();
            $this->loadTopProducts();
            $this->loadShopData();
            $this->loadTurkishCities();
        } catch (\Exception $e) {
            logger()->error('Error loading shop owner profile data: ' . $e->getMessage());
            // Initialize with empty collection if loading fails
            $this->recentOrders = new \Illuminate\Database\Eloquent\Collection();
        }
    }

    private function loadMetrics(): void
    {
        $this->metrics = [
            'total_orders' => $this->shop->orders()->count(),
            'total_revenue' => $this->shop->orders()->sum('total_price') ?? 0,
            'pending_orders' => $this->shop->orders()->where('status', 'pending')->count(),
            'completed_orders' => $this->shop->orders()->whereIn('status', ['delivered', 'completed'])->count(),
            'avg_order_value' => $this->shop->orders()->avg('total_price') ?? 0,
            'total_products' => \App\Models\OrderItem::whereIn('order_id', $this->shop->orders()->pluck('id'))->distinct('product_id')->count(),
        ];
    }

    private function loadRecentOrders(): void
    {
        $this->recentOrders = $this->shop->orders()
            ->with(['customer', 'items.product'])
            ->latest()
            ->take(5)
            ->get();
    }

    private function loadTopProducts(): void
    {
        $this->topProducts = \App\Models\OrderItem::query()
            ->select('product_id', \Illuminate\Support\Facades\DB::raw('SUM(quantity) as total_quantity'))
            ->whereIn('order_id', function ($query) {
                $query->select('id')
                    ->from('orders')
                    ->where('shop_id', $this->shop->id);
            })
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->with('product:id,name')
            ->limit(5)
            ->get()
            ->toArray();
    }

    private function loadShopData(): void
    {
        $this->shopName = $this->shop->name;
        $this->shopAddress = $this->shop->address;
        $this->shopPhone = $this->shop->phone ?? '';
        $this->shopTaxNumber = $this->shop->tax_number ?? '';
        $this->ownerName = Auth::user()->name;
    }

    public function openEditModal(): void
    {
        $this->loadShopData();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->resetValidation();
    }

    public function openPasswordModal(): void
    {
        $this->showPasswordModal = true;
        $this->resetPasswordFields();
    }

    public function closePasswordModal(): void
    {
        $this->showPasswordModal = false;
        $this->resetPasswordFields();
        $this->resetValidation();
    }

    private function resetPasswordFields(): void
    {
        $this->currentPassword = '';
        $this->newPassword = '';
        $this->confirmPassword = '';
    }

    public function saveShopInfo(): void
    {
        $this->validate([
            'shopName' => 'required|string|max:255',
            'shopAddress' => 'required|string|max:500',
            'shopPhone' => 'nullable|string|max:20',
            'shopTaxNumber' => 'nullable|string|max:50',
            'ownerName' => 'required|string|max:255',
        ]);

        try {
            $updateData = [
                'name' => $this->shopName,
                'address' => $this->shopAddress,
                'phone' => $this->shopPhone,
                'tax_number' => $this->shopTaxNumber,
            ];

            // Update shop information
            $this->shop->update($updateData);

            // Update user name
            Auth::user()->update(['name' => $this->ownerName]);

            $this->closeEditModal();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Shop information updated successfully!',
                'sec' => 3000
            ]);

        } catch (\Exception $e) {
            logger()->error('Error updating shop information: ' . $e->getMessage());
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update shop information. Please try again.',
                'sec' => 3000
            ]);
        }
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:8|confirmed:confirmPassword',
            'confirmPassword' => 'required|string|min:8',
        ], [
            'newPassword.confirmed' => 'The new password confirmation does not match.',
            'newPassword.min' => 'The new password must be at least 8 characters.',
        ]);

        try {
            $user = Auth::user();

            // Verify current password
            if (!Hash::check($this->currentPassword, $user->password)) {
                $this->addError('currentPassword', 'The current password is incorrect.');
                return;
            }

            // Update password
            $user->update([
                'password' => Hash::make($this->newPassword)
            ]);

            $this->closePasswordModal();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Password changed successfully!',
                'sec' => 3000
            ]);

        } catch (\Exception $e) {
            logger()->error('Error changing password: ' . $e->getMessage());
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to change password. Please try again.',
                'sec' => 3000
            ]);
        }
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // Address Management Methods
    public function openAddressModal(): void
    {
        $this->resetAddressFields();
        $this->isEditingAddress = false;
        $this->editingAddressId = null;
        $this->showAddressModal = true;
        // Ensure cities are loaded when opening the modal
        if (empty($this->turkishCities)) {
            $this->loadTurkishCities();
        }
    }

    public function closeAddressModal(): void
    {
        $this->showAddressModal = false;
        $this->resetAddressFields();
        $this->resetValidation();
    }

    public function editAddress(int $addressId): void
    {
        $address = $this->shop->addresses()->findOrFail($addressId);

        // Ensure cities are loaded when editing
        if (empty($this->turkishCities)) {
            $this->loadTurkishCities();
        }

        $this->editingAddressId = $address->id;
        $this->addressLabel = $address->label;
        $this->addressLine = $address->address_line;
        $this->addressCity = $address->city;
        $this->addressPostalCode = $address->postal_code ?? '';
        $this->addressState = $address->state ?? '';
        $this->addressLatitude = $address->latitude ? (float) $address->latitude : null;
        $this->addressLongitude = $address->longitude ? (float) $address->longitude : null;
        $this->addressIsPrimary = $address->is_primary;

        // Load districts for the selected city
        $this->cityDistricts = $this->getDistrictsForCity($this->addressCity);

        $this->isEditingAddress = true;
        $this->showAddressModal = true;
    }

    public function deleteAddress(int $addressId): void
    {
        try {
            $address = $this->shop->addresses()->findOrFail($addressId);

            // Don't allow deletion if it's the only address
            if ($this->shop->addresses()->count() <= 1) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Cannot delete the only address. Please add another address first.',
                    'sec' => 3000
                ]);
                return;
            }

            $address->delete();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Address deleted successfully!',
                'sec' => 3000
            ]);

        } catch (\Exception $e) {
            logger()->error('Error deleting address: ' . $e->getMessage());
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to delete address. Please try again.',
                'sec' => 3000
            ]);
        }
    }

    public function setPrimaryAddress(int $addressId): void
    {
        try {
            $address = $this->shop->addresses()->findOrFail($addressId);
            $address->setAsPrimary();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Primary address updated successfully!',
                'sec' => 3000
            ]);

        } catch (\Exception $e) {
            logger()->error('Error setting primary address: ' . $e->getMessage());
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update primary address. Please try again.',
                'sec' => 3000
            ]);
        }
    }

    public function saveAddress(): void
    {
        $this->validate([
            'addressLabel' => 'required|string|max:255',
            'addressLine' => 'required|string|max:500',
            'addressCity' => 'required|string|max:255',
            'addressPostalCode' => 'nullable|string|max:20',
            'addressState' => 'required|string|max:255',
            'addressLatitude' => 'nullable|numeric|between:-90,90',
            'addressLongitude' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $addressData = [
                'label' => $this->addressLabel,
                'address_line' => $this->addressLine,
                'city' => $this->addressCity,
                'postal_code' => $this->addressPostalCode ?: null,
                'state' => $this->addressState ?: null,
                'latitude' => $this->addressLatitude,
                'longitude' => $this->addressLongitude,
                'is_primary' => $this->addressIsPrimary,
            ];

            if ($this->isEditingAddress) {
                $address = $this->shop->addresses()->findOrFail($this->editingAddressId);
                $address->update($addressData);
                $message = 'Address updated successfully!';
            } else {
                $this->shop->addAddress($addressData);
                $message = 'Address added successfully!';
            }

            $this->closeAddressModal();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $message,
                'sec' => 3000
            ]);

        } catch (\Exception $e) {
            logger()->error('Error saving address: ' . $e->getMessage());
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to save address. Please try again.',
                'sec' => 3000
            ]);
        }
    }

    private function resetAddressFields(): void
    {
        $this->addressLabel = '';
        $this->addressLine = '';
        $this->addressCity = '';
        $this->addressPostalCode = '';
        $this->addressState = '';
        $this->addressLatitude = null;
        $this->addressLongitude = null;
        $this->addressIsPrimary = false;
        $this->editingAddressId = null;
        $this->isEditingAddress = false;
        $this->cityDistricts = [];

        // Ensure cities are always loaded
        if (empty($this->turkishCities)) {
            $this->loadTurkishCities();
        }
    }

    private function loadTurkishCities(): void
    {
        $this->turkishCities = [
            'Adana' => 'Adana',
            'Adıyaman' => 'Adıyaman',
            'Afyonkarahisar' => 'Afyonkarahisar',
            'Ağrı' => 'Ağrı',
            'Amasya' => 'Amasya',
            'Ankara' => 'Ankara',
            'Antalya' => 'Antalya',
            'Artvin' => 'Artvin',
            'Aydın' => 'Aydın',
            'Balıkesir' => 'Balıkesir',
            'Bilecik' => 'Bilecik',
            'Bingöl' => 'Bingöl',
            'Bitlis' => 'Bitlis',
            'Bolu' => 'Bolu',
            'Burdur' => 'Burdur',
            'Bursa' => 'Bursa',
            'Çanakkale' => 'Çanakkale',
            'Çankırı' => 'Çankırı',
            'Çorum' => 'Çorum',
            'Denizli' => 'Denizli',
            'Diyarbakır' => 'Diyarbakır',
            'Edirne' => 'Edirne',
            'Elazığ' => 'Elazığ',
            'Erzincan' => 'Erzincan',
            'Erzurum' => 'Erzurum',
            'Eskişehir' => 'Eskişehir',
            'Gaziantep' => 'Gaziantep',
            'Giresun' => 'Giresun',
            'Gümüşhane' => 'Gümüşhane',
            'Hakkari' => 'Hakkari',
            'Hatay' => 'Hatay',
            'Isparta' => 'Isparta',
            'Mersin' => 'Mersin',
            'İstanbul' => 'İstanbul',
            'İzmir' => 'İzmir',
            'Kars' => 'Kars',
            'Kastamonu' => 'Kastamonu',
            'Kayseri' => 'Kayseri',
            'Kırklareli' => 'Kırklareli',
            'Kırşehir' => 'Kırşehir',
            'Kocaeli' => 'Kocaeli',
            'Konya' => 'Konya',
            'Kütahya' => 'Kütahya',
            'Malatya' => 'Malatya',
            'Manisa' => 'Manisa',
            'Kahramanmaraş' => 'Kahramanmaraş',
            'Mardin' => 'Mardin',
            'Muğla' => 'Muğla',
            'Muş' => 'Muş',
            'Nevşehir' => 'Nevşehir',
            'Niğde' => 'Niğde',
            'Ordu' => 'Ordu',
            'Rize' => 'Rize',
            'Sakarya' => 'Sakarya',
            'Samsun' => 'Samsun',
            'Siirt' => 'Siirt',
            'Sinop' => 'Sinop',
            'Sivas' => 'Sivas',
            'Tekirdağ' => 'Tekirdağ',
            'Tokat' => 'Tokat',
            'Trabzon' => 'Trabzon',
            'Tunceli' => 'Tunceli',
            'Şanlıurfa' => 'Şanlıurfa',
            'Uşak' => 'Uşak',
            'Van' => 'Van',
            'Yozgat' => 'Yozgat',
            'Zonguldak' => 'Zonguldak',
            'Aksaray' => 'Aksaray',
            'Bayburt' => 'Bayburt',
            'Karaman' => 'Karaman',
            'Kırıkkale' => 'Kırıkkale',
            'Batman' => 'Batman',
            'Şırnak' => 'Şırnak',
            'Bartın' => 'Bartın',
            'Ardahan' => 'Ardahan',
            'Iğdır' => 'Iğdır',
            'Yalova' => 'Yalova',
            'Karabük' => 'Karabük',
            'Kilis' => 'Kilis',
            'Osmaniye' => 'Osmaniye',
            'Düzce' => 'Düzce'
        ];
    }

    public function updatedAddressCity(): void
    {
        $this->addressState = '';
        $this->cityDistricts = $this->getDistrictsForCity($this->addressCity);
    }





    private function getDistrictsForCity(string $city): array
    {
        $districts = [
            'İstanbul' => [
                'Adalar', 'Arnavutköy', 'Ataşehir', 'Avcılar', 'Bağcılar', 'Bahçelievler', 'Bakırköy', 'Başakşehir', 'Bayrampaşa', 'Beşiktaş', 'Beykoz', 'Beylikdüzü', 'Beyoğlu', 'Büyükçekmece', 'Çatalca', 'Çekmeköy', 'Esenler', 'Esenyurt', 'Eyüpsultan', 'Fatih', 'Gaziosmanpaşa', 'Güngören', 'Kadıköy', 'Kağıthane', 'Kartal', 'Küçükçekmece', 'Maltepe', 'Pendik', 'Sancaktepe', 'Sarıyer', 'Silivri', 'Sultanbeyli', 'Sultangazi', 'Şile', 'Şişli', 'Tuzla', 'Ümraniye', 'Üsküdar', 'Zeytinburnu'
            ],
            'Ankara' => [
                'Akyurt', 'Altındağ', 'Ayaş', 'Bala', 'Beypazarı', 'Çamlıdere', 'Çankaya', 'Çubuk', 'Elmadağ', 'Etimesgut', 'Evren', 'Gölbaşı', 'Güdül', 'Haymana', 'Kalecik', 'Kazan', 'Keçiören', 'Kızılcahamam', 'Mamak', 'Nallıhan', 'Polatlı', 'Pursaklar', 'Sincan', 'Şereflikoçhisar', 'Yenimahalle'
            ],
            'İzmir' => [
                'Aliağa', 'Balçova', 'Bayındır', 'Bayraklı', 'Bergama', 'Beydağ', 'Bornova', 'Buca', 'Çeşme', 'Çiğli', 'Dikili', 'Foça', 'Gaziemir', 'Güzelbahçe', 'Karabağlar', 'Karaburun', 'Karşıyaka', 'Kemalpaşa', 'Kınık', 'Kiraz', 'Konak', 'Menderes', 'Menemen', 'Narlıdere', 'Ödemiş', 'Seferihisar', 'Selçuk', 'Tire', 'Torbalı', 'Urla'
            ],
            'Bursa' => [
                'Büyükorhan', 'Gemlik', 'Gürsu', 'Harmancık', 'İnegöl', 'İznik', 'Karacabey', 'Keles', 'Kestel', 'Mudanya', 'Mustafakemalpaşa', 'Nilüfer', 'Orhaneli', 'Orhangazi', 'Osmangazi', 'Yenişehir', 'Yıldırım'
            ],
            'Antalya' => [
                'Akseki', 'Aksu', 'Alanya', 'Demre', 'Döşemealtı', 'Elmalı', 'Finike', 'Gazipaşa', 'Gündoğmuş', 'İbradı', 'Kaş', 'Kemer', 'Kepez', 'Konyaaltı', 'Korkuteli', 'Kumluca', 'Manavgat', 'Muratpaşa', 'Serik'
            ],
            'Adana' => [
                'Aladağ', 'Ceyhan', 'Çukurova', 'Feke', 'İmamoğlu', 'Karaisalı', 'Karataş', 'Kozan', 'Pozantı', 'Saimbeyli', 'Sarıçam', 'Seyhan', 'Tufanbeyli', 'Yumurtalık', 'Yüreğir'
            ],
            'Konya' => [
                'Ahırlı', 'Akören', 'Akşehir', 'Altınekin', 'Beyşehir', 'Bozkır', 'Cihanbeyli', 'Çeltik', 'Çumra', 'Derbent', 'Derebucak', 'Doğanhisar', 'Emirgazi', 'Ereğli', 'Güneysınır', 'Hadim', 'Halkapınar', 'Hüyük', 'Ilgın', 'Kadınhanı', 'Karapınar', 'Karatay', 'Kulu', 'Meram', 'Sarayönü', 'Selçuklu', 'Seydişehir', 'Taşkent', 'Tuzlukçu', 'Yalıhüyük', 'Yunak'
            ],
            'Gaziantep' => [
                'Araban', 'İslahiye', 'Karkamış', 'Nizip', 'Nurdağı', 'Oğuzeli', 'Şahinbey', 'Şehitkamil', 'Yavuzeli'
            ],
            'Mersin' => [
                'Akdeniz', 'Anamur', 'Aydıncık', 'Bozyazı', 'Çamlıyayla', 'Erdemli', 'Gülnar', 'Mezitli', 'Mut', 'Silifke', 'Tarsus', 'Toroslar', 'Yenişehir'
            ],
            'Diyarbakır' => [
                'Bağlar', 'Bismil', 'Çermik', 'Çınar', 'Çüngüş', 'Dicle', 'Eğil', 'Ergani', 'Hani', 'Hazro', 'Kayapınar', 'Kocaköy', 'Kulp', 'Lice', 'Silvan', 'Sur', 'Yenişehir'
            ],
            'Samsun' => [
                '19 Mayıs', 'Alaçam', 'Asarcık', 'Atakum', 'Ayvacık', 'Bafra', 'Canik', 'Çarşamba', 'Havza', 'İlkadım', 'Kavak', 'Ladik', 'Salıpazarı', 'Tekkeköy', 'Terme', 'Vezirköprü', 'Yakakent'
            ],
            'Denizli' => [
                'Acıpayam', 'Babadağ', 'Baklan', 'Bekilli', 'Beyağaç', 'Bozkurt', 'Buldan', 'Çal', 'Çameli', 'Çardak', 'Çivril', 'Güney', 'Honaz', 'Kale', 'Merkezefendi', 'Pamukkale', 'Sarayköy', 'Serinhisar', 'Tavas'
            ],
            'Eskişehir' => [
                'Alpu', 'Beylikova', 'Çifteler', 'Günyüzü', 'Han', 'İnönü', 'Mahmudiye', 'Mihalgazi', 'Mihalıççık', 'Odunpazarı', 'Sarıcakaya', 'Seyitgazi', 'Sivrihisar', 'Tepebaşı'
            ],
            'Trabzon' => [
                'Akçaabat', 'Araklı', 'Arsin', 'Beşikdüzü', 'Çarşıbaşı', 'Çaykara', 'Dernekpazarı', 'Düzköy', 'Hayrat', 'Köprübaşı', 'Maçka', 'Of', 'Ortahisar', 'Sürmene', 'Şalpazarı', 'Tonya', 'Vakfıkebir', 'Yomra'
            ],
            'Erzurum' => [
                'Aşkale', 'Aziziye', 'Çat', 'Hınıs', 'Horasan', 'İspir', 'Karaçoban', 'Karayazı', 'Köprüköy', 'Narman', 'Oltu', 'Olur', 'Palandöken', 'Pasinler', 'Pazaryolu', 'Şenkaya', 'Tekman', 'Tortum', 'Uzundere', 'Yakutiye'
            ],
            'Van' => [
                'Bahçesaray', 'Başkale', 'Çaldıran', 'Çatak', 'Edremit', 'Erciş', 'Gevaş', 'Gürpınar', 'İpekyolu', 'Muradiye', 'Özalp', 'Saray', 'Tuşba'
            ],
            'Kayseri' => [
                'Akkışla', 'Bünyan', 'Develi', 'Felahiye', 'Hacılar', 'İncesu', 'Kocasinan', 'Melikgazi', 'Özvatan', 'Pınarbaşı', 'Sarıoğlan', 'Sarız', 'Talas', 'Tomarza', 'Yahyalı', 'Yeşilhisar'
            ],
            'Manisa' => [
                'Ahmetli', 'Akhisar', 'Alaşehir', 'Demirci', 'Gölmarmara', 'Gördes', 'Kırkağaç', 'Köprübaşı', 'Kula', 'Salihli', 'Sarıgöl', 'Saruhanlı', 'Selendi', 'Soma', 'Şehzadeler', 'Turgutlu', 'Yunusemre'
            ],
            'Sivas' => [
                'Akıncılar', 'Altınyayla', 'Divriği', 'Doğanşar', 'Gemerek', 'Gölova', 'Hafik', 'İmranlı', 'Kangal', 'Koyulhisar', 'Merkez', 'Şarkışla', 'Suşehri', 'Ulaş', 'Yıldızeli', 'Zara'
            ],
            'Balıkesir' => [
                'Altıeylül', 'Ayvalık', 'Balya', 'Bandırma', 'Bigadiç', 'Burhaniye', 'Dursunbey', 'Edremit', 'Erdek', 'Gönen', 'Havran', 'İvrindi', 'Karesi', 'Kepsut', 'Manyas', 'Savaştepe', 'Sındırgı', 'Gömeç', 'Susurluk', 'Marmara'
            ],
            'Kahramanmaraş' => [
                'Afşin', 'Andırın', 'Çağlayancerit', 'Dulkadiroğlu', 'Ekinözü', 'Elbistan', 'Göksun', 'Nurhak', 'Onikişubat', 'Pazarcık', 'Türkoğlu'
            ],
            'Aydın' => [
                'Bozdoğan', 'Çine', 'Germencik', 'Karacasu', 'Karpuzlu', 'Koçarlı', 'Köşk', 'Kuşadası', 'Kuyucak', 'Nazilli', 'Söke', 'Sultanhisar', 'Yenipazar', 'Buharkent', 'İncirliova', 'Kuyucak', 'Didim'
            ],
            'Tekirdağ' => [
                'Çerkezköy', 'Çorlu', 'Ergene', 'Hayrabolu', 'Kapaklı', 'Malkara', 'Marmaraereğlisi', 'Muratlı', 'Saray', 'Süleymanpaşa', 'Şarköy'
            ],
            'Muğla' => [
                'Bodrum', 'Dalaman', 'Datça', 'Fethiye', 'Kavaklıdere', 'Köyceğiz', 'Marmaris', 'Menteşe', 'Milas', 'Ortaca', 'Seydikemer', 'Ula', 'Yatağan'
            ],
            'Kocaeli' => [
                'Başiskele', 'Çayırova', 'Darıca', 'Derince', 'Dilovası', 'Gebze', 'Gölcük', 'İzmit', 'Kandıra', 'Karamürsel', 'Kartepe', 'Körfez'
            ],
            'Sakarya' => [
                'Adapazarı', 'Akyazı', 'Arifiye', 'Erenler', 'Ferizli', 'Geyve', 'Hendek', 'Karapürçek', 'Karasu', 'Kaynarca', 'Kocaali', 'Pamukova', 'Sapanca', 'Serdivan', 'Söğütlü', 'Taraklı'
            ],
            'Hatay' => [
                'Altınözü', 'Antakya', 'Belen', 'Defne', 'Dörtyol', 'Erzin', 'Hassa', 'İskenderun', 'Kırıkhan', 'Kumlu', 'Payas', 'Reyhanlı', 'Samandağ', 'Yayladağı'
            ],
            'Kırıkkale' => [
                'Bahşılı', 'Balışeyh', 'Çelebi', 'Delice', 'Karakeçili', 'Keskin', 'Sulakyurt', 'Yahşihan'
            ],
            'Aksaray' => [
                'Ağaçören', 'Eskil', 'Gülağaç', 'Güzelyurt', 'Merkez', 'Ortaköy', 'Sarıyahşi'
            ],
            'Afyonkarahisar' => [
                'Başmakçı', 'Bayat', 'Bolvadin', 'Çay', 'Çobanlar', 'Dazkırı', 'Dinar', 'Emirdağ', 'Evciler', 'Hocalar', 'İhsaniye', 'İscehisar', 'Kızılören', 'Merkez', 'Sandıklı', 'Sinanpaşa', 'Sultandağı', 'Şuhut'
            ],
            'Isparta' => [
                'Aksu', 'Atabey', 'Eğirdir', 'Gelendost', 'Gönen', 'Keçiborlu', 'Merkez', 'Senirkent', 'Sütçüler', 'Şarkikaraağaç', 'Uluborlu', 'Yalvaç', 'Yenişarbademli'
            ],
            'Malatya' => [
                'Akçadağ', 'Arapgir', 'Arguvan', 'Battalgazi', 'Darende', 'Doğanşehir', 'Doğanyol', 'Hekimhan', 'Kale', 'Kuluncak', 'Pütürge', 'Yazıhan', 'Yeşilyurt'
            ],
            'Elazığ' => [
                'Ağın', 'Alacakaya', 'Arıcak', 'Baskil', 'Karakoçan', 'Keban', 'Kovancılar', 'Maden', 'Merkez', 'Palu', 'Sivrice'
            ],
            'Tunceli' => [
                'Çemişgezek', 'Hozat', 'Mazgirt', 'Merkez', 'Nazımiye', 'Ovacık', 'Pertek', 'Pülümür'
            ],
            'Bingöl' => [
                'Genç', 'Karlıova', 'Kiğı', 'Merkez', 'Solhan', 'Yayladere', 'Yedisu'
            ],
            'Bitlis' => [
                'Adilcevaz', 'Ahlat', 'Güroymak', 'Hizan', 'Merkez', 'Mutki', 'Tatvan'
            ],
            'Muş' => [
                'Bulanık', 'Hasköy', 'Korkut', 'Malazgirt', 'Merkez', 'Varto'
            ],
            'Hakkari' => [
                'Çukurca', 'Derecik', 'Merkez', 'Şemdinli', 'Yüksekova'
            ],
            'Şırnak' => [
                'Beytüşşebap', 'Cizre', 'Güçlükonak', 'İdil', 'Merkez', 'Silopi', 'Uludere'
            ],
            'Batman' => [
                'Beşiri', 'Gercüş', 'Hasankeyf', 'Kozluk', 'Merkez', 'Sason'
            ],
            'Siirt' => [
                'Baykan', 'Eruh', 'Kurtalan', 'Merkez', 'Pervari', 'Şirvan', 'Tillo'
            ],
            'Mardin' => [
                'Artuklu', 'Dargeçit', 'Derik', 'Kızıltepe', 'Mazıdağı', 'Midyat', 'Nusaybin', 'Ömerli', 'Savur', 'Yeşilli'
            ],
            'Şanlıurfa' => [
                'Akçakale', 'Birecik', 'Bozova', 'Ceylanpınar', 'Eyyübiye', 'Halfeti', 'Haliliye', 'Harran', 'Hilvan', 'Karaköprü', 'Siverek', 'Suruç', 'Viranşehir'
            ],
            'Adıyaman' => [
                'Besni', 'Çelikhan', 'Gerger', 'Gölbaşı', 'Kahta', 'Merkez', 'Samsat', 'Sincik', 'Tut'
            ],
            'Kilis' => [
                'Elbeyli', 'Merkez', 'Musabeyli', 'Polateli'
            ],
            'Osmaniye' => [
                'Bahçe', 'Düziçi', 'Hasanbeyli', 'Kadirli', 'Merkez', 'Sumbas', 'Toprakkale'
            ],
            'K.Maraş' => [
                'Afşin', 'Andırın', 'Çağlayancerit', 'Dulkadiroğlu', 'Ekinözü', 'Elbistan', 'Göksun', 'Nurhak', 'Onikişubat', 'Pazarcık', 'Türkoğlu'
            ]
        ];

        return $districts[$city] ?? [];
    }

    // Order Management Methods
    public function showOrderDetails(int $orderId): void
    {
        $this->selectedOrder = \App\Models\Order::with(['items.product', 'shop', 'customer'])
            ->where('shop_id', $this->shop->id)
            ->findOrFail($orderId);
        $this->showOrderDetailsModal = true;
    }

    public function closeOrderDetailsModal(): void
    {
        $this->showOrderDetailsModal = false;
        $this->selectedOrder = null;
    }

    public function exportOrderToPdf(int $orderId, int $shopId)
    {
        try {
            $order = \App\Models\Order::with(['items.product', 'shop', 'customer'])
                ->where('shop_id', $shopId)
                ->findOrFail($orderId);

            $customer = new \LaravelDaily\Invoices\Classes\Buyer([
                'serial' => $order->id,
                'date' => $order->updated_at->format('d M Y'),
                'invoice_records' => $order->items,
                'total' => $order->total_price
            ]);

            $invoice = \LaravelDaily\Invoices\Invoice::make()
                ->template('indirimgo')
                ->buyer($customer)
                ->discountByPercent(10)
                ->taxRate(18)
                ->addItem((new \LaravelDaily\Invoices\Classes\InvoiceItem())->pricePerUnit(2));

            $html = view('vendor.invoices.templates.indirimgo', compact('invoice'))->render();

            return response()->stream(function () use ($html) {
                print \Spatie\Browsershot\Browsershot::html($html)
                    ->format('A4')
                    ->noSandbox()
                    ->waitUntilNetworkIdle()
                    ->showBackground()
                    ->pdf();
            }, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="order-' . $orderId . '.pdf"',
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to export order: ' . $e->getMessage()
            ]);

            dd($e->getMessage());
        }
    }

    #[Layout('layouts.frontend')]
    #[Title('Shop Owner Profile')]
    public function render()
    {
        return view('livewire.frontend.shop-owner-profile');
    }
}
