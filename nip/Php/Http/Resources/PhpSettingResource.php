<?php

namespace Nip\Php\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Php\Models\PhpSetting;

/**
 * @mixin PhpSetting
 */
class PhpSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maxUploadSize' => $this->max_upload_size,
            'maxExecutionTime' => $this->max_execution_time,
            'opcacheEnabled' => $this->opcache_enabled,
        ];
    }
}
