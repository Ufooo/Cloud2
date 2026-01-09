<?php

namespace Nip\Site\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Site\Data\SiteData;
use Nip\Site\Models\Site;

/**
 * @mixin Site
 */
class SiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return SiteData::fromModel($this->resource)->toArray();
    }
}
