<?php

namespace Nip\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Security\Enums\SecurityRuleStatus;
use Nip\Security\Models\SecurityRule;
use Nip\Site\Models\Site;

/**
 * @extends Factory<SecurityRule>
 */
class SecurityRuleFactory extends Factory
{
    protected $model = SecurityRule::class;

    /**
     * A live, site-wide rule (no path). Credentials are opt-in: see withCredentials().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => fake()->randomElement(['Staging area', 'Client preview', 'Admin area', 'Private beta']),
            'path' => null,
            'status' => SecurityRuleStatus::Installed,
        ];
    }

    /**
     * Protect only this path. Pass it the way it is stored (normalized): "/admin", never "/admin/".
     */
    public function withPath(string $path): static
    {
        return $this->state(fn (array $attributes) => [
            'path' => $path,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SecurityRuleStatus::Pending,
        ]);
    }

    public function installing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SecurityRuleStatus::Installing,
        ]);
    }

    public function installed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SecurityRuleStatus::Installed,
        ]);
    }

    public function updating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SecurityRuleStatus::Updating,
        ]);
    }

    public function removing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SecurityRuleStatus::Removing,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SecurityRuleStatus::Failed,
        ]);
    }

    /**
     * Attach credentials with real bcrypt hashes. Referencing the credential factory directly
     * (instead of through the model) keeps this working independently of the models' HasFactory wiring.
     */
    public function withCredentials(int $count = 1): static
    {
        return $this->has(SecurityRuleCredentialFactory::new()->count($count), 'credentials');
    }
}
