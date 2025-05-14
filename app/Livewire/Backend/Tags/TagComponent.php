<?php
declare(strict_types=1);

namespace App\Livewire\Backend\Tags;

use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class TagComponent extends Component
{
    use WithFileUploads;
    use WithPagination;

    public bool $addModalOpen = false;
    public bool $editModalOpen = false;
    public bool $showDeleteModal = false;
    public bool $showBulkActionModal = false;
    public ?int $tagToDelete = null;
    public array $selectedTags = [];
    public bool $selectAll = false;
    public string $search = '';
    public string $bulkAction = '';
    public string $bulkActionMessage = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public array $addForm = [
        'name' => '',
        'tr_name' => '',
        'ar_name' => '',
        'text_color' => '#000000',
        'background_color' => '#FFFFFF',
        'border_color' => '#000000',
        'icon' => '',
        'is_active' => true,
    ];

    public array $editForm = [
        'id' => null,
        'name' => '',
        'tr_name' => '',
        'ar_name' => '',
        'text_color' => '#000000',
        'background_color' => '#FFFFFF',
        'border_color' => '#000000',
        'icon' => '',
        'is_active' => true,
    ];

    public function mount(): void
    {
        $this->initializeAddForm();
    }

    private function initializeAddForm(): void
    {
        $this->addForm = [
            'name' => '',
            'tr_name' => '',
            'ar_name' => '',
            'text_color' => '#000000',
            'background_color' => '#FFFFFF',
            'border_color' => '#000000',
            'icon' => '',
            'is_active' => true,
        ];
    }

    public function updatingAddModalOpen(): void
    {
        if ($this->addModalOpen) {
            $this->initializeAddForm();
        }
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedTags = $this->getTags()->pluck('id')->toArray();
        } else {
            $this->selectedTags = [];
        }
    }

    public function confirmDelete(int $tagId): void
    {
        $this->tagToDelete = $tagId;
        $this->showDeleteModal = true;
    }

    public function deleteTag(): void
    {
        if ($this->tagToDelete) {
            $tag = Tag::find($this->tagToDelete);
            if ($tag) {
                $tag->delete();
                $this->showDeleteModal = false;
                $this->tagToDelete = null;
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Tag deleted successfully.'
                ]);
            }
        }
    }

    public function editTag(int $tagId): void
    {
        $tag = Tag::find($tagId);
        if ($tag) {
            $this->editForm = [
                'id' => $tag->id,
                'name' => $tag->name,
                'tr_name' => $tag->tr_name,
                'ar_name' => $tag->ar_name,
                'text_color' => $tag->text_color,
                'background_color' => $tag->background_color,
                'border_color' => $tag->border_color,
                'icon' => $tag->icon,
                'is_active' => $tag->is_active,
            ];
            $this->editModalOpen = true;
        }
    }

    public function updateTag(): void
    {
        try {
            $this->validate($this->getEditRules());

            $tag = Tag::find($this->editForm['id']);
            if ($tag) {
                $tag->update([
                    'name' => $this->editForm['name'],
                    'tr_name' => $this->editForm['tr_name'],
                    'ar_name' => $this->editForm['ar_name'],
                    'text_color' => $this->editForm['text_color'],
                    'background_color' => $this->editForm['background_color'],
                    'border_color' => $this->editForm['border_color'],
                    'icon' => $this->editForm['icon'],
                    'is_active' => $this->editForm['is_active'],
                ]);

                $this->editModalOpen = false;
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Tag updated successfully.'
                ]);
            }
        } catch (Throwable $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
            return;
        }
    }

    private function getTags(): LengthAwarePaginator
    {
        return Tag::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }

    public function createTag(): void
    {
        $this->validate($this->getAddRules());

        // Generate a slug from the name
        $slug = str($this->addForm['name'])->slug()->toString();

        Tag::create([
            'name' => $this->addForm['name'],
            'tr_name' => $this->addForm['tr_name'],
            'ar_name' => $this->addForm['ar_name'],
            'slug' => $slug,
            'text_color' => $this->addForm['text_color'],
            'background_color' => $this->addForm['background_color'],
            'border_color' => $this->addForm['border_color'],
            'icon' => $this->addForm['icon'],
            'is_active' => $this->addForm['is_active'],
        ]);

        $this->addModalOpen = false;
        $this->initializeAddForm();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Tag created successfully.'
        ]);
    }

    protected function getAddRules(): array
    {
        return [
            'addForm.name' => 'required|min:2|max:255',
            'addForm.tr_name' => 'nullable|min:2|max:255',
            'addForm.ar_name' => 'nullable|min:2|max:255',
            'addForm.text_color' => 'required|regex:/^#[0-9A-F]{6}$/i',
            'addForm.background_color' => 'required|regex:/^#[0-9A-F]{6}$/i',
            'addForm.border_color' => 'nullable|regex:/^#[0-9A-F]{6}$/i',
            'addForm.icon' => 'nullable|max:255',
            'addForm.is_active' => 'boolean',
        ];
    }

    protected function getEditRules(): array
    {
        return [
            'editForm.name' => 'required|min:2|max:255',
            'editForm.tr_name' => 'nullable|min:2|max:255',
            'editForm.ar_name' => 'nullable|min:2|max:255',
            'editForm.text_color' => 'required|regex:/^#[0-9A-F]{6}$/i',
            'editForm.background_color' => 'required|regex:/^#[0-9A-F]{6}$/i',
            'editForm.border_color' => 'nullable|regex:/^#[0-9A-F]{6}$/i',
            'editForm.icon' => 'nullable|max:255',
            'editForm.is_active' => 'boolean',
        ];
    }

    public function processBulkAction(): void
    {
        if (empty($this->selectedTags)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Please select at least one tag.'
            ]);
            return;
        }

        switch ($this->bulkAction) {
            case 'delete':
                $this->bulkActionMessage = 'Are you sure you want to delete the selected tags? This action cannot be undone.';
                $this->showBulkActionModal = true;
                break;
            case 'activate':
                Tag::whereIn('id', $this->selectedTags)->update(['is_active' => true]);
                $this->selectedTags = [];
                $this->selectAll = false;
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Selected tags activated successfully.'
                ]);
                break;
            case 'deactivate':
                Tag::whereIn('id', $this->selectedTags)->update(['is_active' => false]);
                $this->selectedTags = [];
                $this->selectAll = false;
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Selected tags deactivated successfully.'
                ]);
                break;
        }
    }

    public function confirmBulkAction(): void
    {
        if ($this->bulkAction === 'delete') {
            Tag::whereIn('id', $this->selectedTags)->delete();
            $this->selectedTags = [];
            $this->selectAll = false;
            $this->showBulkActionModal = false;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Selected tags deleted successfully.'
            ]);
        }
    }

    public function cancelBulkAction(): void
    {
        $this->showBulkActionModal = false;
        $this->bulkAction = '';
    }

    public function toggleStatus(int $tagId): void
    {
        try {
            $tag = Tag::findOrFail($tagId);
            $tag->is_active = !$tag->is_active;
            $tag->save();

            $status = $tag->is_active ? 'activated' : 'deactivated';
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Tag {$status} successfully."
            ]);
        } catch (Throwable $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update tag status: ' . $e->getMessage(),
            ]);
        }
    }

    #[Layout('layouts.backend')]
    #[Title('Tags Manager')]
    public function render(): View
    {
        return view('livewire.backend.tags.tag-component', [
            'tags' => $this->getTags()
        ]);
    }
}
