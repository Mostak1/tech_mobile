<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductSerialNumber;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SerialTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleProfitCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected SerialTrackingService $serialService;
    protected Warehouse $warehouse;
    protected Client $client;
    protected User $user;
    protected Unit $unit;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $this->serialService = app(SerialTrackingService::class);
        $this->warehouse = Warehouse::create(['name' => 'Main Warehouse', 'city' => 'Dhaka', 'mobile' => '12345678', 'email' => 'wh@test.com']);
        $this->client = Client::create(['name' => 'John Doe', 'code' => 'CLI-1', 'email' => 'client@test.com', 'phone' => '123456']);
        $this->user = User::factory()->create();
        $this->category = Category::create(['name' => 'Mobile Phones', 'code' => 'MOB']);
        $this->unit = Unit::create(['name' => 'Piece', 'ShortName' => 'pc', 'operator' => '*', 'operator_value' => 1]);
    }

    public function test_profit_calculated_from_purchase_cost_at_sale_date(): void
    {
        // Create Product
        $product = Product::create([
            'code' => 'PHONE-A',
            'name' => 'Phone Model A',
            'type' => 'is_single',
            'Type_barcode' => 'CODE128',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'unit_purchase_id' => $this->unit->id,
            'unit_sale_id' => $this->unit->id,
            'price' => 400,
            'cost' => 250,
            'enable_serial_tracking' => false,
        ]);

        // Purchase Batch 1: 5 qty @ 250 Taka on 2026-01-01
        $purchase1 = Purchase::create([
            'user_id' => $this->user->id,
            'date' => '2026-01-01',
            'Ref' => 'PR_001',
            'warehouse_id' => $this->warehouse->id,
            'provider_id' => 1,
            'GrandTotal' => 1250,
            'statut' => 'received',
            'discount' => 0,
            'shipping' => 0,
            'TaxNet' => 0,
            'paid_amount' => 1250,
            'payment_statut' => 'paid',
        ]);

        $pd1 = PurchaseDetail::create([
            'cost' => 250,
            'purchase_id' => $purchase1->id,
            'product_id' => $product->id,
            'volume' => 0,
            'TaxNet' => 0,
            'tax_method' => '1',
            'discount' => 0,
            'discount_method' => '1',
            'quantity' => 5,
            'total' => 1250,
        ]);

        // Sale 1: 1 qty sold for 400 Taka on 2026-01-05
        $sale1 = Sale::create([
            'user_id' => $this->user->id,
            'date' => '2026-01-05',
            'time' => '10:00:00',
            'Ref' => 'SO_001',
            'is_pos' => 0,
            'client_id' => $this->client->id,
            'warehouse_id' => $this->warehouse->id,
            'GrandTotal' => 400,
            'discount' => 0,
            'shipping' => 0,
            'TaxNet' => 0,
            'statut' => 'completed',
            'payment_statut' => 'paid',
            'paid_amount' => 400,
        ]);

        $sd1 = SaleDetail::create([
            'date' => '2026-01-05',
            'sale_id' => $sale1->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 400,
            'total' => 400,
            'TaxNet' => 0,
            'discount' => 0,
            'discount_method' => '1',
            'tax_method' => '1',
        ]);

        // Verify profit for Sale 1 before Purchase 2
        $costsMap1 = $this->serialService->calculateSalePurchaseCosts([$sale1]);
        $this->assertEquals(250.0, $costsMap1[$sale1->id]);
        $profit1 = 400 - $costsMap1[$sale1->id];
        $this->assertEquals(150.0, $profit1);

        // Purchase Batch 2: 6 qty @ 300 Taka on 2026-01-10
        $purchase2 = Purchase::create([
            'user_id' => $this->user->id,
            'date' => '2026-01-10',
            'Ref' => 'PR_002',
            'warehouse_id' => $this->warehouse->id,
            'provider_id' => 1,
            'GrandTotal' => 1800,
            'statut' => 'received',
            'discount' => 0,
            'shipping' => 0,
            'TaxNet' => 0,
            'paid_amount' => 1800,
            'payment_statut' => 'paid',
        ]);

        $pd2 = PurchaseDetail::create([
            'cost' => 300,
            'purchase_id' => $purchase2->id,
            'product_id' => $product->id,
            'volume' => 0,
            'TaxNet' => 0,
            'tax_method' => '1',
            'discount' => 0,
            'discount_method' => '1',
            'quantity' => 6,
            'total' => 1800,
        ]);

        // Verify profit for Sale 1 AFTER Purchase 2 (MUST STILL BE 150, NOT recalculated with 300 cost!)
        $costsMap1After = $this->serialService->calculateSalePurchaseCosts([$sale1]);
        $this->assertEquals(250.0, $costsMap1After[$sale1->id]);

        // Sale 2: 1 qty sold for 400 Taka on 2026-01-12 (after Purchase 2)
        $sale2 = Sale::create([
            'user_id' => $this->user->id,
            'date' => '2026-01-12',
            'time' => '11:00:00',
            'Ref' => 'SO_002',
            'is_pos' => 0,
            'client_id' => $this->client->id,
            'warehouse_id' => $this->warehouse->id,
            'GrandTotal' => 400,
            'discount' => 0,
            'shipping' => 0,
            'TaxNet' => 0,
            'statut' => 'completed',
            'payment_statut' => 'paid',
            'paid_amount' => 400,
        ]);

        $sd2 = SaleDetail::create([
            'date' => '2026-01-12',
            'sale_id' => $sale2->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 400,
            'total' => 400,
            'TaxNet' => 0,
            'discount' => 0,
            'discount_method' => '1',
            'tax_method' => '1',
        ]);

        // Verify profit for Sale 2 (Uses 300 purchase cost from active batch as of 2026-01-12)
        $costsMap2 = $this->serialService->calculateSalePurchaseCosts([$sale2]);
        $this->assertEquals(300.0, $costsMap2[$sale2->id]);
        $profit2 = 400 - $costsMap2[$sale2->id];
        $this->assertEquals(100.0, $profit2);
    }

    public function test_profit_calculated_from_specific_imei_serial_purchase_cost(): void
    {
        if (!$this->serialService->isSupported()) {
            $this->markTestSkipped('Serial tracking DB tables not present.');
        }

        $product = Product::create([
            'code' => 'PHONE-IMEI',
            'name' => 'IMEI Phone',
            'type' => 'is_single',
            'Type_barcode' => 'CODE128',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'unit_purchase_id' => $this->unit->id,
            'unit_sale_id' => $this->unit->id,
            'price' => 500,
            'cost' => 250,
            'enable_serial_tracking' => true,
        ]);

        // Purchase 1: IMEI-101 purchased @ 250 Taka
        $purchase1 = Purchase::create(['user_id' => $this->user->id, 'date' => '2026-01-01', 'Ref' => 'PR_101', 'warehouse_id' => $this->warehouse->id, 'provider_id' => 1, 'GrandTotal' => 250, 'statut' => 'received', 'discount' => 0, 'shipping' => 0, 'TaxNet' => 0, 'paid_amount' => 250, 'payment_statut' => 'paid']);
        $pd1 = PurchaseDetail::create(['cost' => 250, 'purchase_id' => $purchase1->id, 'product_id' => $product->id, 'volume' => 0, 'TaxNet' => 0, 'tax_method' => '1', 'discount' => 0, 'discount_method' => '1', 'quantity' => 1, 'total' => 250]);
        $sn1 = ProductSerialNumber::create(['product_id' => $product->id, 'purchase_line_id' => $pd1->id, 'serial_no' => 'IMEI-101', 'status' => 'available', 'current_location_id' => $this->warehouse->id]);

        // Purchase 2: IMEI-102 purchased @ 300 Taka
        $purchase2 = Purchase::create(['user_id' => $this->user->id, 'date' => '2026-01-02', 'Ref' => 'PR_102', 'warehouse_id' => $this->warehouse->id, 'provider_id' => 1, 'GrandTotal' => 300, 'statut' => 'received', 'discount' => 0, 'shipping' => 0, 'TaxNet' => 0, 'paid_amount' => 300, 'payment_statut' => 'paid']);
        $pd2 = PurchaseDetail::create(['cost' => 300, 'purchase_id' => $purchase2->id, 'product_id' => $product->id, 'volume' => 0, 'TaxNet' => 0, 'tax_method' => '1', 'discount' => 0, 'discount_method' => '1', 'quantity' => 1, 'total' => 300]);
        $sn2 = ProductSerialNumber::create(['product_id' => $product->id, 'purchase_line_id' => $pd2->id, 'serial_no' => 'IMEI-102', 'status' => 'available', 'current_location_id' => $this->warehouse->id]);

        // Sale: Selling IMEI-101 (bought @ 250) on 2026-01-05 for 500 Taka
        $sale = Sale::create(['user_id' => $this->user->id, 'date' => '2026-01-05', 'time' => '12:00:00', 'Ref' => 'SO_101', 'is_pos' => 0, 'client_id' => $this->client->id, 'warehouse_id' => $this->warehouse->id, 'GrandTotal' => 500, 'discount' => 0, 'shipping' => 0, 'TaxNet' => 0, 'statut' => 'completed', 'payment_statut' => 'paid', 'paid_amount' => 500]);
        $sd = SaleDetail::create(['date' => '2026-01-05', 'sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 500, 'total' => 500, 'TaxNet' => 0, 'discount' => 0, 'discount_method' => '1', 'tax_method' => '1', 'imei_number' => 'IMEI-101']);
        $sn1->update(['status' => 'sold', 'sold_sell_line_id' => $sd->id]);

        // Calculate purchase cost for this sale detail
        $costRes = $this->serialService->getSaleDetailPurchaseCost($sd, '2026-01-05');
        $this->assertEquals(250.0, $costRes['unit_cost']);
        $this->assertEquals(250.0, $costRes['total_cost']);

        $profit = 500 - $costRes['total_cost'];
        $this->assertEquals(250.0, $profit);
    }
}
