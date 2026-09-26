<?php

namespace App\Http\Middleware;

use App\Services\TenantConnectionManager;
use Closure;
use Illuminate\Support\Facades\Config;

class SetSessionConfig
{
    public function handle($request, Closure $next)
    {
        $tenant = TenantConnectionManager::getTenant();
        $prefix = $tenant ? 'tenant_' . $tenant->subdomain . '_' : '';

        if ($request->is('online_store') || $request->is('online_store/*')) {
            Config::set('session.path', '/online_store');
            Config::set('session.cookie', $prefix . 'store_session');
        } else {
            Config::set('session.path', '/');
            Config::set('session.cookie', $prefix . 'web_session');
        }

        return $next($request);
    }
}
