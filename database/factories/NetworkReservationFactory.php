<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\NetworkReservation;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Units and listings are created through their actions, so callers pass `network_listing_id`,
 * `listing_revision`, `inventory_unit_id`, `product_definition_id`, and `owning_store_id`.
 *
 * @extends Factory<NetworkReservation>
 */
class NetworkReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requesting_store_id' => Store::factory(),
            'status' => ReservationStatus::PENDING,
            'requested_by' => User::factory(),
            'requested_at' => now(),
        ];
    }
}
