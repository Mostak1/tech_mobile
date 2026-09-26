<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:migrate 
                            {--subdomain= : Migrate specific tenant by subdomain} 
                            {--seed : Run seeders after migration} 
                            {--rollback : Rollback latest migration step}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute database migrations across all isolated tenant databases';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $subdomain = $this->option('subdomain');
        $seed = $this->option('seed');
        $rollback = $this->option('rollback');

        $query = Tenant::on('landlord')->where('status', 'active');
        if ($subdomain) {
            $query->where('subdomain', $subdomain);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found matching criteria.');
            return 0;
        }

        $connectionManager = app(TenantConnectionManager::class);

        foreach ($tenants as $tenant) {
            $this->info("--------------------------------------------------");
            $this->info("Migrating Tenant: [{$tenant->name}] ({$tenant->subdomain})");
            $this->info("Database: {$tenant->database_name}");

            try {
                $connectionManager->setTenant($tenant);

                if ($rollback) {
                    Artisan::call('migrate:rollback', ['--force' => true]);
                } else {
                    Artisan::call('migrate', ['--force' => true]);
                    if ($seed) {
                        Artisan::call('db:seed', ['--force' => true]);
                    }
                }

                $this->line(trim(Artisan::output()));
                $this->info("Successfully processed [{$tenant->subdomain}]");
            } catch (\Exception $e) {
                $this->error("Error processing tenant [{$tenant->subdomain}]: " . $e->getMessage());
            } finally {
                $connectionManager->purgeTenant();
            }
        }

        $this->info("--------------------------------------------------");
        $this->info("Multi-Tenant migration task completed!");
        return 0;
    }
}
