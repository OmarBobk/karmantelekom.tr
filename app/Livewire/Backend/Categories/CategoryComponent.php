<?php

namespace App\Livewire\Backend\Categories;

use App\Models\Category;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryComponent extends Component
{
    use WithPagination, WithFileUploads;

    #[Layout('layouts.backend')]

    public $search = '';
    public $status = '';
    public $dateFilter = '';
    public $selectedCategories = [];
    public $selectAll = false;
    public $addModalOpen = false;
    public $editModalOpen = false;
    public $sortField = 'name';
    public $sortDirection = 'asc';
    public $expandedCategories = [];
    public $orderBy = 'newest';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'dateFilter' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
        'orderBy' => ['except' => 'newest'],
    ];

    public $addForm = [
        'name' => '',
        'parent_id' => null,
        'status' => true,
    ];

    public $editForm = [
        'id' => null,
        'name' => '',
        'parent_id' => null,
        'status' => true,
    ];

    public function mount()
    {
        $this->selectedCategories = collect();
    }

    public function toggleCategory($categoryId)
    {
        if (in_array($categoryId, $this->expandedCategories)) {
            $this->expandedCategories = array_diff($this->expandedCategories, [$categoryId]);
        } else {
            $this->expandedCategories[] = $categoryId;
        }
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedCategories = $this->categories->pluck('id')->toArray();
        } else {
            $this->selectedCategories = [];
        }
    }

    public function updatedSelectedCategories()
    {
        $this->selectAll = count($this->selectedCategories) === $this->categories->count();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function editCategory($categoryId)
    {
        $category = Category::find($categoryId);
        $this->editForm = [
            'id' => $category->id,
            'name' => $category->name,
            'parent_id' => $category->parent_id,
            'status' => $category->status,
        ];
        $this->editModalOpen = true;
    }

    public function updateCategory()
    {
        $this->validate([
            'editForm.name' => 'required|string|max:255',
            'editForm.parent_id' => 'nullable|exists:categories,id',
            'editForm.status' => 'boolean',
        ]);

        $category = Category::find($this->editForm['id']);
        $category->update($this->editForm);

        $this->reset(['editForm', 'editModalOpen']);
    }

    public function resetAddForm()
    {
        $this->addForm = [
            'name' => '',
            'parent_id' => null,
            'status' => true,
        ];
        $this->addModalOpen = false;
    }

    public function createCategory()
    {
        $this->validate([
            'addForm.name' => 'required|string|max:255',
            'addForm.parent_id' => 'nullable|exists:categories,id',
            'addForm.status' => 'boolean',
        ]);

        Category::create($this->addForm);

        // Refresh the parent categories list
        $this->dispatch('refreshParentCategories');

        $this->resetAddForm();
    }


    public function getParentCategoriesProperty()
    {
        return Category::where('id', '!=', $this->editForm['id'] ?? null)
            ->orderBy('name', 'asc')
            ->get();
    }

    private function prepareCategories()
    {
        return Category::with('children')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->when($this->status !== '', function ($query) {
                $query->where('status', $this->status === 'active');
            })
            ->when($this->orderBy === 'newest', function ($query) {
                $query->orderBy('created_at', 'desc');
            })
            ->when($this->orderBy === 'oldest', function ($query) {
                $query->orderBy('created_at', 'asc');
            })
            ->orderBy($this->sortField, $this->sortDirection);
    }

    public function getCategoriesProperty()
    {
        $query = $this->prepareCategories();

        return $query->paginate(10);
    }

    public function getCategoryTreeProperty(): LengthAwarePaginator
    {
        $query = $this->prepareCategories();

        $categories = $query->get();
        $tree = $this->buildTree($categories->whereNull('parent_id'));

        // Convert to paginator
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $items = $tree->forPage($page, $perPage);
        $total = $tree->count();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    protected function buildTree($categories, $level = 0)
    {
        $result = collect();

        foreach ($categories as $category) {
            $category->level = $level;
            $result->push($category);

            if (in_array($category->id, $this->expandedCategories) && $category->children->isNotEmpty()) {
                $result = $result->merge($this->buildTree($category->children, $level + 1));
            }
        }

        return $result;
    }

    public function render()
    {
        return view('livewire.backend.categories.category-component', [
            'categories' => $this->categoryTree,
            'parentCategories' => $this->parentCategories
        ]);
    }

    public function toggleStatus($categoryId)
    {
        $category = Category::find($categoryId);
        $category->update(['status' => !$category->status]);
    }

    public function toggleEditFormStatus(): void
    {
        $this->editForm['status'] = !$this->editForm['status'];
    }

    public function toggleAddFormStatus(): void
    {
        $this->addForm['status'] = !$this->addForm['status'];
    }

    public Category $categoryToDelete;
    public Collection $categoriesToDelete;
    public Collection $productsToDelete;
    public bool $showDeleteModal = false;

    protected $listeners = ['confirmCategoryDelete' => 'openDeleteModal'];

    #[On('confirmCategoryDelete')]
    public function openDeleteModal(Category $category): void
    {
        $this->categoryToDelete = $category;
        $this->categoriesToDelete = $this->getCascadeCategories($category);

        $this->productsToDelete = $this->getCascadeProducts($this->categoriesToDelete);
        $this->showDeleteModal = true;
    }

    public function deleteCategory(): void
    {
        $this->categoryToDelete->delete();
        $this->showDeleteModal = false;
        $this->dispatch('categoryDeleted');
    }

    public function getCascadeCategories(Category $category): Collection
    {
        // Recursively collect all descendant categories
        $categories = collect([$category]);
        foreach ($category->children as $child) {
            $categories = $categories->merge($this->getCascadeCategories($child));
        }
        return $categories;
    }

    public function getCascadeProducts(Collection $categories): Collection
    {
        return $categories->flatMap(fn ($cat) => $cat->products);
    }

    public function getCategoryTree(): array
    {
        return Category::with('children')->whereNull('parent_id')->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'children' => $category->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'children' => $child->children->map(function ($grandChild) {
                            return [
                                'id' => $grandChild->id,
                                'name' => $grandChild->name
                            ];
                        })->toArray()
                    ];
                })->toArray()
            ];
        })->toArray();
    }

}
