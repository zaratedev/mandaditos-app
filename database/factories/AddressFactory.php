<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Address;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'label' => fake()->randomElement(['Casa', 'Trabajo', null]),
            'street' => fake()->streetAddress(),
            'neighborhood' => fake()->citySuffix(),
            'city' => fake()->city(),
            'landmark' => fake()->sentence(4),
            'notes' => null,
        ];
    }
}
