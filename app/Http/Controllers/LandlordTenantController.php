<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

class LandlordTenantController extends Controller
{
    /**
     * Step 1: Initialize Landlord Database Table (Run Landlord Migration)
     * URL: GET /landlord/init-db
     */
    public function initLandlordDb()
    {
        try {
            $exitCode = Artisan::call('migrate', [
                '--path' => 'database/migrations/landlord',
                '--database' => 'landlord',
                '--force' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Landlord database tables initialized successfully!',
                'output' => Artisan::output(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize landlord database: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all tenants registered in Landlord Database
     * URL: GET /landlord/tenants
     */
    public function index()
    {
        try {
            $tenants = Tenant::on('landlord')->orderBy('id', 'desc')->get();

            return response()->json([
                'success' => true,
                'count' => $tenants->count(),
                'tenants' => $tenants,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching tenants: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 2: Provision a New Tenant Subdomain & Database via Web URL
     * URL: POST /landlord/tenants/create or GET /landlord/tenants/create
     */
    public function createTenant(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'subdomain' => 'required|string|max:100|alpha_dash',
            'db_name' => 'nullable|string|max:100',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $name = $request->input('name');
        $subdomain = strtolower($request->input('subdomain'));
        $dbName = $request->input('db_name') ?: env('DB_TENANT_PREFIX', 'tenant_') . $subdomain;
        $email = $request->input('email');
        $password = $request->input('password');

        try {
            $exitCode = Artisan::call('tenant:create', [
                '--name' => $name,
                '--subdomain' => $subdomain,
                '--db-name' => $dbName,
                '--email' => $email,
                '--password' => $password,
            ]);

            if ($exitCode !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant creation failed.',
                    'output' => Artisan::output(),
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => "Tenant [{$name}] successfully provisioned!",
                'tenant' => [
                    'name' => $name,
                    'subdomain' => $subdomain,
                    'login_url' => "https://{$subdomain}." . request()->getHost(),
                    'database_name' => $dbName,
                    'admin_email' => $email,
                ],
                'output' => Artisan::output(),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error provisioning tenant: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run Migrations across all tenant databases
     * URL: GET /landlord/migrate-tenants
     */
    public function migrateTenants()
    {
        try {
            Artisan::call('tenant:migrate');

            return response()->json([
                'success' => true,
                'message' => 'All tenant database migrations executed successfully!',
                'output' => Artisan::output(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
