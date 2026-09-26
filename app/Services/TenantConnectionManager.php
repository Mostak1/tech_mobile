<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantConnectionManager
{
    /**
     * @var Tenant|null
     */
    protected static ?Tenant $currentTenant = null;

    /**
     * Switch database connection and application scope to target tenant.
     *
     * @param Tenant $tenant
     * @return void
     */
    public function setTenant(Tenant $tenant): void
    {
        static::$currentTenant = $tenant;

        // Decrypt password if encrypted, or use plain password
        $password = $tenant->database_password;
        try {
            $password = Crypt::decryptString($tenant->database_password);
        } catch (\Exception $e) {
            // Raw password fallback
        }

        // Configure dynamic tenant DB connection
        Config::set('database.connections.tenant.host', env('DB_HOST', '127.0.0.1'));
        Config::set('database.connections.tenant.port', env('DB_PORT', '3306'));
        Config::set('database.connections.tenant.database', $tenant->database_name);
        Config::set('database.connections.tenant.username', $tenant->database_username);
        Config::set('database.connections.tenant.password', $password);

        // Purge any stale connection and reconnect
        DB::purge('tenant');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');

        // Scope Cache prefix
        Config::set('cache.prefix', 'tenant_' . $tenant->id . '_cache');

        // Scope Session cookie to prevent cross-subdomain collision
        Config::set('session.cookie', 'tenant_' . $tenant->subdomain . '_session');
        Config::set('session.domain', null); // Strict single subdomain boundary

        // Scope Storage filesystem root per tenant
        $tenantStoragePath = storage_path('app/tenants/' . $tenant->subdomain);
        Config::set('filesystems.disks.public.root', $tenantStoragePath . '/public');
        Config::set('filesystems.disks.local.root', $tenantStoragePath . '/private');
        Config::set('filesystems.disks.public.url', '/storage/tenants/' . $tenant->subdomain);
    }

    /**
     * Get currently active tenant instance.
     *
     * @return Tenant|null
     */
    public static function getTenant(): ?Tenant
    {
        return static::$currentTenant;
    }

    /**
     * Purge tenant connection context.
     *
     * @return void
     */
    public function purgeTenant(): void
    {
        static::$currentTenant = null;
        DB::purge('tenant');
        DB::setDefaultConnection(env('DB_CONNECTION', 'mysql'));
    }
}
