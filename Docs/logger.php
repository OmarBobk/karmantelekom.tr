<?php
//
//public function updateProduct()
//{
//    DB::enableQueryLog();
//    $this->validate();
//
//    try {
//        DB::beginTransaction();
//
//        // Log the start of transaction
//        logger()->info('Starting product update transaction', [
//            'product_id' => $this->editingProduct->id,
//            'form_data' => $this->editForm
//        ]);
//
//        $this->editingProduct->update([
//            'name' => $this->editForm['name'],
//            'slug' => $this->editForm['slug'],
//            'serial' => $this->editForm['serial'],
//            'code' => $this->editForm['code'],
//            'status' => $this->editForm['status'],
//            'description' => $this->editForm['description'],
//            'category_id' => $this->editForm['category_id'],
//            'supplier_id' => $this->editForm['supplier_id']
//        ]);
//
//        // Log after basic update
//        logger()->info('Basic product details updated', ['product_id' => $this->editingProduct->id]);
//
//        // Update prices
//        $this->editingProduct->prices()->delete();
//        foreach ($this->editForm['prices'] as $price) {
//            $this->editingProduct->prices()->create([
//                'price' => $price['price'],
//                'currency' => $price['currency']
//            ]);
//        }
//
//        // Log after prices update
//        logger()->info('Product prices updated', [
//            'product_id' => $this->editingProduct->id,
//            'prices' => $this->editForm['prices']
//        ]);
//
//        // Handle new images
//        if (!empty($this->newImages)) {
//            foreach ($this->newImages as $image) {
//                $path = $image->store('products', 'public');
//                $this->editingProduct->images()->create([
//                    'url' => $path,
//                    'is_primary' => false
//                ]);
//            }
//            logger()->info('New images added', [
//                'product_id' => $this->editingProduct->id,
//                'image_count' => count($this->newImages)
//            ]);
//        }
//
//        // Update tags
//        $this->editingProduct->tags()->sync($this->editForm['tags']);
//        logger()->info('Tags synced', [
//            'product_id' => $this->editingProduct->id,
//            'tags' => $this->editForm['tags']
//        ]);
//
//        DB::commit();
//        logger()->info('Transaction committed successfully', ['product_id' => $this->editingProduct->id]);
//
//        $this->editModalOpen = false;
//        $this->reset(['newImages']);
//
//        $this->dispatch('notify', [
//            'type' => 'success',
//            'message' => 'Product updated successfully!'
//        ]);
//
//        logger()->info('SQL Queries:', ['queries' => DB::getQueryLog()]);
//    } catch (\Exception $e) {
//        DB::rollBack();
//        logger()->error('Error updating product', [
//            'product_id' => $this->editingProduct->id,
//            'error' => $e->getMessage(),
//            'trace' => $e->getTraceAsString()
//        ]);
//
//        $errorMessage = app()->environment('local')
//            ? 'Error: ' . $e->getMessage()
//            : 'Error updating product. Please try again.';
//
//        $this->dispatch('notify', [
//            'type' => 'error',
//            'message' => $errorMessage
//        ]);
//    }
//}
