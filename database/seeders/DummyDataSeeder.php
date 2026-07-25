<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 1. Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 2. Truncate tables we are going to seed
        $tablesToTruncate = [
            'units',
            'payment_methods',
            'warehouses',
            'users',
            'role_user',
            'user_warehouse',
            'categories',
            'subcategories',
            'brands',
            'clients',
            'providers',
            'products',
            'product_warehouse',
            'sales',
            'sale_details',
            'payment_sales',
            'purchases',
            'purchase_details',
            'payment_purchases',
            'transfers',
            'transfer_details',
            'sale_returns',
            'sale_return_details',
            'payment_sale_returns',
            'purchase_returns',
            'purchase_return_details',
            'payment_purchase_returns'
        ];

        foreach ($tablesToTruncate as $table) {
            DB::table($table)->truncate();
        }

        // 3. Seed Units
        DB::table('units')->insert([
            'id' => 1,
            'name' => 'Piece',
            'ShortName' => 'pc',
            'base_unit' => null,
            'operator' => '*',
            'operator_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Seed Payment Methods
        $paymentMethods = [
            ['id' => 1, 'name' => 'Card'],
            ['id' => 2, 'name' => 'Cash'],
            ['id' => 3, 'name' => 'bKash'],
            ['id' => 4, 'name' => 'Check'],
            ['id' => 5, 'name' => 'Bank Transfer'],
        ];
        foreach ($paymentMethods as $pm) {
            DB::table('payment_methods')->insert(array_merge($pm, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // 5. Seed Warehouses (3)
        $warehouses = [
            ['id' => 1, 'name' => 'Main Warehouse', 'city' => 'Dhaka', 'mobile' => '01711111111', 'country' => 'Bangladesh'],
            ['id' => 2, 'name' => 'Dhaka Warehouse', 'city' => 'Dhaka', 'mobile' => '01722222222', 'country' => 'Bangladesh'],
            ['id' => 3, 'name' => 'Chittagong Warehouse', 'city' => 'Chittagong', 'mobile' => '01733333333', 'country' => 'Bangladesh'],
        ];
        foreach ($warehouses as $wh) {
            DB::table('warehouses')->insert(array_merge($wh, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // 6. Seed Users (5)
        $users = [
            [
                'id' => 1,
                'firstname' => 'William',
                'lastname' => 'Castillo',
                'username' => 'William Castillo',
                'email' => 'admin@example.com',
                'password' => Hash::make('123456'),
                'avatar' => 'no_avatar.png',
                'phone' => '0123456789',
                'role_id' => 1,
                'statut' => 1,
                'is_all_warehouses' => 1,
                'record_view' => 1,
            ],
            [
                'id' => 2,
                'firstname' => 'Karim',
                'lastname' => 'Rahman',
                'username' => 'manager',
                'email' => 'manager@example.com',
                'password' => Hash::make('123456'),
                'avatar' => 'no_avatar.png',
                'phone' => '01712345671',
                'role_id' => 1,
                'statut' => 1,
                'is_all_warehouses' => 1,
                'record_view' => 1,
            ],
            [
                'id' => 3,
                'firstname' => 'Abul',
                'lastname' => 'Kalam',
                'username' => 'sales1',
                'email' => 'sales1@example.com',
                'password' => Hash::make('123456'),
                'avatar' => 'no_avatar.png',
                'phone' => '01712345672',
                'role_id' => 1,
                'statut' => 1,
                'is_all_warehouses' => 1,
                'record_view' => 1,
            ],
            [
                'id' => 4,
                'firstname' => 'Bablu',
                'lastname' => 'Miah',
                'username' => 'sales2',
                'email' => 'sales2@example.com',
                'password' => Hash::make('123456'),
                'avatar' => 'no_avatar.png',
                'phone' => '01712345673',
                'role_id' => 1,
                'statut' => 1,
                'is_all_warehouses' => 1,
                'record_view' => 1,
            ],
            [
                'id' => 5,
                'firstname' => 'Kamil',
                'lastname' => 'Hasan',
                'username' => 'staff',
                'email' => 'staff@example.com',
                'password' => Hash::make('123456'),
                'avatar' => 'no_avatar.png',
                'phone' => '01712345674',
                'role_id' => 1,
                'statut' => 1,
                'is_all_warehouses' => 1,
                'record_view' => 1,
            ]
        ];

        foreach ($users as $user) {
            DB::table('users')->insert(array_merge($user, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            // Assign Owner Role
            DB::table('role_user')->insert([
                'user_id' => $user['id'],
                'role_id' => 1,
            ]);

            // Assign User to all warehouses
            foreach ([1, 2, 3] as $wh_id) {
                DB::table('user_warehouse')->insert([
                    'user_id' => $user['id'],
                    'warehouse_id' => $wh_id,
                ]);
            }
        }

        // 7. Seed Categories & Subcategories
        $categories = [
            ['id' => 1, 'name' => 'Keyboards & Mice', 'code' => 'KBMC'],
            ['id' => 2, 'name' => 'Storage Devices', 'code' => 'STRG'],
            ['id' => 3, 'name' => 'Monitors & Displays', 'code' => 'MNTR'],
            ['id' => 4, 'name' => 'Audio & Sound', 'code' => 'SND'],
            ['id' => 5, 'name' => 'Cables & Adapters', 'code' => 'CBL'],
            ['id' => 6, 'name' => 'PC Components', 'code' => 'COMP'],
        ];

        foreach ($categories as $cat) {
            DB::table('categories')->insert(array_merge($cat, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $subcategories = [
            ['id' => 1, 'category_id' => 1, 'name' => 'Mechanical Keyboards', 'status' => 1],
            ['id' => 2, 'category_id' => 1, 'name' => 'Wireless Mice', 'status' => 1],
            ['id' => 3, 'category_id' => 1, 'name' => 'Desktop Accessories', 'status' => 1],
            ['id' => 4, 'category_id' => 2, 'name' => 'NVMe SSDs', 'status' => 1],
            ['id' => 5, 'category_id' => 2, 'name' => 'Portable Hard Drives', 'status' => 1],
            ['id' => 6, 'category_id' => 3, 'name' => 'Gaming Monitors', 'status' => 1],
            ['id' => 7, 'category_id' => 3, 'name' => 'Office Displays', 'status' => 1],
            ['id' => 8, 'category_id' => 4, 'name' => 'Gaming Headsets', 'status' => 1],
            ['id' => 9, 'category_id' => 4, 'name' => 'Bluetooth Speakers', 'status' => 1],
            ['id' => 10, 'category_id' => 5, 'name' => 'HDMI & DisplayPort', 'status' => 1],
            ['id' => 11, 'category_id' => 5, 'name' => 'USB Adapters', 'status' => 1],
            ['id' => 12, 'category_id' => 6, 'name' => 'RAM Memory Modules', 'status' => 1],
            ['id' => 13, 'category_id' => 6, 'name' => 'Graphics Cards', 'status' => 1],
        ];

        foreach ($subcategories as $sub) {
            DB::table('subcategories')->insert(array_merge($sub, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // 8. Seed Brands (10)
        $brands = [
            ['id' => 1, 'name' => 'Logitech'],
            ['id' => 2, 'name' => 'Razer'],
            ['id' => 3, 'name' => 'Corsair'],
            ['id' => 4, 'name' => 'Samsung'],
            ['id' => 5, 'name' => 'ASUS'],
            ['id' => 6, 'name' => 'Sony'],
            ['id' => 7, 'name' => 'Kingston'],
            ['id' => 8, 'name' => 'HP'],
            ['id' => 9, 'name' => 'Dell'],
            ['id' => 10, 'name' => 'Intel'],
        ];

        foreach ($brands as $br) {
            DB::table('brands')->insert(array_merge($br, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // 9. Seed Clients & Providers (5 each)
        $clients = [
            ['id' => 1, 'name' => 'Rahim Ahmed', 'firstname' => 'Rahim', 'lastname' => 'Ahmed', 'code' => 'CL-0001', 'email' => 'rahim@example.com', 'phone' => '01811111111', 'adresse' => 'Dhaka, Bangladesh'],
            ['id' => 2, 'name' => 'Karim Ullah', 'firstname' => 'Karim', 'lastname' => 'Ullah', 'code' => 'CL-0002', 'email' => 'karim@example.com', 'phone' => '01822222222', 'adresse' => 'Chittagong, Bangladesh'],
            ['id' => 3, 'name' => 'Selim Khan', 'firstname' => 'Selim', 'lastname' => 'Khan', 'code' => 'CL-0003', 'email' => 'selim@example.com', 'phone' => '01833333333', 'adresse' => 'Sylhet, Bangladesh'],
            ['id' => 4, 'name' => 'Anisur Rahman', 'firstname' => 'Anisur', 'lastname' => 'Rahman', 'code' => 'CL-0004', 'email' => 'anis@example.com', 'phone' => '01844444444', 'adresse' => 'Rajshahi, Bangladesh'],
            ['id' => 5, 'name' => 'Babul Miah', 'firstname' => 'Babul', 'lastname' => 'Miah', 'code' => 'CL-0005', 'email' => 'babul@example.com', 'phone' => '01855555555', 'adresse' => 'Khulna, Bangladesh'],
        ];

        foreach ($clients as $client) {
            DB::table('clients')->insert(array_merge($client, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $providers = [
            ['id' => 1, 'name' => 'Tech Distribution Ltd', 'code' => 'PV-0001', 'email' => 'info@techdist.com', 'phone' => '01911111111', 'adresse' => 'Dhaka Office'],
            ['id' => 2, 'name' => 'Global Accessories Ltd', 'code' => 'PV-0002', 'email' => 'sales@globalacc.com', 'phone' => '01922222222', 'adresse' => 'Chittagong Branch'],
            ['id' => 3, 'name' => 'Smart Technologies', 'code' => 'PV-0003', 'email' => 'info@smarttech.com', 'phone' => '01933333333', 'adresse' => 'IDB Bhaban, Dhaka'],
            ['id' => 4, 'name' => 'Flora Limited', 'code' => 'PV-0004', 'email' => 'contact@floralimited.com', 'phone' => '01944444444', 'adresse' => 'Motijheel C/A, Dhaka'],
            ['id' => 5, 'name' => 'Excel Technologies', 'code' => 'PV-0005', 'email' => 'support@exceltech.com', 'phone' => '01955555555', 'adresse' => 'Elephant Road, Dhaka'],
        ];

        foreach ($providers as $prov) {
            DB::table('providers')->insert(array_merge($prov, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // 10. Generate 200 Products (Computer Accessories)
        $brandNames = ['Logitech', 'Razer', 'Corsair', 'Samsung', 'ASUS', 'Sony', 'Kingston', 'HP', 'Dell', 'Intel', 'Gigabyte', 'MSI', 'HyperX', 'SteelSeries', 'Anker', 'Crucial', 'TP-Link', 'Western Digital', 'Sandisk'];
        $adj = ['Gaming', 'Wireless', 'Ergonomic', 'RGB', 'Pro', 'Ultra', 'Slim', 'Mechanical', 'High-Speed', 'Portable', 'Multi-Port', 'Heavy-Duty', 'Noise-Cancelling', 'HD', 'Curved', 'Premium', 'Compact', 'USB-C', 'Dual-Band', 'Waterproof'];
        
        $subCatMapping = [
            1  => ['name' => 'Mechanical Keyboard', 'image' => 'keyboard.png'],
            2  => ['name' => 'Wireless Mouse', 'image' => 'mouse.png'],
            3  => ['name' => 'Desk Accessory', 'image' => 'accessories.png'],
            4  => ['name' => 'NVMe SSD Drive', 'image' => 'ssd.png'],
            5  => ['name' => 'External Portable HDD', 'image' => 'ssd.png'],
            6  => ['name' => 'Curved Gaming Monitor', 'image' => 'monitor.png'],
            7  => ['name' => 'UltraSharp Office Monitor', 'image' => 'monitor.png'],
            8  => ['name' => 'Wireless Headset', 'image' => 'headphone.png'],
            9  => ['name' => 'Bluetooth Speaker', 'image' => 'speaker.png'],
            10 => ['name' => 'Premium HDMI Cable', 'image' => 'cable.png'],
            11 => ['name' => 'Type-C USB Adapter', 'image' => 'cable.png'],
            12 => ['name' => 'DDR4 Desktop RAM Module', 'image' => 'ram.png'],
            13 => ['name' => 'Overclocked Graphics Card', 'image' => 'gpu.png'],
        ];

        $products = [];
        for ($i = 1; $i <= 200; $i++) {
            $brandId = rand(1, 10);
            $brandName = $brandNames[$brandId - 1];
            $adjective = $adj[array_rand($adj)];
            
            $subCatId = rand(1, 13);
            $subCatInfo = $subCatMapping[$subCatId];
            
            $catId = null;
            foreach ($subcategories as $sc) {
                if ($sc['id'] === $subCatId) {
                    $catId = $sc['category_id'];
                    break;
                }
            }

            // Generate unique product name
            $capacity = in_array($subCatId, [4, 5, 12]) ? (['256GB', '512GB', '1TB', '2TB', '8GB', '16GB', '32GB'][rand(0, 6)] . ' ') : '';
            $productName = $brandName . ' ' . $adjective . ' ' . $capacity . $subCatInfo['name'] . ' ' . rand(100, 999);
            
            $cost = rand(10, 300);
            $price = round($cost * rand(12, 15) / 10, 2);
            $wholesale_price = round($cost * 1.1, 2);
            $min_price = round($cost * 1.05, 2);
            $code = 'PROD' . str_pad($i, 5, '0', STR_PAD_LEFT);

            $products[] = [
                'id' => $i,
                'code' => $code,
                'Type_barcode' => 'BARCODE',
                'name' => $productName,
                'cost' => $cost,
                'price' => $price,
                'wholesale_price' => $wholesale_price,
                'min_price' => $min_price,
                'category_id' => $catId,
                'sub_category_id' => $subCatId,
                'brand_id' => $brandId,
                'unit_id' => 1,
                'unit_sale_id' => 1,
                'unit_purchase_id' => 1,
                'TaxNet' => 0.0,
                'tax_method' => '1',
                'discount' => 0.0,
                'discount_method' => '1',
                'image' => $subCatInfo['image'],
                'note' => 'Realistic dummy data seeded for testing.',
                'stock_alert' => 5.0,
                'weight' => rand(1, 20) / 10,
                'length' => rand(10, 50),
                'width' => rand(10, 30),
                'height' => rand(2, 15),
                'is_variant' => 0,
                'is_imei' => 0,
                'not_selling' => 0,
                'is_active' => 1,
                'is_featured' => rand(0, 5) === 0 ? 1 : 0, // 20% of products are featured
                'hide_from_online_store' => 0,
                'type' => 'is_single',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert products in chunks for performance
        $chunks = array_chunk($products, 50);
        foreach ($chunks as $chunk) {
            DB::table('products')->insert($chunk);
        }

        // Seed stock quantities (Product Warehouse)
        $productWarehouseEntries = [];
        for ($productId = 1; $productId <= 200; $productId++) {
            foreach ([1, 2, 3] as $whId) {
                $productWarehouseEntries[] = [
                    'product_id' => $productId,
                    'warehouse_id' => $whId,
                    'product_variant_id' => null,
                    'qte' => rand(20, 100),
                    'manage_stock' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        $pwChunks = array_chunk($productWarehouseEntries, 100);
        foreach ($pwChunks as $chunk) {
            DB::table('product_warehouse')->insert($chunk);
        }

        // 11. Seed Transactions (Purchases, Sales, Transfers, Sell Returns, Purchase Returns)
        
        // --- SEED PURCHASES (15) ---
        for ($p = 1; $p <= 15; $p++) {
            $ref = 'PR-' . str_pad($p, 4, '0', STR_PAD_LEFT);
            $userId = rand(1, 5);
            $providerId = rand(1, 5);
            $warehouseId = rand(1, 3);
            
            // Choose 1 to 4 random products
            $purchasedProducts = [];
            $totalProductsCount = rand(1, 4);
            $grandTotal = 0;
            
            $purchaseId = $p;
            
            for ($k = 0; $k < $totalProductsCount; $k++) {
                $randProdId = rand(1, 200);
                // Avoid duplicating the same product in a single purchase
                if (in_array($randProdId, $purchasedProducts)) {
                    continue;
                }
                $purchasedProducts[] = $randProdId;
                
                $prodCost = $products[$randProdId - 1]['cost'];
                $qty = rand(5, 20);
                $total = $prodCost * $qty;
                $grandTotal += $total;
                
                DB::table('purchase_details')->insert([
                    'purchase_id' => $purchaseId,
                    'product_id' => $randProdId,
                    'product_variant_id' => null,
                    'cost' => $prodCost,
                    'purchase_unit_id' => 1,
                    'TaxNet' => 0.0,
                    'tax_method' => '1',
                    'discount' => 0.0,
                    'discount_method' => '1',
                    'quantity' => $qty,
                    'total' => $total,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            DB::table('purchases')->insert([
                'id' => $purchaseId,
                'user_id' => $userId,
                'Ref' => $ref,
                'date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                'time' => '12:00:00',
                'provider_id' => $providerId,
                'warehouse_id' => $warehouseId,
                'tax_rate' => 0.0,
                'TaxNet' => 0.0,
                'discount' => 0.0,
                'shipping' => 0.0,
                'GrandTotal' => $grandTotal,
                'paid_amount' => $grandTotal,
                'statut' => 'completed',
                'payment_statut' => 'paid',
                'notes' => 'Bulk stock purchase.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Payment method logic: cash, bkash, card
            $pmMethodId = rand(1, 3); 
            DB::table('payment_purchases')->insert([
                'user_id' => $userId,
                'date' => now()->format('Y-m-d'),
                'Ref' => 'PAY-PR-' . str_pad($p, 4, '0', STR_PAD_LEFT),
                'purchase_id' => $purchaseId,
                'account_id' => null,
                'montant' => $grandTotal,
                'change' => 0.0,
                'payment_method_id' => $pmMethodId,
                'notes' => 'Paid via method ID ' . $pmMethodId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // --- SEED SALES (25) ---
        for ($s = 1; $s <= 25; $s++) {
            $ref = 'SL-' . str_pad($s, 4, '0', STR_PAD_LEFT);
            $userId = rand(1, 5);
            $clientId = rand(1, 5);
            $warehouseId = rand(1, 3);
            
            $soldProducts = [];
            $totalProductsCount = rand(1, 4);
            $grandTotal = 0;
            
            $saleId = $s;
            
            for ($k = 0; $k < $totalProductsCount; $k++) {
                $randProdId = rand(1, 200);
                if (in_array($randProdId, $soldProducts)) {
                    continue;
                }
                $soldProducts[] = $randProdId;
                
                $prodPrice = $products[$randProdId - 1]['price'];
                $qty = rand(1, 5);
                $total = $prodPrice * $qty;
                $grandTotal += $total;
                
                DB::table('sale_details')->insert([
                    'sale_id' => $saleId,
                    'product_id' => $randProdId,
                    'product_variant_id' => null,
                    'price' => $prodPrice,
                    'sale_unit_id' => 1,
                    'TaxNet' => 0.0,
                    'tax_method' => '1',
                    'discount' => 0.0,
                    'discount_method' => '1',
                    'price_type' => 'retail',
                    'quantity' => $qty,
                    'total' => $total,
                    'date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            DB::table('sales')->insert([
                'id' => $saleId,
                'user_id' => $userId,
                'Ref' => $ref,
                'date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                'time' => '14:30:00',
                'is_pos' => 1,
                'client_id' => $clientId,
                'warehouse_id' => $warehouseId,
                'tax_rate' => 0.0,
                'TaxNet' => 0.0,
                'discount' => 0.0,
                'shipping' => 0.0,
                'GrandTotal' => $grandTotal,
                'paid_amount' => $grandTotal,
                'statut' => 'completed',
                'payment_statut' => 'paid',
                'notes' => 'Standard client retail sale.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $pmMethodId = rand(1, 3); // Cash, Card or bKash
            DB::table('payment_sales')->insert([
                'user_id' => $userId,
                'date' => now()->format('Y-m-d'),
                'Ref' => 'PAY-SL-' . str_pad($s, 4, '0', STR_PAD_LEFT),
                'sale_id' => $saleId,
                'account_id' => null,
                'montant' => $grandTotal,
                'change' => 0.0,
                'payment_method_id' => $pmMethodId,
                'notes' => 'Paid via method ID ' . $pmMethodId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // --- SEED TRANSFERS (10) ---
        for ($t = 1; $t <= 10; $t++) {
            $ref = 'TR-' . str_pad($t, 4, '0', STR_PAD_LEFT);
            $userId = rand(1, 5);
            $fromWh = rand(1, 2);
            $toWh = $fromWh + 1; // 1->2, 2->3
            
            $transferredProducts = [];
            $totalProductsCount = rand(1, 3);
            $grandTotal = 0;
            
            $transferId = $t;
            
            for ($k = 0; $k < $totalProductsCount; $k++) {
                $randProdId = rand(1, 200);
                if (in_array($randProdId, $transferredProducts)) {
                    continue;
                }
                $transferredProducts[] = $randProdId;
                
                $prodCost = $products[$randProdId - 1]['cost'];
                $qty = rand(5, 10);
                $total = $prodCost * $qty;
                $grandTotal += $total;
                
                DB::table('transfer_details')->insert([
                    'transfer_id' => $transferId,
                    'product_id' => $randProdId,
                    'product_variant_id' => null,
                    'cost' => $prodCost,
                    'purchase_unit_id' => 1,
                    'TaxNet' => 0.0,
                    'tax_method' => '1',
                    'discount' => 0.0,
                    'discount_method' => '1',
                    'quantity' => $qty,
                    'total' => $total,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            DB::table('transfers')->insert([
                'id' => $transferId,
                'user_id' => $userId,
                'Ref' => $ref,
                'date' => now()->subDays(rand(1, 15))->format('Y-m-d'),
                'time' => '10:00:00',
                'from_warehouse_id' => $fromWh,
                'to_warehouse_id' => $toWh,
                'items' => count($transferredProducts),
                'tax_rate' => 0.0,
                'TaxNet' => 0.0,
                'discount' => 0.0,
                'shipping' => 0.0,
                'GrandTotal' => $grandTotal,
                'statut' => 'completed',
                'approval_status' => 'approved',
                'notes' => 'Internal stock redistribution.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // --- SEED SELL RETURNS (5) ---
        for ($sr = 1; $sr <= 5; $sr++) {
            $ref = 'RT-SL-' . str_pad($sr, 4, '0', STR_PAD_LEFT);
            $userId = rand(1, 5);
            
            // Link to a sale (1 to 5)
            $saleId = $sr;
            $saleDetails = DB::table('sale_details')->where('sale_id', $saleId)->get();
            if ($saleDetails->isEmpty()) {
                continue;
            }
            
            $grandTotal = 0;
            $saleInfo = DB::table('sales')->where('id', $saleId)->first();
            
            foreach ($saleDetails as $sd) {
                // Return exactly 1 quantity of each product from the sale
                $returnQty = 1;
                $total = $sd->price * $returnQty;
                $grandTotal += $total;
                
                DB::table('sale_return_details')->insert([
                    'sale_return_id' => $sr,
                    'product_id' => $sd->product_id,
                    'product_variant_id' => null,
                    'price' => $sd->price,
                    'sale_unit_id' => 1,
                    'TaxNet' => 0.0,
                    'tax_method' => '1',
                    'discount' => 0.0,
                    'discount_method' => '1',
                    'quantity' => $returnQty,
                    'total' => $total,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            DB::table('sale_returns')->insert([
                'id' => $sr,
                'user_id' => $userId,
                'Ref' => $ref,
                'sale_id' => $saleId,
                'date' => now()->format('Y-m-d'),
                'time' => '16:00:00',
                'client_id' => $saleInfo->client_id,
                'warehouse_id' => $saleInfo->warehouse_id,
                'tax_rate' => 0.0,
                'TaxNet' => 0.0,
                'discount' => 0.0,
                'shipping' => 0.0,
                'GrandTotal' => $grandTotal,
                'paid_amount' => $grandTotal,
                'statut' => 'completed',
                'payment_statut' => 'paid',
                'notes' => 'Customer product return.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $pmMethodId = rand(1, 3);
            DB::table('payment_sale_returns')->insert([
                'user_id' => $userId,
                'date' => now()->format('Y-m-d'),
                'Ref' => 'PAY-RT-SL-' . str_pad($sr, 4, '0', STR_PAD_LEFT),
                'sale_return_id' => $sr,
                'account_id' => null,
                'montant' => $grandTotal,
                'change' => 0.0,
                'payment_method_id' => $pmMethodId,
                'notes' => 'Refunded via method ID ' . $pmMethodId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // --- SEED PURCHASE RETURNS (5) ---
        for ($pr = 1; $pr <= 5; $pr++) {
            $ref = 'RT-PR-' . str_pad($pr, 4, '0', STR_PAD_LEFT);
            $userId = rand(1, 5);
            
            // Link to a purchase (1 to 5)
            $purchaseId = $pr;
            $purchaseDetails = DB::table('purchase_details')->where('purchase_id', $purchaseId)->get();
            if ($purchaseDetails->isEmpty()) {
                continue;
            }
            
            $grandTotal = 0;
            $purchaseInfo = DB::table('purchases')->where('id', $purchaseId)->first();
            
            foreach ($purchaseDetails as $pd) {
                // Return exactly 1 quantity of each product from the purchase
                $returnQty = 1;
                $total = $pd->cost * $returnQty;
                $grandTotal += $total;
                
                DB::table('purchase_return_details')->insert([
                    'purchase_return_id' => $pr,
                    'product_id' => $pd->product_id,
                    'product_variant_id' => null,
                    'cost' => $pd->cost,
                    'purchase_unit_id' => 1,
                    'TaxNet' => 0.0,
                    'tax_method' => '1',
                    'discount' => 0.0,
                    'discount_method' => '1',
                    'quantity' => $returnQty,
                    'total' => $total,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            DB::table('purchase_returns')->insert([
                'id' => $pr,
                'user_id' => $userId,
                'Ref' => $ref,
                'purchase_id' => $purchaseId,
                'date' => now()->format('Y-m-d'),
                'time' => '11:00:00',
                'provider_id' => $purchaseInfo->provider_id,
                'warehouse_id' => $purchaseInfo->warehouse_id,
                'tax_rate' => 0.0,
                'TaxNet' => 0.0,
                'discount' => 0.0,
                'shipping' => 0.0,
                'GrandTotal' => $grandTotal,
                'paid_amount' => $grandTotal,
                'statut' => 'completed',
                'payment_statut' => 'paid',
                'notes' => 'Supplier defect product return.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $pmMethodId = rand(1, 3);
            DB::table('payment_purchase_returns')->insert([
                'user_id' => $userId,
                'date' => now()->format('Y-m-d'),
                'Ref' => 'PAY-RT-PR-' . str_pad($pr, 4, '0', STR_PAD_LEFT),
                'purchase_return_id' => $pr,
                'account_id' => null,
                'montant' => $grandTotal,
                'change' => 0.0,
                'payment_method_id' => $pmMethodId,
                'notes' => 'Refunded via method ID ' . $pmMethodId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 12. Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
