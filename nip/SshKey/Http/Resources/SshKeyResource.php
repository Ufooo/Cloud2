<?php

namespace Nip\SshKey\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Nip\UnixUser\Http\Resources\UnixUserResource;

class SshKeyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fingerprint' => $this->fingerprint,
            'createdAt' => $this->created_at->format('Y-m-d H:i:s'),
            'unixUser' => $this->whenLoaded('unixUser', fn () => $this->unixUser ? UnixUserResource::make($this->unixUser) : null),
            'can' => [
                'delete' => $request->user()->can('update', $this->server),
            ],
        ];
    }
}
