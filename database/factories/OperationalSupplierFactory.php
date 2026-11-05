<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OperationalSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalSupplier>
 */
class OperationalSupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'contact_name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'tax_number' => null,
            'address' => $this->faker->address(),
            'notes' => null,
            'is_active' => true,
        ];
    }
}
