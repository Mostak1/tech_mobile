# Complete Step-by-Step Guide: Implementing Database-per-Tenant Multi-Tenancy in Any Laravel Application

This master guide provides exact, production-tested steps to implement a **Database-per-Tenant Multi-Tenant SaaS Architecture** in any Laravel 10 / 11 / 12 application.

---

## 🏛️ Architecture Overview

```
                               ┌───────────────────────────┐
                               │     Incoming Request      │
                               │ (shop1.domain.com/login)  │
                               └─────────────┬─────────────┘
                                             │
                                             ▼
                               ┌───────────────────────────┐
                               │  IdentifyTenant Middleware │
                               └─────────────┬─────────────┘
                                             │
                       1. Extract Subdomain ("shop1")
                       2. Lookup Landlord DB ('tenants' table)
                       3. Resolve DB Name ("shop1_db")
                                             │
                                             ▼
                               ┌───────────────────────────┐
                               │  TenantConnectionManager  │
                               └─────────────┬─────────────┘
                                             │
                       - DB::purge('tenant')
                       - Reconnect PDO to shop1_db
                       - DB::setDefaultConnection('tenant')
                       - Scope Cache Prefix -> tenant_1_cache
                       - Scope Session Cookie -> tenant_shop1_session
                       - Scope Storage Path -> storage/app/tenants/shop1/
                                             │
                                             ▼
                               ┌───────────────────────────┐
                               │ Controller & Eloquent ORM │
                               │   Product::all() on DB1   │
                               └───────────────────────────┘
```

* **Landlord Database (`landlord_database`):** Stores tenant metadata, subdomains, and database names.
* **Tenant Databases (`shop1_db`, `shop2_db`...):** Independent MySQL database per tenant containing identical business tables (`users`, `products`, `orders`, `settings`...).
* **Zero Code Clutter:** Business models do **NOT** need `tenant_id` columns or `whereTenant` query scopes.

---

## Step 1: Database Configuration (`config/database.php`)

Open `config/database.php` and add two new connection entries under `'connections'`:

```php
'connections' => [

    // 1. Existing Standard MySQL Connection (Default)
    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'strict' => false,
        'engine' => 'InnoDB',
    ],

    // 2. Central Landlord Database Connection
    'landlord' => [
        'driver' => 'mysql',
        'host' => env('DB_LANDLORD_HOST', env('DB_HOST', '127.0.0.1')),
        'port' => env('DB_LANDLORD_PORT', env('DB_PORT', '3306')),
        'database' => env('DB_LANDLORD_DATABASE', env('DB_DATABASE', 'landlord_database')),
        'username' => env('DB_LANDLORD_USERNAME', env('DB_USERNAME', 'root')),
        'password' => env('DB_LANDLORD_PASSWORD', env('DB_PASSWORD', '')),
        'charset' => 'utf8mb4',
        'strict' => false,
        'engine' => 'InnoDB',
    ],

    // 3. Dynamic Tenant Database Template Connection
    'tenant' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => null,   // Dynamically injected at runtime
        'username' => null,   // Dynamically injected at runtime
        'password' => null,   // Dynamically injected at runtime
        'charset' => 'utf8mb4',
        'strict' => false,
        'engine' => 'InnoDB',
    ],

],
```

---

## Step 2: Landlord Database Schema & Models

### 2.1 Create Landlord Migration
Create file `database/migrations/landlord/2026_09_25_000000_create_landlord_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subdomain')->unique();
            $table->string('database_name');
            $table->string('database_username')->nullable();
            $table->text('database_password')->nullable();
            $table->string('status')->default('active'); // active, suspended, inactive
            $table->timestamps();
        });

        Schema::connection('landlord')->create('tenant_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('action');
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('tenant_activity_logs');
        Schema::connection('landlord')->dropIfExists('tenants');
    }
};
```

### 2.2 Create Landlord Models

Create `app/Models/Tenant.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $connection = 'landlord';
    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'subdomain',
        'database_name',
        'database_username',
        'database_password',
        'status',
    ];

    protected $hidden = [
        'database_password',
    ];

    public function activityLogs()
    {
        return $this->hasMany(TenantActivityLog::class, 'tenant_id');
    }
}
```

Create `app/Models/TenantActivityLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantActivityLog extends Model
{
    protected $connection = 'landlord';
    protected $table = 'tenant_activity_logs';

    protected $fillable = [
        'tenant_id',
        'action',
        'description',
        'ip_address',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
```

---

## Step 3: Tenant Connection Manager (`TenantConnectionManager.php`)

Create service class `app/Services/TenantConnectionManager.php`:

```php
<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class TenantConnectionManager
{
    protected static ?Tenant $currentTenant = null;

    public function setTenant(Tenant $tenant): void
    {
        static::$currentTenant = $tenant;

        // Fallback to master DB_USERNAME and DB_PASSWORD if tenant credentials are empty
        $username = !empty($tenant->database_username) ? $tenant->database_username : env('DB_USERNAME', 'root');
        $password = env('DB_PASSWORD', '');

        if (!empty($tenant->database_password)) {
            try {
                $password = Crypt::decryptString($tenant->database_password);
            } catch (\Exception $e) {
                $password = $tenant->database_password;
            }
        }

        // 1. Inject Dynamic Database Credentials
        Config::set('database.connections.tenant.host', env('DB_HOST', '127.0.0.1'));
        Config::set('database.connections.tenant.port', env('DB_PORT', '3306'));
        Config::set('database.connections.tenant.database', $tenant->database_name);
        Config::set('database.connections.tenant.username', $username);
        Config::set('database.connections.tenant.password', $password);

        // 2. Purge stale PDO connection and reconnect
        DB::purge('tenant');
        DB::reconnect('tenant');

        // 3. Set 'tenant' as default Eloquent connection
        DB::setDefaultConnection('tenant');

        // 4. Scope Cache Prefix per Tenant
        Config::set('cache.prefix', 'tenant_' . $tenant->id . '_cache');

        // 5. Scope Session Cookie and Domain per Tenant
        Config::set('session.cookie', 'tenant_' . $tenant->subdomain . '_session');
        Config::set('session.domain', null);

        // 6. Scope File Storage Root per Tenant
        $tenantStoragePath = storage_path('app/tenants/' . $tenant->subdomain);
        Config::set('filesystems.disks.public.root', $tenantStoragePath . '/public');
        Config::set('filesystems.disks.local.root', $tenantStoragePath . '/private');
        Config::set('filesystems.disks.public.url', '/storage/tenants/' . $tenant->subdomain);
    }

    public static function getTenant(): ?Tenant
    {
        return static::$currentTenant;
    }

    public function purgeTenant(): void
    {
        static::$currentTenant = null;
        DB::purge('tenant');
        DB::setDefaultConnection(env('DB_CONNECTION', 'mysql'));
    }
}
```

---

## Step 4: Tenant Resolution Middleware (`IdentifyTenant.php`)

Create middleware `app/Http/Middleware/IdentifyTenant.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantConnectionManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();
        $subdomain = $this->extractSubdomain($host);

        if (!empty($subdomain)) {
            // 1. Check if matching tenant exists in Landlord DB
            $tenant = Cache::store('file')->remember('landlord_tenant_' . $subdomain, 300, function () use ($subdomain) {
                return Tenant::on('landlord')->where('subdomain', $subdomain)->where('status', 'active')->first();
            });

            // 2. If tenant exists, switch database connection context!
            if ($tenant) {
                app(TenantConnectionManager::class)->setTenant($tenant);
                return $next($request);
            }
        }

        // 3. Central domain / Landlord routes pass-through
        if (empty($subdomain) || $this=isCentralDomain($subdomain)) {
            if ($request->is('landlord') || $request->is('landlord/*') || $request->is('api/landlord/*')) {
                return $next($request);
            }

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

            return redirect('/landlord/tenants');
        }

        abort(404, "Tenant account [{$subdomain}] does not exist or is suspended.");
    }

    protected function extractSubdomain(string $host): ?string
    {
        $host = explode(':', $host)[0];
        $parts = explode('.', $host);

        if (count($parts) <= 1 || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        if (count($parts) >= 2) {
            return strtolower($parts[0]);
        }

        return null;
    }

    protected function isCentralDomain(string $subdomain): bool
    {
        $envCentral = array_filter(array_map('trim', explode(',', env('CENTRAL_SUBDOMAINS', ''))));
        $defaultCentral = ['www', 'admin', 'central', 'landlord', 'app'];
        $centralDomains = array_merge($defaultCentral, $envCentral);

        return in_array(strtolower($subdomain), $centralDomains, true);
    }
}
```

### Register Middleware at Highest Priority

In `app/Http/Kernel.php` (or `bootstrap/app.php` in Laravel 11/12):

```php
protected $middlewarePriority = [
    \App\Http\Middleware\IdentifyTenant::class, // HIGHEST PRIORITY
    \Illuminate\Session\Middleware\StartSession::class,
    ...
];

protected $middleware = [
    \App\Http\Middleware\IdentifyTenant::class, // GLOBAL ENTRY
    ...
];
```

---

## Step 5: Queue & Background Worker Tenant Scoping (`TenantServiceProvider.php`)

Create provider `app/Providers/TenantServiceProvider.php`:

```php
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
    public function register(): void
    {
        $this->app->singleton(TenantConnectionManager::class, function ($app) {
            return new TenantConnectionManager();
        });
    }

    public function boot(): void
    {
        // 1. Inject tenant_id into all queued job payloads automatically
        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            $tenant = TenantConnectionManager::getTenant();
            return $tenant ? ['tenant_id' => $tenant->id] : [];
        });

        // 2. Restore tenant connection before background worker processes job
        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            $payload = $event->job->payload();
            if (isset($payload['tenant_id'])) {
                $tenant = Tenant::on('landlord')->find($payload['tenant_id']);
                if ($tenant) {
                    app(TenantConnectionManager::class)->setTenant($tenant);
                }
            }
        });

        // 3. Purge tenant connection when job finishes or fails
        Event::listen(JobProcessed::class, function () {
            app(TenantConnectionManager::class)->purgeTenant();
        });

        Event::listen(JobFailed::class, function () {
            app(TenantConnectionManager::class)->purgeTenant();
        });
    }
}
```

Register `App\Providers\TenantServiceProvider::class` in `config/app.php` under `'providers'`.

---

## Step 6: CLI Management Commands

### 6.1 Provision Tenant Command (`php artisan tenant:create`)
Create `app/Console/Commands/TenantCreateCommand.php`:

```php
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
    protected $signature = 'tenant:create 
                            {--name= : Tenant Shop Name} 
                            {--subdomain= : Subdomain e.g. shop1} 
                            {--db-name= : Database name} 
                            {--email=admin@example.com : Admin email} 
                            {--password=password123 : Admin password}';

    protected $description = 'Provision a new isolated tenant database, run migrations, and create admin user';

    public function handle()
    {
        $name = $this->option('name') ?: $this->ask('Enter Business Name');
        $subdomain = strtolower(Str::slug($this->option('subdomain') ?: $this->ask('Enter Subdomain')));
        $dbName = $this->option('db-name') ?: env('DB_TENANT_PREFIX', 'tenant_') . $subdomain;
        $email = $this->option('email');
        $password = $this->option('password');

        $this->info("Creating database [{$dbName}]...");
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

        $tenant = Tenant::on('landlord')->create([
            'name' => $name,
            'subdomain' => $subdomain,
            'database_name' => $dbName,
            'database_username' => env('DB_USERNAME', 'root'),
            'database_password' => Crypt::encryptString(env('DB_PASSWORD', '')),
            'status' => 'active',
        ]);

        $connectionManager = app(TenantConnectionManager::class);
        $connectionManager->setTenant($tenant);

        $this->info("Running migrations on database [{$dbName}]...");
        Artisan::call('migrate', ['--force' => true]);

        $this->info("Running seeders...");
        Artisan::call('db:seed', ['--force' => true]);

        User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("Tenant [{$subdomain}] successfully provisioned!");
        return 0;
    }
}
```

### 6.2 Multi-Tenant Migration Command (`php artisan tenant:migrate`)
Create `app/Console/Commands/TenantMigrateCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantMigrateCommand extends Command
{
    protected $signature = 'tenant:migrate {--subdomain=} {--seed}';
    protected $description = 'Run database migrations across all isolated tenant databases';

    public function handle()
    {
        $query = Tenant::on('landlord')->where('status', 'active');
        if ($subdomain = $this->option('subdomain')) {
            $query->where('subdomain', $subdomain);
        }

        $tenants = $query->get();
        $connectionManager = app(TenantConnectionManager::class);

        foreach ($tenants as $tenant) {
            $this->info("Migrating Tenant: [{$tenant->subdomain}] -> DB: [{$tenant->database_name}]");
            try {
                $connectionManager->setTenant($tenant);
                Artisan::call('migrate', ['--force' => true]);
                if ($this->option('seed')) {
                    Artisan::call('db:seed', ['--force' => true]);
                }
            } finally {
                $connectionManager->purgeTenant();
            }
        }
        return 0;
    }
}
```

---

## Step 7: Landlord Management Web Controller & Routes

Create `app/Http/Controllers/LandlordTenantController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class LandlordTenantController extends Controller
{
    public function initLandlordDb()
    {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/landlord',
            '--database' => 'landlord',
            '--force' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Landlord database tables initialized!',
            'output' => Artisan::output(),
        ]);
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'tenants' => Tenant::on('landlord')->get(),
        ]);
    }

    public function createTenant(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'subdomain' => 'required|string|alpha_dash',
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        Artisan::call('tenant:create', [
            '--name' => $request->name,
            '--subdomain' => $request->subdomain,
            '--db-name' => $request->db_name ?: env('DB_TENANT_PREFIX', 'tenant_') . $request->subdomain,
            '--email' => $request->email,
            '--password' => $request->password,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Tenant [{$request->subdomain}] created successfully!",
            'output' => Artisan::output(),
        ]);
    }
}
```

Register web routes in `routes/web.php`:

```php
Route::prefix('landlord')->middleware(['web'])->group(function () {
    Route::get('/init-db', [App\Http\Controllers\LandlordTenantController::class, 'initLandlordDb']);
    Route::get('/tenants', [App\Http\Controllers\LandlordTenantController::class, 'index']);
    Route::match(['get', 'post'], '/tenants/create', [App\Http\Controllers\LandlordTenantController::class, 'createTenant']);
});
```

---

## Step 8: View Composer Safeguards (`AppServiceProvider.php`)

To prevent missing table crashes when rendering central landlord views or error pages, wrap all View Composer database calls in `Schema::hasTable()` checks inside `AppServiceProvider.php`:

```php
View::composer('*', function ($view) {
    $excluded = ['api', 'setup', 'update', 'password', 'online_store', 'landlord'];
    $firstSegment = Request::segment(1);

    if (!in_array($firstSegment, $excluded)) {
        try {
            if (Schema::hasTable('settings')) {
                $view->with('app_settings', Setting::first());
            }
        } catch (\Throwable $e) {
            // Gracefully ignore missing table errors
        }
    }
});
```

---

## 🚀 Step 9: cPanel Production Setup Checklist

1. **Master `.env` File Setup:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=mypos_landlord
   DB_USERNAME=mypos_master
   DB_PASSWORD="YourPasswordHere"
   DB_TENANT_PREFIX="mypos_"
   ```
2. **Wildcard Subdomain Setup:**
   * cPanel $\rightarrow$ Subdomains $\rightarrow$ Add `*.yourdomain.com` pointing to `/public_html` (or `laravel_app/public`).
3. **Wildcard SSL:**
   * cPanel AutoSSL $\rightarrow$ Issue SSL for `*.yourdomain.com`.
4. **cPanel Cron Job:**
   ```bash
   * * * * * cd /home/username/laravel_app && php artisan tenant:cron >> /dev/null 2>&1
   ```

---
*End of Master Multi-Tenancy Setup Guide.*
