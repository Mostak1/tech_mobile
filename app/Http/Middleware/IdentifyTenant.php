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

        // System/Central routes or IPs bypass tenant identification
        if (empty($subdomain) || $this->isCentralDomain($subdomain)) {
            return $next($request);
        }

        // Cache tenant metadata lookup for 300s to avoid central DB bottleneck on every hit
        $tenant = Cache::store('file')->remember('landlord_tenant_' . $subdomain, 300, function () use ($subdomain) {
            return Tenant::on('landlord')->where('subdomain', $subdomain)->where('status', 'active')->first();
        });

        if (!$tenant) {
            abort(404, "Tenant account [{$subdomain}] does not exist or is suspended.");
        }

        // Activate dynamic tenant DB, Cache prefix, Session cookie, and Storage paths
        app(TenantConnectionManager::class)->setTenant($tenant);

        return $next($request);
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
        $centralDomains = ['www', 'admin', 'central', 'landlord', 'app'];
        return in_array(strtolower($subdomain), $centralDomains, true);
    }
}
