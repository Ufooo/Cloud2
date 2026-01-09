<?php

namespace Nip\Support\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

abstract class CacheInvalidatingObserver
{
    /**
     * @return array<string>
     */
    abstract protected function getCacheKeys(): array;

    public function created(Model $model): void
    {
        $this->invalidateCache();
    }

    public function updated(Model $model): void
    {
        $this->invalidateCache();
    }

    public function deleted(Model $model): void
    {
        $this->invalidateCache();
    }

    public function restored(Model $model): void
    {
        $this->invalidateCache();
    }

    public function forceDeleted(Model $model): void
    {
        $this->invalidateCache();
    }

    protected function invalidateCache(): void
    {
        foreach ($this->getCacheKeys() as $key) {
            Cache::forget($key);
        }
    }
}
