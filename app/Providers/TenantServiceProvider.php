<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Services\TenantConnectionManager;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantConnectionManager::class, function ($app) {
            return new TenantConnectionManager();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Automatically inject active tenant ID into all queued job payloads
        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            $tenant = TenantConnectionManager::getTenant();
            return $tenant ? ['tenant_id' => $tenant->id] : [];
        });

        // Restore tenant database connection before queue worker executes job
        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            $payload = $event->job->payload();
            if (isset($payload['tenant_id'])) {
                $tenant = Tenant::on('landlord')->find($payload['tenant_id']);
                if ($tenant) {
                    app(TenantConnectionManager::class)->setTenant($tenant);
                }
            }
        });

        // Purge tenant connection context after job finishes or fails
        Event::listen(JobProcessed::class, function () {
            app(TenantConnectionManager::class)->purgeTenant();
        });

        Event::listen(JobFailed::class, function () {
            app(TenantConnectionManager::class)->purgeTenant();
        });
    }
}
