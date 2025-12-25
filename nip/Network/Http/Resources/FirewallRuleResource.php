<?php

namespace Nip\Network\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Network\Models\FirewallRule;

/**
 * @mixin FirewallRule
 */
class FirewallRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'port' => $this->port,
            'ipAddress' => $this->ip_address,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'variant' => $this->status->badgeVariant(),
            ],
            'can' => [
                'delete' => $request->user()?->can('update', $this->server) ?? false,
            ],
        ];
    }
}
