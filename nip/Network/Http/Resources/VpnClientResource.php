<?php

namespace Nip\Network\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VpnClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'commonName' => $this->common_name,
            'realAddress' => $this->real_address,
            'virtualAddress' => $this->virtual_address,
            'bytesReceived' => $this->bytes_received,
            'bytesSent' => $this->bytes_sent,
            'bytesReceivedFormatted' => $this->formatted_bytes_received,
            'bytesSentFormatted' => $this->formatted_bytes_sent,
            'connectedSince' => $this->connected_since?->toIso8601String(),
            'connectedSinceHuman' => $this->connected_since?->diffForHumans(),
            'isOnline' => $this->is_online,
            'lastSeenAt' => $this->last_seen_at?->toIso8601String(),
            'lastSeenAtHuman' => $this->last_seen_at?->diffForHumans(),
        ];
    }
}
