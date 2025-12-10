<?php

namespace Nip\SshKey\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\SshKey\Models\UserSshKey;

/**
 * @mixin UserSshKey
 */
class UserSshKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fingerprint' => $this->fingerprint,
            'createdAt' => $this->created_at?->format('M j, Y'),
        ];
    }
}
