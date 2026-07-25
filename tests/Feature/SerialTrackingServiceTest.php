<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\product_warehouse;
use App\Models\ProductSerialNumber;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Transfer;
use App\Models\TransferDetail;
use App\Models\Adjustment;
use App\Models\AdjustmentDetail;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetails;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetails;
use App\Services\SerialTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SerialTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SerialTrackingService $service;
    protected Product $serialProduct;
    protected Product $normalProduct;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SerialTrackingService::class);

        // Create warehouses
        $this->warehouseA = Warehouse::create(['name' => 'Warehouse A', 'city' => 'City A', 'mobile' => '123', 'email' => 'a@test.com']);
        $this->warehouseB = Warehouse::create(['name' => 'Warehouse B', 'city' => 'City B', 'mobile' => '456', 'email' => 'b@test.com']);

        // Create serial tracking product
        $this->serialProduct = Product::create([
            'code' => 'PROD-SERIAL',
            'name' => 'Serial Product',
            'type' => 'is_single',
            'Type_barcode' => 'CODE128',
            'category_id' => 1,
            'unit_id' => 1,
            'unit_purchase_id' => 1,
            'unit_sale_id' => 1,
            'price' => 100,
            'cost' => 80,
            'wholesale_price' => 100,
            'min_price' => 80,
            'enable_serial_tracking' => true,
        ]);

        // Create normal product
        $this->normalProduct = Product::create([
            'code' => 'PROD-NORMAL',
            'name' => 'Normal Product',
            'type' => 'is_single',
            'Type_barcode' => 'CODE128',
            'category_id' => 1,
            'unit_id' => 1,
            'unit_purchase_id' => 1,
            'unit_sale_id' => 1,
            'price' => 50,
            'cost' => 40,
            'wholesale_price' => 50,
            'min_price' => 40,
            'enable_serial_tracking' => false,
        ]);
    }

    public function test_validate_purchase_requires_serials_for_serial_tracking_products(): void
    {
        // Purchase with missing serial_numbers
        $details = [
            [
                'product_id' => $this->serialProduct->id,
                'quantity' => 2,
                'serial_numbers' => null,
            ]
        ];

        $errors = $this->service->validatePurchase($details);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('requires 2 serial numbers', $errors[0]);

        // Purchase with incorrect number of serials
        $details[0]['serial_numbers'] = ['SN1'];
        $errors = $this->service->validatePurchase($details);
        $this->assertNotEmpty($errors);

        // Purchase with duplicate serials in the payload
        $details[0]['serial_numbers'] = ['SN1', 'SN1'];
        $errors = $this->service->validatePurchase($details);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('duplicate serial number', $errors[0]);

        // Purchase with valid serials
        $details[0]['serial_numbers'] = ['SN1', 'SN2'];
        $errors = $this->service->validatePurchase($details);
        $this->assertEmpty($errors);
    }

    public function test_apply_for_purchase_inserts_serials_correctly(): void
    {
        $purchase = Purchase::forceCreate([
            'warehouse_id' => $this->warehouseA->id,
            'provider_id' => 1,
            'date' => now()->toDateString(),
            'Ref' => 'PR-0001',
            'statut' => 'received',
            'user_id' => 1,
            'GrandTotal' => 160,
            'payment_statut' => 'unpaid',
        ]);

        $detail = PurchaseDetail::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->serialProduct->id,
            'quantity' => 2,
            'cost' => 80,
            'total' => 160,
        ]);

        $inputDetails = [
            [
                'product_id' => $this->serialProduct->id,
                'serial_numbers' => ['SN101', 'SN102'],
            ]
        ];

        $this->service->applyForPurchase($purchase, $inputDetails, [$detail]);

        // Assert database has the serials
        $this->assertDatabaseHas('product_serial_numbers', [
            'product_id' => $this->serialProduct->id,
            'serial_no' => 'SN101',
            'current_location_id' => $this->warehouseA->id,
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('product_serial_numbers', [
            'product_id' => $this->serialProduct->id,
            'serial_no' => 'SN102',
            'current_location_id' => $this->warehouseA->id,
            'status' => 'available',
        ]);
    }

    public function test_validate_sale_checks_serials_exist_and_are_active(): void
    {
        // Add serials to DB first
        ProductSerialNumber::create([
            'product_id' => $this->serialProduct->id,
            'serial_no' => 'SN-ACTIVE',
            'current_location_id' => $this->warehouseA->id,
            'status' => 'available',
        ]);

        ProductSerialNumber::create([
            'product_id' => $this->serialProduct->id,
            'serial_no' => 'SN-SOLD',
            'current_location_id' => $this->warehouseA->id,
            'status' => 'sold',
        ]);

        // Missing serials
        $details = [
            [
                'product_id' => $this->serialProduct->id,
                'quantity' => 1,
                'serial_numbers' => null,
            ]
        ];
        $errors = $this->service->validateSale($details, $this->warehouseA->id);
        $this->assertNotEmpty($errors);

        // Incorrect quantity count
        $details[0]['serial_numbers'] = ['SN-ACTIVE', 'SN-OTHER'];
        $errors = $this->service->validateSale($details, $this->warehouseA->id);
        $this->assertNotEmpty($errors);

        // Non-existent serial
        $details[0]['quantity'] = 1;
        $details[0]['serial_numbers'] = ['SN-NONEXISTENT'];
        $errors = $this->service->validateSale($details, $this->warehouseA->id);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('does not exist', $errors[0]);

        // Sold serial
        $details[0]['serial_numbers'] = ['SN-SOLD'];
        $errors = $this->service->validateSale($details, $this->warehouseA->id);
        $this->assertNotEmpty($errors);

        // Wrong warehouse serial
        $details[0]['serial_numbers'] = ['SN-ACTIVE'];
        $errors = $this->service->validateSale($details, $this->warehouseB->id);
        $this->assertNotEmpty($errors);

        // Active correct warehouse serial
        $errors = $this->service->validateSale($details, $this->warehouseA->id);
        $this->assertEmpty($errors);
    }

    public function test_apply_for_sale_marks_serials_as_sold(): void
    {
        $serial = ProductSerialNumber::create([
            'product_id' => $this->serialProduct->id,
            'serial_no' => 'SN-TO-SELL',
            'current_location_id' => $this->warehouseA->id,
            'status' => 'available',
        ]);

        $sale = Sale::forceCreate([
            'warehouse_id' => $this->warehouseA->id,
            'client_id' => 1,
            'date' => now()->toDateString(),
            'Ref' => 'SL-0001',
            'user_id' => 1,
            'payment_statut' => 'unpaid',
            'statut' => 'completed',
        ]);

        $detail = SaleDetail::forceCreate([
            'sale_id' => $sale->id,
            'product_id' => $this->serialProduct->id,
            'quantity' => 1,
            'price' => 100,
            'total' => 100,
            'date' => now()->toDateString(),
        ]);

        $inputDetails = [
            [
                'product_id' => $this->serialProduct->id,
                'serial_numbers' => ['SN-TO-SELL'],
            ]
        ];

        $this->service->applyForSale($sale, $inputDetails, [$detail]);

        $this->assertDatabaseHas('product_serial_numbers', [
            'id' => $serial->id,
            'status' => 'sold',
        ]);

        // Reverse sale check
        $this->service->reverseForSaleDetails([$detail]);
        $this->assertDatabaseHas('product_serial_numbers', [
            'id' => $serial->id,
            'status' => 'available',
        ]);
    }
}
