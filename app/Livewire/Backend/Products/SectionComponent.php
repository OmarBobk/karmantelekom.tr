<?php
declare(strict_types=1);

namespace App\Livewire\Backend\Products;

use App\Models\Product;
use App\Models\Section;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

class SectionComponent extends Component
{
    use WithPagination;

    public $showModal = false;
    public $editingSection = null;
    public $selectedProducts = [];
    public $searchTerm = '';
    
    // Form properties
    public $sectionId;
    public $name = '';
    public $description = '';
    public $order = 0;
    public $position = 'main';
    public $is_active = true;

    protected $rules = [
        'name' => 'required|min:3|max:255',
        'description' => 'nullable|max:1000',
        'order' => 'required|integer|min:0',
        'position' => 'required|in:main,sidebar,footer',
        'is_active' => 'boolean'
    ];

    public function mount()
    {
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset(['name', 'description', 'order', 'position', 'is_active', 'sectionId', 'selectedProducts']);
        $this->editingSection = null;
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(Section $section)
    {
        $this->editingSection = $section;
        $this->sectionId = $section->id;
        $this->name = $section->name;
        $this->description = $section->description;
        $this->order = $section->order;
        $this->position = $section->position;
        $this->is_active = $section->is_active;
        $this->selectedProducts = $section->products->pluck('id')->toArray();
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $section = $this->editingSection ?? new Section();
        $section->fill([
            'name' => $this->name,
            'description' => $this->description,
            'order' => $this->order,
            'position' => $this->position,
            'is_active' => $this->is_active,
        ]);
        $section->save();

        // Sync products
        $section->products()->sync($this->selectedProducts);

        $this->dispatch('notify', [
            [
                'message' => $this->editingSection ? 'Section updated successfully!' : 'Section created successfully!',
                'type' => 'success'
            ]
        ]);

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(Section $section)
    {
        $section->delete();
        $this->dispatch('notify', [
            [
                'message' => 'Section deleted successfully!',
                'type' => 'success'
            ]
        ]);
    }

    public function toggleActive(Section $section)
    {
        $section->is_active = !$section->is_active;
        $section->save();
        
        $this->dispatch('section-updated', sectionId: $section->id);
    }

    #[On('section-updated')] 
    public function refreshSection($sectionId)
    {
        // Only refresh the specific section that changed
    }

    #[Computed]
    public function sections()
    {
        return Section::with('products')
            ->when($this->searchTerm, function ($query) {
                $query->where('name', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('description', 'like', '%' . $this->searchTerm . '%');
            })
            ->orderBy('order')
            ->paginate(10);
    }

    #[Layout('layouts.backend')]
    public function render()
    {
        $sections = Section::with('products')
            ->when($this->searchTerm, function ($query) {
                $query->where('name', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('description', 'like', '%' . $this->searchTerm . '%');
            })
            ->orderBy('order')
            ->paginate(10);

        $products = Product::when($this->searchTerm, function ($query) {
            $query->where('name', 'like', '%' . $this->searchTerm . '%')
                ->orWhere('code', 'like', '%' . $this->searchTerm . '%');
        })->get();

        return view('livewire.backend.products.section-component', [
            'sections' => $sections,
            'products' => $products
        ]);
    }
}
