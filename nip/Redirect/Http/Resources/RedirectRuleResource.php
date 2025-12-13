<?php

namespace Nip\Redirect\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Redirect\Enums\RedirectRuleStatus;
use Nip\Redirect\Models\RedirectRule;

/**
 * @mixin RedirectRule
 */
class RedirectRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canUpdate = $request->user()?->can('update', $this->site->server);
        $isInstalled = $this->status === RedirectRuleStatus::Installed;

        return [
            'id' => $this->id,
            'from' => $this->from,
            'to' => $this->to,
            'type' => $this->type?->value,
            'displayableType' => $this->type?->label(),
            'status' => $this->status?->value,
            'displayableStatus' => $this->status?->label(),
            'statusBadgeVariant' => $this->status?->badgeVariant(),
            'createdAt' => $this->created_at?->toISOString(),
            'can' => [
                'update' => $canUpdate && $isInstalled,
                'delete' => $canUpdate && ! in_array($this->status, [
                    RedirectRuleStatus::Installing,
                    RedirectRuleStatus::Removing,
                ], true),
            ],
        ];
    }
}
