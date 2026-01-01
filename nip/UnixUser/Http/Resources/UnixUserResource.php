<?php

namespace Nip\UnixUser\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Site\Models\Site;
use Nip\UnixUser\Enums\UserStatus;
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
        $sitesCount = Site::where('server_id', $this->server_id)
            ->where('user', $this->username)
            ->count();

        return [
            'id' => $this->id,
            'username' => $this->username,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'variant' => $this->status->badgeVariant(),
            ],
            'sitesCount' => $sitesCount,
            'createdAt' => $this->created_at?->toISOString(),
            'can' => [
                'delete' => ! in_array($this->username, ['root', 'netipar'], true)
                    && $this->status !== UserStatus::Deleting
                    && $this->status !== UserStatus::Installing
                    && $sitesCount === 0,
            ],
        ];
    }
}
