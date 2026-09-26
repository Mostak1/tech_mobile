<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:create 
                            {--name= : Name of the tenant shop/business} 
                            {--subdomain= : Subdomain for the tenant} 
                            {--db-name= : Database name for the tenant} 
                            {--email=admin@example.com : Initial Admin email} 
                            {--password=password123 : Initial Admin password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision a new isolated POS tenant database, run migrations, seed initial data, and create admin user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->option('name') ?: $this->ask('Enter Tenant Business Name');
        $subdomain = strtolower(Str::slug($this->option('subdomain') ?: $this->ask('Enter Tenant Subdomain (e.g. shop1)')));
        $dbName = $this->option('db-name') ?: env('DB_TENANT_PREFIX', 'tenant_') . $subdomain;
        $email = $this->option('email');
        $password = $this->option('password');

        if (empty($name) || empty($subdomain)) {
            $this->error('Business name and subdomain are required!');
            return 1;
        }

        // Check if tenant subdomain exists in Landlord database
        $existing = Tenant::on('landlord')->where('subdomain', $subdomain)->first();
        if ($existing) {
            $this->error("Subdomain [{$subdomain}] already exists!");
            return 1;
        }

        $this->info("Creating tenant [{$name}] on subdomain [{$subdomain}.example.com]...");

        // Step 1: Create Database
        $dbUser = env('DB_USERNAME', 'root');
        $dbPass = env('DB_PASSWORD', '');

        try {
            $this->info("Creating database [{$dbName}]...");
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        } catch (\Exception $e) {
            $this->error("Failed to create database: " . $e->getMessage());
            return 1;
        }

        // Step 2: Save Tenant Record in Landlord DB
        $tenant = Tenant::on('landlord')->create([
            'name' => $name,
            'subdomain' => $subdomain,
            'database_name' => $dbName,
            'database_username' => $dbUser,
            'database_password' => Crypt::encryptString($dbPass),
            'status' => 'active',
        ]);

        // Step 3: Switch Connection Context
        $connectionManager = app(TenantConnectionManager::class);
        $connectionManager->setTenant($tenant);

        // Step 4: Run Migrations
        $this->info("Running migrations on database [{$dbName}]...");
        Artisan::call('migrate', [
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        // Step 5: Run Seeders
        $this->info("Running database seeders...");
        Artisan::call('db:seed', [
            '--force' => true,
        ]);

        // Step 6: Create/Update Admin User
        $user = User::where('email', $email)->first();
        if (!$user) {
            User::create([
                'firstname' => 'Admin',
                'lastname' => $name,
                'username' => 'admin_' . $subdomain,
                'email' => $email,
                'password' => Hash::make($password),
                'phone' => '123456789',
                'status' => 1,
                'is_all_warehouses' => 1,
                'role_id' => 1,
            ]);
        }

        $this->info("Tenant successfully created!");
        $this->info("Subdomain: https://{$subdomain}.example.com");
        $this->info("Database: {$dbName}");
        $this->info("Admin Email: {$email}");

        return 0;
    }
}
