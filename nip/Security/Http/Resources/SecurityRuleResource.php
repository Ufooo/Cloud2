<?php

namespace Nip\Security\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Security\Enums\SecurityRuleStatus;
use Nip\Security\Models\SecurityRule;

/**
 * @mixin SecurityRule
 */
class SecurityRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canUpdate = $request->user()?->can('update', $this->site->server);
        $isInstalled = $this->status === SecurityRuleStatus::Installed;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'path' => $this->path,
            'status' => $this->status?->value,
            'displayableStatus' => $this->status?->label(),
            'statusBadgeVariant' => $this->status?->badgeVariant(),
            'credentials' => $this->whenLoaded('credentials', fn () => $this->credentials->map(fn ($credential) => [
                'id' => $credential->id,
                'username' => $credential->username,
            ])),
            'createdAt' => $this->created_at?->toISOString(),
            'can' => [
                'update' => $canUpdate && $isInstalled,
                'delete' => $canUpdate && ! in_array($this->status, [
                    SecurityRuleStatus::Installing,
                    SecurityRuleStatus::Removing,
                ], true),
            ],
        ];
    }
}
