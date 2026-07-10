<?php declare(strict_types=1);

namespace OpenSearch\ScoutDriver\Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use OpenSearch\ScoutDriver\Tests\App\Client;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @var class-string<Client>
     */
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'phone_number' => $this->faker->unique()->e164PhoneNumber(),
            'email' => $this->faker->unique()->email(),
        ];
    }
}
