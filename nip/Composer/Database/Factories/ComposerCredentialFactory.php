<?php

namespace Nip\Composer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nip\Composer\Models\ComposerCredential;
use Nip\Site\Models\Site;
use Nip\UnixUser\Models\UnixUser;

/**
 * @extends Factory<ComposerCredential>
 */
class ComposerCredentialFactory extends Factory
{
    protected $model = ComposerCredential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unix_user_id' => UnixUser::factory(),
            'site_id' => null,
            'repository' => $this->faker->domainName(),
            'username' => $this->faker->userName(),
            'password' => $this->faker->password(),
        ];
    }

    public function forSite(Site $site): static
    {
        return $this->state(fn () => [
            'unix_user_id' => $site->unix_user_id,
            'site_id' => $site->id,
        ]);
    }

    public function forUnixUser(UnixUser $unixUser): static
    {
        return $this->state(fn () => [
            'unix_user_id' => $unixUser->id,
            'site_id' => null,
        ]);
    }
}
