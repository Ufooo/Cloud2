<?php

namespace Nip\Shared\Traits;

use Illuminate\Http\Request;

trait BuildsResourcePermissions
{
    /**
     * @return array<string, bool>
     */
    protected function buildPermissions(Request $request, array $abilities = []): array
    {
        $user = $request->user();
        $permissions = [];

        foreach ($abilities as $ability) {
            $permissions[$ability] = $user?->can($ability, $this->resource) ?? false;
        }

        return $permissions;
    }
}
