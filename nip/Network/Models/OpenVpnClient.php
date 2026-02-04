<?php

namespace Nip\Network\Models;

use Illuminate\Database\Eloquent\Model;

class OpenVpnClient extends Model
{
    protected $fillable = [
        'common_name',
        'real_address',
        'virtual_address',
        'bytes_received',
        'bytes_sent',
        'connected_since',
        'is_online',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'bytes_received' => 'integer',
            'bytes_sent' => 'integer',
            'connected_since' => 'datetime',
            'is_online' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    public function getFormattedBytesReceivedAttribute(): string
    {
        return $this->formatBytes($this->bytes_received);
    }

    public function getFormattedBytesSentAttribute(): string
    {
        return $this->formatBytes($this->bytes_sent);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
