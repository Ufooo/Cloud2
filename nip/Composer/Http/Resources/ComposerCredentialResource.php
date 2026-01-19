<?php

namespace Nip\Composer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Composer\Models\ComposerCredential;

/**
 * @mixin ComposerCredential
 */
class ComposerCredentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->when($this->isUserLevel(), $this->unixUser?->username),
            'repository' => $this->repository,
            'username' => $this->username,
            'hasPassword' => $this->password !== null,
            'status' => $this->status?->value,
            'displayableStatus' => $this->status?->label(),
            'statusBadgeVariant' => $this->status?->badgeVariant(),
            'createdAt' => $this->created_at?->format('M j, Y'),
        ];
    }
}
