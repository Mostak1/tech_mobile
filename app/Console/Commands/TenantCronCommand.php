<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantCronCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Iterate through all active tenants and execute scheduled tenant-level background tasks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenants = Tenant::on('landlord')->where('status', 'active')->get();

        if ($tenants->isEmpty()) {
            $this->info('No active tenants registered.');
            return 0;
        }

        $connectionManager = app(TenantConnectionManager::class);

        foreach ($tenants as $tenant) {
            try {
                $connectionManager->setTenant($tenant);

                // Execute tenant scheduled commands
                Artisan::call('check:asset_validation_due');
                Artisan::call('generate:subscription_invoices');
                Artisan::call('send:subscription_sms_reminders');

            } catch (\Exception $e) {
                logger()->error("Tenant cron error on tenant [{$tenant->subdomain}]: " . $e->getMessage());
            } finally {
                $connectionManager->purgeTenant();
            }
        }

        return 0;
    }
}
