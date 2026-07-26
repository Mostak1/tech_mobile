<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Insert some stuff
        DB::table('roles')->insert(
            [
                [
                    'id' => 1,
                    'name' => 'Owner',
                    'label' => 'Owner',
                    'status' => 1,
                    'description' => 'Owner',
                ],
                [
                    'id' => 2,
                    'name' => 'Sales & Inventory Manager',
                    'label' => 'Sales & Inventory Manager',
                    'status' => 1,
                    'description' => 'Access to Dashboard, 5 Reports, Customers, Products, Sales & Returns, Purchases & Returns',
                ]
            ]
        );
    }
}
