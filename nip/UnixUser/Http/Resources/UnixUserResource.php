<?php

namespace Nip\UnixUser\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\UnixUser\Models\UnixUser;

/**
 * @mixin UnixUser
 */
class UnixUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'status' => $this->status,
            'displayableStatus' => $this->status->label(),
            'createdAt' => $this->created_at?->toISOString(),
            'can' => [
                'delete' => $this->username !== 'netipar',
            ],
        ];
    }
}
