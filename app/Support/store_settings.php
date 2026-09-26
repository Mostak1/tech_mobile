<?php

use App\Models\StoreSetting;
use App\Services\TenantConnectionManager;
use Illuminate\Support\Facades\Cache;

if (! function_exists('store_settings')) {
    function store_settings(): StoreSetting
    {
        $tenant = TenantConnectionManager::getTenant();
        $cacheKey = $tenant ? 'tenant_' . $tenant->id . '_store_settings' : 'store_settings';

        return Cache::remember($cacheKey, 600, function () {
            return StoreSetting::query()->first() ?? new StoreSetting;
        });
    }
}
