<?php

namespace Nip\SshKey\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\SshKey\Models\SshKey;

/**
 * @mixin SshKey
 */
class SshKeyResource extends JsonResource
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
            'unixUser' => $this->whenLoaded('unixUser', fn () => [
                'id' => $this->unixUser->id,
                'username' => $this->unixUser->username,
            ]),
            'can' => [
                'delete' => $request->user()->can('update', $this->server),
            ],
        ];
    }
}
