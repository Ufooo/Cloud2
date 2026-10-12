<?php

namespace Nip\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Nip\Security\Models\SecurityRuleCredential;

/**
 * @extends Factory<SecurityRuleCredential>
 */
class SecurityRuleCredentialFactory extends Factory
{
    /**
     * The plain-text password behind the default hash.
     */
    public const DEFAULT_PASSWORD = 'secret-password';

    /**
     * bcrypt cost of stored credentials. nginx re-verifies the hash on every request, so cost 12 would
     * add ~333 ms to each asset; cost 5 is ~2.6 ms. Mirrors the controller contract (spec decision D2).
     */
    public const HASH_ROUNDS = 5;

    protected $model = SecurityRuleCredential::class;

    /**
     * The password column holds what production stores and what lands in the htpasswd file:
     * a "$2y$05$..." bcrypt hash that nginx' crypt() accepts, never plain text and never argon2.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'security_rule_id' => SecurityRuleFactory::new(),
            'username' => fake()->unique()->userName(),
            'password' => self::hash(self::DEFAULT_PASSWORD),
        ];
    }

    /**
     * Store the given plain-text password the way the controller does (explicit bcrypt, cost 5).
     */
    public function withPassword(string $plain): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => self::hash($plain),
        ]);
    }

    private static function hash(string $plain): string
    {
        return Hash::driver('bcrypt')->make($plain, ['rounds' => self::HASH_ROUNDS]);
    }
}
