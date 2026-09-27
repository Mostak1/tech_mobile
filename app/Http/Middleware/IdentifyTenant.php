<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantConnectionManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IdentifyTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();
        $subdomain = $this->extractSubdomain($host);

        if (!empty($subdomain)) {
            // First check if a matching tenant exists in Landlord DB
            $tenant = Cache::store('file')->remember('landlord_tenant_' . $subdomain, 300, function () use ($subdomain) {
                return Tenant::on('landlord')->where('subdomain', $subdomain)->where('status', 'active')->first();
            });

            // If tenant record exists, activate dynamic tenant DB context
            if ($tenant) {
                app(TenantConnectionManager::class)->setTenant($tenant);
                return $next($request);
            }
        }

        // System/Central routes or IPs bypass tenant identification if no tenant matched
        if (empty($subdomain) || $this->isCentralDomain($subdomain)) {
            // Allow all /landlord routes to execute against Landlord DB
            if ($request->is('landlord') || $request->is('landlord/*') || $request->is('api/landlord/*')) {
                return $next($request);
            }

            // Optional: If DEFAULT_TENANT_SUBDOMAIN is configured in .env, bind default tenant for central domain
            $defaultSubdomain = env('DEFAULT_TENANT_SUBDOMAIN');
            if (!empty($defaultSubdomain)) {
                $tenant = Cache::store('file')->remember('landlord_tenant_' . $defaultSubdomain, 300, function () use ($defaultSubdomain) {
                    return Tenant::on('landlord')->where('subdomain', $defaultSubdomain)->where('status', 'active')->first();
                });

                if ($tenant) {
                    app(TenantConnectionManager::class)->setTenant($tenant);
                    return $next($request);
                }
            }

            // On Central Landlord Domain without tenant prefix, redirect to Landlord Tenants Dashboard
            return redirect('/landlord/tenants');
        }

        abort(404, "Tenant account [{$subdomain}] does not exist or is suspended.");
    }

    /**
     * Extract subdomain from request host header.
     *
     * @param string $host
     * @return string|null
     */
    protected function extractSubdomain(string $host): ?string
    {
        // Strip port if present
        $host = explode(':', $host)[0];

        $parts = explode('.', $host);

        // Localhost / IP address checks
        if (count($parts) <= 1 || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        // shop1.example.com -> parts: ['shop1', 'example', 'com'] -> subdomain: 'shop1'
        // shop1.localhost -> parts: ['shop1', 'localhost'] -> subdomain: 'shop1'
        if (count($parts) >= 2) {
            return strtolower($parts[0]);
        }

        return null;
    }

    /**
     * Check if subdomain belongs to central admin / landlord app.
     *
     * @param string $subdomain
     * @return bool
     */
    protected function isCentralDomain(string $subdomain): bool
    {
        $envCentral = array_filter(array_map('trim', explode(',', env('CENTRAL_SUBDOMAINS', ''))));
        $defaultCentral = ['www', 'admin', 'central', 'landlord', 'app', 'pos', 'main'];
        $centralDomains = array_merge($defaultCentral, $envCentral);

        return in_array(strtolower($subdomain), $centralDomains, true);
    }
}
