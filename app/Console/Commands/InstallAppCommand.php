<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;

class InstallAppCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install {--fresh : Drop all tables and re-run all migrations} {--seed : Seed the database after migration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform the application CLI installation and setup';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Stocky App Installer ===');

        // 1. Ensure .env exists
        if (!file_exists(base_path('.env'))) {
            $this->error('.env file does not exist. Please copy .env.example or create a .env file first.');
            return 1;
        }

        // 2. Generate APP_KEY if not already set
        $appKey = config('app.key');
        if (empty($appKey) || $appKey === 'SomeRandomStringWith32Characters') {
            $this->info('Generating application key...');
            Artisan::call('key:generate', ['--force' => true]);
            $this->line(Artisan::output());
        } else {
            $this->info('Application key already set.');
        }

        // 3. Run database migrations
        $fresh = $this->option('fresh');
        $seed = $this->option('seed');

        if ($fresh) {
            $this->info('Dropping all tables and re-running migrations...');
            Artisan::call('migrate:fresh', [
                '--force' => true,
                '--seed' => $seed,
            ]);
            $this->line(Artisan::output());
        } else {
            $this->info('Running database migrations...');
            Artisan::call('migrate', [
                '--force' => true,
            ]);
            $this->line(Artisan::output());

            if ($seed) {
                $this->info('Seeding database...');
                Artisan::call('db:seed', [
                    '--force' => true,
                ]);
                $this->line(Artisan::output());
            }
        }

        // 4. Run Passport setup
        $this->info('Running Passport migrations...');
        Artisan::call('migrate', [
            '--path'  => 'vendor/laravel/passport/database/migrations',
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        $this->info('Generating Passport keys...');
        Artisan::call('passport:keys', [
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        $this->info('Creating Passport OAuth clients...');
        try {
            $clientRepository = app(ClientRepository::class);
            $appUrl = config('app.url') ?: 'http://localhost';

            // Create Personal Access Client
            $clientRepository->createPersonalAccessClient(
                null,
                'Laravel Personal Access Client',
                $appUrl
            );

            // Create Password Grant Client
            $clientRepository->createPasswordGrantClient(
                null,
                'Laravel Password Grant Client',
                $appUrl
            );
            $this->info('Passport OAuth clients created successfully.');
        } catch (\Exception $e) {
            $this->warn('Could not create Passport clients (they might already exist): ' . $e->getMessage());
        }

        // 5. Create storage links
        $this->info('Creating storage links...');
        if (!file_exists(public_path('storage'))) {
            Artisan::call('storage:link');
            $this->line(Artisan::output());
        } else {
            $this->info('Storage link already exists.');
        }

        // 6. Mark as installed
        $this->info('Marking application as installed...');
        Storage::disk('public')->put('installed', 'OK');

        $this->info('=== Installation Completed Successfully ===');
        return 0;
    }
}
