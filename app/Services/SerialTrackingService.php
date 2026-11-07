<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSerialNumber;
use App\Models\SerialNumberHistory;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class SerialTrackingService
{
    /**
     * Check if serial tracking is supported.
     */
    public function isSupported(): bool
    {
        return Schema::hasTable('product_serial_numbers')
            && Schema::hasTable('serial_number_histories')
            && Schema::hasColumn('products', 'enable_serial_tracking');
    }

    /**
     * Check if a product is serial tracked.
     */
    public function isProductTracked($productId): bool
    {
        if (!$this->isSupported() || !$productId) {
            return false;
        }
        return (bool) Product::whereKey($productId)->value('enable_serial_tracking');
    }

    /**
     * Resolve product name and SKU / code label for user-friendly errors
     */
    public function getProductLabel($productId, $item = []): array
    {
        $name = $item['name'] ?? null;
        $code = $item['code'] ?? null;
        if (!$name || !$code) {
            $product = Product::find($productId);
            if ($product) {
                $name = $name ?: $product->name;
                $code = $code ?: $product->code;
            }
        }
        $name = $name ?: "Product #{$productId}";
        $code = $code ?: "N/A";
        return [
            'name' => $name,
            'code' => $code,
            'label' => "Product Name: {$name}, SKU: {$code}",
        ];
    }

    /**
     * Extract serial numbers from string (comma/newline/space separated)
     */
    public function parseSerials($input): array
    {
        if (is_array($input)) {
            return array_filter(array_map('trim', $input));
        }

        if (empty($input)) {
            return [];
        }

        // Split by commas, newlines, semicolons, or multiple spaces
        $raw = preg_split('/[\n\r,;\s]+/', (string) $input);
        return array_filter(array_map('trim', $raw));
    }

    /**
     * Validate purchase serial inputs (duplicate and count check)
     */
    public function validatePurchase(array $details, ?int $purchaseId = null): array
    {
        $errors = [];
        if (!$this->isSupported()) {
            return $errors;
        }

        foreach ($details as $index => $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId || !$this->isProductTracked($productId)) {
                continue;
            }

            $pInfo = $this->getProductLabel($productId, $item);
            $pLabel = $pInfo['label'];

            $qty = (float) ($item['quantity'] ?? 0);
            $requiredCount = (int) floor($qty);
            $serials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            if (count($serials) !== $requiredCount) {
                $errors[] = "Quantity Unavailable: Product requires $requiredCount serial numbers, but " . count($serials) . " were provided. {$pLabel}.";
                continue;
            }

            // Check for duplicates in current input
            if (count(array_unique($serials)) !== count($serials)) {
                $errors[] = "Duplicate Submission: Duplicate serial numbers detected in input for {$pLabel}.";
                continue;
            }

            // Check for global duplicates in DB that are currently available/active in stock
            $query = ProductSerialNumber::whereIn('serial_no', $serials)
                ->whereIn('status', ['available', 'in_service']);
            if ($purchaseId) {
                // Ignore serials registered under current purchase
                $purchaseLines = PurchaseDetail::where('purchase_id', $purchaseId)->get();
                $existingLineIds = $purchaseLines->pluck('id')->toArray();
                $existingPurchaseSerials = [];
                foreach ($purchaseLines as $pl) {
                    foreach ($this->parseSerials($pl->imei_number) as $ps) {
                        $existingPurchaseSerials[$ps] = true;
                    }
                }

                $query->where(function ($q) use ($existingLineIds, $existingPurchaseSerials) {
                    $q->where(function ($sub) use ($existingLineIds) {
                        $sub->whereNull('purchase_line_id')
                            ->orWhereNotIn('purchase_line_id', $existingLineIds);
                    });
                    if (!empty($existingPurchaseSerials)) {
                        $q->whereNotIn('serial_no', array_keys($existingPurchaseSerials));
                    }
                });
            }

            $dbDuplicates = $query->pluck('serial_no')->toArray();
            if (!empty($dbDuplicates)) {
                $errors[] = "Duplicate Submission: The serial numbers (" . implode(', ', $dbDuplicates) . ") are already registered in the system. {$pLabel}.";
            }
        }

        return $errors;
    }

    /**
     * Apply serials for a purchase
     */
    public function applyForPurchase(Purchase $purchase, array $inputDetails, $persistedDetails): void
    {
        if (!$this->isSupported()) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();
        $userId = Auth::id();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (!$detail) {
                continue;
            }

            $productId = $row['product_id'] ?? null;
            if (!$this->isProductTracked($productId)) {
                continue;
            }

            // Parse serial numbers
            $serials = $this->parseSerials($row['serial_numbers'] ?? $row['imei_number'] ?? '');
            if (empty($serials)) {
                continue;
            }

            // Reverse existing serial numbers for this purchase line (handles updates cleanly)
            $this->reverseForPurchaseDetail($detail, $serials);

            if ($purchase->statut === 'received') {
                foreach ($serials as $serialNo) {
                    $existingSerial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                    if ($existingSerial && $existingSerial->status === 'sold') {
                        $existingSerial->update([
                            'product_id' => $productId,
                            'variation_id' => $detail->product_variant_id,
                            'purchase_line_id' => $detail->id,
                        ]);
                        continue;
                    }

                    // Create or update serial number
                    $serial = ProductSerialNumber::updateOrCreate(
                        ['serial_no' => $serialNo],
                        [
                            'product_id' => $productId,
                            'variation_id' => $detail->product_variant_id,
                            'purchase_line_id' => $detail->id,
                            'status' => 'available',
                            'current_location_id' => $purchase->warehouse_id,
                            'current_customer_id' => null,
                            'sold_sell_line_id' => null,
                            'service_job_id' => null,
                        ]
                    );

                    // Log history only if not already logged for this purchase
                    $historyExists = SerialNumberHistory::where('serial_number_id', $serial->id)
                        ->where('type', 'purchase')
                        ->where('reference_id', $purchase->id)
                        ->exists();

                    if (!$historyExists) {
                        SerialNumberHistory::create([
                            'serial_number_id' => $serial->id,
                            'type' => 'purchase',
                            'reference_id' => $purchase->id,
                            'from_location_id' => null,
                            'to_location_id' => $purchase->warehouse_id,
                            'customer_id' => null,
                            'notes' => 'Registered via Purchase: ' . $purchase->Ref,
                            'created_by' => $userId,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse serials for a single purchase detail line
     */
    public function reverseForPurchaseDetail(PurchaseDetail $detail, array $allNewSerials = []): void
    {
        $serialNumbers = ProductSerialNumber::where('purchase_line_id', $detail->id)->get();
        foreach ($serialNumbers as $serial) {
            if ($serial->status === 'sold') {
                if (in_array($serial->serial_no, $allNewSerials)) {
                    continue;
                }
                throw new \Exception("Cannot remove serial number {$serial->serial_no} from purchase because it is already sold.");
            }
            if (!in_array($serial->serial_no, $allNewSerials)) {
                SerialNumberHistory::where('serial_number_id', $serial->id)->delete();
                $serial->delete();
            }
        }
    }

    /**
     * Reverse serials for multiple purchase detail lines
     */
    public function reverseForPurchaseDetails($purchaseDetails, array $allNewSerials = []): void
    {
        if (!$this->isSupported()) {
            return;
        }

        foreach ($purchaseDetails as $detail) {
            if ($detail && isset($detail->id)) {
                $this->reverseForPurchaseDetail($detail, $allNewSerials);
            }
        }
    }

    /**
     * Validate sale serial inputs
     */
    public function validateSale(array $details, int $warehouseId, ?int $saleId = null): array
    {
        $errors = [];
        if (!$this->isSupported()) {
            return $errors;
        }

        foreach ($details as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId || !$this->isProductTracked($productId)) {
                continue;
            }

            $pInfo = $this->getProductLabel($productId, $item);
            $pLabel = $pInfo['label'];

            $qty = (float) ($item['quantity'] ?? 0);
            $requiredCount = (int) floor($qty);
            $serials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            if (count($serials) !== $requiredCount) {
                $errors[] = "Quantity Unavailable: Product requires $requiredCount serial numbers, but " . count($serials) . " were selected. {$pLabel}.";
                continue;
            }

            if (count(array_unique($serials)) !== count($serials)) {
                $errors[] = "Duplicate Submission: Duplicate serial numbers detected in input for {$pLabel}.";
                continue;
            }

            // Verify they exist and are available in this warehouse
            foreach ($serials as $serialNo) {
                $cleanNo = trim((string) $serialNo);
                $serial = ProductSerialNumber::where('serial_no', $cleanNo)->first();
                if (!$serial) {
                    $serial = ProductSerialNumber::whereRaw('LOWER(TRIM(serial_no)) = ?', [strtolower($cleanNo)])->first();
                }
                $belongsToCurrentSale = $saleId && $serial && $serial->soldSellLine
                    && (int) $serial->soldSellLine->sale_id === (int) $saleId;
                if (!$serial) {
                    $errors[] = "Product Not Found: Serial number '$cleanNo' does not exist in the database. {$pLabel}.";
                } elseif ((int) $serial->product_id !== (int) $productId) {
                    $errors[] = "Product Mismatch: Serial number '$cleanNo' belongs to a different product. {$pLabel}.";
                } elseif ($serial->status === 'sold' && ! $belongsToCurrentSale) {
                    $errors[] = "Product Already Sold: This product has already been sold (Serial: '$cleanNo'). {$pLabel}.";
                } elseif (($serial->status !== 'available' || $serial->service_job_id !== null) && ! $belongsToCurrentSale) {
                    $errors[] = "Quantity Unavailable: Serial number '$cleanNo' is not available (Current status: {$serial->status}). {$pLabel}.";
                } elseif (! $belongsToCurrentSale && !empty($serial->current_location_id) && (int)$serial->current_location_id !== (int)$warehouseId) {
                    $errors[] = "Insufficient Stock: Serial number '$cleanNo' is not located in the selected warehouse. {$pLabel}.";
                }
            }
        }

        return $errors;
    }

    /**
     * Apply serials for a sale
     */
    public function applyForSale(Sale $sale, array $inputDetails, $persistedDetails): void
    {
        if (!$this->isSupported()) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();
        $userId = Auth::id();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (!$detail) {
                continue;
            }

            $productId = $row['product_id'] ?? null;
            if (!$this->isProductTracked($productId)) {
                continue;
            }

            $serials = $this->parseSerials($row['serial_numbers'] ?? $row['imei_number'] ?? '');
            if (empty($serials)) {
                continue;
            }

            // Reverse old sell mappings for this detail
            $this->reverseForSaleDetail($detail);

            foreach ($serials as $serialNo) {
                $cleanNo = trim((string) $serialNo);
                $serial = ProductSerialNumber::where('serial_no', $cleanNo)->first();
                if (!$serial) {
                    $serial = ProductSerialNumber::whereRaw('LOWER(TRIM(serial_no)) = ?', [strtolower($cleanNo)])->first();
                }
                if ($serial) {
                    $serial->update([
                        'status' => 'sold',
                        'sold_sell_line_id' => $detail->id,
                        'current_customer_id' => $sale->client_id,
                    ]);

                    SerialNumberHistory::create([
                        'serial_number_id' => $serial->id,
                        'type' => 'sell',
                        'reference_id' => $sale->id,
                        'from_location_id' => $sale->warehouse_id,
                        'to_location_id' => null,
                        'customer_id' => $sale->client_id,
                        'notes' => 'Sold via Sale: ' . $sale->Ref,
                        'created_by' => $userId,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse sale detail mapping
     */
    protected function reverseForSaleDetail(SaleDetail $detail): void
    {
        $serialNumbers = ProductSerialNumber::where('sold_sell_line_id', $detail->id)->get();
        foreach ($serialNumbers as $serial) {
            // Restore status to available
            $serial->update([
                'status' => 'available',
                'sold_sell_line_id' => null,
                'current_customer_id' => null,
            ]);

            // Add history record for reversal if needed, or simply delete sale histories
            SerialNumberHistory::where('serial_number_id', $serial->id)
                ->where('type', 'sell')
                ->where('reference_id', $detail->sale_id)
                ->delete();
        }
    }

    /**
     * Reverse multiple sale details mapping
     */
    public function reverseForSaleDetails($saleDetails): void
    {
        if (!$this->isSupported()) {
            return;
        }

        foreach ($saleDetails as $detail) {
            if ($detail && isset($detail->id)) {
                $this->reverseForSaleDetail($detail);
            }
        }
    }

    /**
     * Validate transfer serial inputs
     */
    public function validateTransfer(array $details, int $fromWarehouseId): array
    {
        $errors = [];
        if (!$this->isSupported()) {
            return $errors;
        }

        foreach ($details as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId || !$this->isProductTracked($productId)) {
                continue;
            }

            $pInfo = $this->getProductLabel($productId, $item);
            $pLabel = $pInfo['label'];

            $qty = (float) ($item['quantity'] ?? 0);
            $requiredCount = (int) floor($qty);
            $serials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            if (count($serials) !== $requiredCount) {
                $errors[] = "Quantity Unavailable: Product requires $requiredCount serial numbers, but " . count($serials) . " were selected. {$pLabel}.";
                continue;
            }

            if (count(array_unique($serials)) !== count($serials)) {
                $errors[] = "Duplicate Submission: Duplicate serial numbers detected in input for {$pLabel}.";
                continue;
            }

            // Verify they exist and are available in fromWarehouseId
            foreach ($serials as $serialNo) {
                $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                if (!$serial) {
                    $errors[] = "Product Not Found: Serial number '$serialNo' does not exist in the database. {$pLabel}.";
                } elseif ((int) $serial->product_id !== (int) $productId) {
                    $errors[] = "Product Mismatch: Serial number '$serialNo' belongs to a different product. {$pLabel}.";
                } elseif ($serial->status !== 'available' || $serial->service_job_id !== null) {
                    $errors[] = "Quantity Unavailable: Serial number '$serialNo' is not available (Current status: {$serial->status}). {$pLabel}.";
                } elseif ($serial->current_location_id != $fromWarehouseId) {
                    $errors[] = "Insufficient Stock: Serial number '$serialNo' is not located in the source warehouse. {$pLabel}.";
                }
            }
        }

        return $errors;
    }

    /**
     * Apply serials for a transfer
     */
    public function applyForTransfer($transfer, array $inputDetails, $persistedDetails): void
    {
        if (!$this->isSupported()) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();
        $userId = Auth::id();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (!$detail) {
                continue;
            }

            $productId = $row['product_id'] ?? null;
            if (!$this->isProductTracked($productId)) {
                continue;
            }

            $serials = $this->parseSerials($row['serial_numbers'] ?? $row['imei_number'] ?? '');
            if (empty($serials)) {
                continue;
            }

            // Reverse existing transfer log/location update for this detail (handles updates cleanly)
            $this->reverseForTransferDetail($detail, $transfer->from_warehouse_id);

            if ($transfer->statut === 'completed') {
                foreach ($serials as $serialNo) {
                    $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                    if ($serial) {
                        $serial->update([
                            'current_location_id' => $transfer->to_warehouse_id,
                        ]);

                        SerialNumberHistory::create([
                            'serial_number_id' => $serial->id,
                            'type' => 'transfer',
                            'reference_id' => $transfer->id,
                            'from_location_id' => $transfer->from_warehouse_id,
                            'to_location_id' => $transfer->to_warehouse_id,
                            'customer_id' => null,
                            'notes' => 'Transferred via Transfer: ' . $transfer->Ref,
                            'created_by' => $userId,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse transfer detail mapping
     */
    protected function reverseForTransferDetail($detail, int $fromWarehouseId): void
    {
        $transferHistories = SerialNumberHistory::where('type', 'transfer')
            ->where('reference_id', $detail->transfer_id)
            ->get();

        foreach ($transferHistories as $history) {
            $serial = ProductSerialNumber::find($history->serial_number_id);
            if ($serial) {
                $serial->update([
                    'current_location_id' => $fromWarehouseId,
                ]);
            }
            $history->delete();
        }
    }

    /**
     * Reverse multiple transfer details mapping
     */
    public function reverseForTransferDetails($transferDetails, int $fromWarehouseId): void
    {
        if (!$this->isSupported()) {
            return;
        }

        foreach ($transferDetails as $detail) {
            if ($detail && isset($detail->id)) {
                $this->reverseForTransferDetail($detail, $fromWarehouseId);
            }
        }
    }

    /**
     * Validate adjustment serial inputs
     */
    public function validateAdjustment(array $details, int $warehouseId): array
    {
        $errors = [];
        if (!$this->isSupported()) {
            return $errors;
        }

        foreach ($details as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId || !$this->isProductTracked($productId)) {
                continue;
            }

            $pInfo = $this->getProductLabel($productId, $item);
            $pLabel = $pInfo['label'];

            $qty = (float) ($item['quantity'] ?? 0);
            $type = $item['type'] ?? 'sub';
            $serials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            if (count($serials) !== (int) $qty) {
                $errors[] = "Quantity Unavailable: Quantity is $qty, but " . count($serials) . " serial numbers were provided. {$pLabel}.";
                continue;
            }

            if ($type === 'sub') {
                foreach ($serials as $serialNo) {
                    $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                    if (!$serial) {
                        $errors[] = "Product Not Found: Serial number '$serialNo' does not exist in the database. {$pLabel}.";
                    } elseif ($serial->status !== 'available' || $serial->service_job_id !== null) {
                        $errors[] = "Quantity Unavailable: Serial number '$serialNo' is not available (Current status: {$serial->status}). {$pLabel}.";
                    } elseif ($serial->current_location_id != $warehouseId) {
                        $errors[] = "Insufficient Stock: Serial number '$serialNo' is not located in the selected warehouse. {$pLabel}.";
                    }
                }
            } else {
                if (count(array_unique($serials)) !== count($serials)) {
                    $errors[] = "Duplicate Submission: Duplicate serial numbers detected in input for {$pLabel}.";
                    continue;
                }

                $dbDuplicates = ProductSerialNumber::whereIn('serial_no', $serials)
                    ->whereIn('status', ['available', 'in_service'])
                    ->pluck('serial_no')
                    ->toArray();
                if (!empty($dbDuplicates)) {
                    $errors[] = "Duplicate Submission: Serial numbers (" . implode(', ', $dbDuplicates) . ") are already registered in the system. {$pLabel}.";
                }
            }
        }

        return $errors;
    }

    /**
     * Apply serials for an adjustment
     */
    public function applyForAdjustment($adjustment, array $inputDetails, $persistedDetails): void
    {
        if (!$this->isSupported()) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();
        $userId = Auth::id();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (!$detail) {
                continue;
            }

            $productId = $row['product_id'] ?? null;
            if (!$this->isProductTracked($productId)) {
                continue;
            }

            $serials = $this->parseSerials($row['serial_numbers'] ?? $row['imei_number'] ?? '');
            if (empty($serials)) {
                continue;
            }

            $this->reverseForAdjustmentDetail($detail);

            $type = $row['type'] ?? 'sub';
            if ($type === 'add') {
                foreach ($serials as $serialNo) {
                    $serial = ProductSerialNumber::updateOrCreate(
                        ['serial_no' => $serialNo],
                        [
                            'product_id' => $productId,
                            'variation_id' => $detail->product_variant_id,
                            'status' => 'available',
                            'current_location_id' => $adjustment->warehouse_id,
                        ]
                    );

                    SerialNumberHistory::create([
                        'serial_number_id' => $serial->id,
                        'type' => 'adjustment',
                        'reference_id' => $adjustment->id,
                        'from_location_id' => null,
                        'to_location_id' => $adjustment->warehouse_id,
                        'notes' => 'Added via Adjustment: ' . $adjustment->Ref,
                        'created_by' => $userId,
                    ]);
                }
            } else {
                foreach ($serials as $serialNo) {
                    $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                    if ($serial) {
                        $serial->update([
                            'status' => 'adjusted_out',
                        ]);

                        SerialNumberHistory::create([
                            'serial_number_id' => $serial->id,
                            'type' => 'adjustment',
                            'reference_id' => $adjustment->id,
                            'from_location_id' => $adjustment->warehouse_id,
                            'to_location_id' => null,
                            'notes' => 'Removed via Adjustment: ' . $adjustment->Ref,
                            'created_by' => $userId,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse adjustment detail mapping
     */
    protected function reverseForAdjustmentDetail($detail): void
    {
        $histories = SerialNumberHistory::where('type', 'adjustment')
            ->where('reference_id', $detail->adjustment_id)
            ->get();

        foreach ($histories as $history) {
            $serial = ProductSerialNumber::find($history->serial_number_id);
            if ($serial) {
                if ($history->to_location_id !== null) {
                    $serial->delete();
                } else {
                    $serial->update([
                        'status' => 'available',
                        'current_location_id' => $history->from_location_id,
                    ]);
                }
            }
            $history->delete();
        }
    }

    /**
     * Reverse multiple adjustment details mapping
     */
    public function reverseForAdjustmentDetails($adjustmentDetails): void
    {
        if (!$this->isSupported()) {
            return;
        }

        foreach ($adjustmentDetails as $detail) {
            if ($detail && isset($detail->id)) {
                $this->reverseForAdjustmentDetail($detail);
            }
        }
    }

    /**
     * Validate purchase return serial inputs
     */
    public function validatePurchaseReturn(array $details, int $warehouseId, ?int $returnId = null): array
    {
        $errors = [];
        if (!$this->isSupported()) {
            return $errors;
        }

        foreach ($details as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId || !$this->isProductTracked($productId)) {
                continue;
            }

            $pInfo = $this->getProductLabel($productId, $item);
            $pLabel = $pInfo['label'];

            $qty = (float) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $rawSerials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            // If more serials provided than quantity (e.g. original purchase serial list sent),
            // filter down to serials that are actually available in this warehouse for this return.
            if (count($rawSerials) > (int) $qty) {
                $query = ProductSerialNumber::whereIn('serial_no', $rawSerials)
                    ->where('product_id', $productId);
                if ($returnId) {
                    $query->where(function ($q) use ($warehouseId, $returnId) {
                        $q->where(function ($sub) use ($warehouseId) {
                            $sub->where('status', 'available')
                                ->where('current_location_id', $warehouseId);
                        })->orWhere(function ($sub) use ($returnId) {
                            $sub->where('status', 'returned_to_supplier')
                                ->whereHas('histories', function ($qh) use ($returnId) {
                                    $qh->where('type', 'purchase_return')
                                       ->where('reference_id', $returnId);
                                });
                        });
                    });
                } else {
                    $query->where('status', 'available')
                        ->where('current_location_id', $warehouseId)
                        ->whereNull('service_job_id');
                }
                $availableOnly = $query->pluck('serial_no')->toArray();
                if (count($availableOnly) >= (int) $qty) {
                    $serials = array_slice($availableOnly, 0, (int) $qty);
                } else {
                    $serials = $rawSerials;
                }
            } else {
                $serials = $rawSerials;
            }

            if (count($serials) !== (int) $qty) {
                $errors[] = "Quantity Unavailable: Product requires $qty serial numbers, but " . count($serials) . " were selected. {$pLabel}.";
                continue;
            }

            foreach ($serials as $serialNo) {
                $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                if (!$serial) {
                    $errors[] = "Product Not Found: Serial number '$serialNo' does not exist in the database. {$pLabel}.";
                    continue;
                }

                if ($returnId && $serial->status === 'returned_to_supplier') {
                    $wasReturnedHere = SerialNumberHistory::where('serial_number_id', $serial->id)
                        ->where('type', 'purchase_return')
                        ->where('reference_id', $returnId)
                        ->exists();
                    if ($wasReturnedHere) {
                        continue;
                    }
                }

                if ($serial->status === 'sold') {
                    $errors[] = "Product Already Sold: This product has already been sold and cannot be returned to supplier (Serial: '$serialNo'). {$pLabel}.";
                } elseif ($serial->status !== 'available' || $serial->service_job_id !== null) {
                    $errors[] = "Return Validation Failed: Product serial '$serialNo' is not available for return (Current status: {$serial->status}). {$pLabel}.";
                } elseif ($serial->current_location_id != $warehouseId) {
                    $errors[] = "Insufficient Stock: Serial number '$serialNo' is not located in the selected warehouse. {$pLabel}.";
                }
            }
        }

        return $errors;
    }

    /**
     * Apply serials for purchase return
     */
    public function applyForPurchaseReturn($return, array $inputDetails, $persistedDetails): void
    {
        if (!$this->isSupported()) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();
        $userId = Auth::id();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (!$detail) {
                continue;
            }

            $productId = $row['product_id'] ?? null;
            if (!$this->isProductTracked($productId)) {
                continue;
            }

            $qty = (float) ($row['quantity'] ?? $detail->quantity ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $rawSerials = $this->parseSerials($row['serial_numbers'] ?? $row['imei_number'] ?? '');
            if (empty($rawSerials)) {
                continue;
            }

            if (count($rawSerials) > (int) $qty) {
                $serials = ProductSerialNumber::whereIn('serial_no', $rawSerials)
                    ->where('product_id', $productId)
                    ->where('status', 'available')
                    ->where('current_location_id', $return->warehouse_id)
                    ->whereNull('service_job_id')
                    ->limit((int) $qty)
                    ->pluck('serial_no')
                    ->toArray();
            } else {
                $serials = $rawSerials;
            }

            $this->reverseForPurchaseReturnDetail($detail);

            if ($return->statut === 'completed') {
                foreach ($serials as $serialNo) {
                    $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                    if ($serial) {
                        $serial->update([
                            'status' => 'returned_to_supplier',
                            'current_location_id' => null,
                        ]);

                        SerialNumberHistory::create([
                            'serial_number_id' => $serial->id,
                            'type' => 'purchase_return',
                            'reference_id' => $return->id,
                            'from_location_id' => $return->warehouse_id,
                            'to_location_id' => null,
                            'notes' => 'Returned to supplier via Purchase Return: ' . $return->Ref,
                            'created_by' => $userId,
                        ]);
                    }
                }
            }
        }
    }

    protected function reverseForPurchaseReturnDetail($detail): void
    {
        $histories = SerialNumberHistory::where('type', 'purchase_return')
            ->where('reference_id', $detail->purchase_return_id)
            ->get();

        foreach ($histories as $history) {
            $serial = ProductSerialNumber::find($history->serial_number_id);
            if ($serial) {
                $serial->update([
                    'status' => 'available',
                    'current_location_id' => $history->from_location_id,
                ]);
            }
            $history->delete();
        }
    }

    public function reverseForPurchaseReturnDetails($returnDetails): void
    {
        if (!$this->isSupported()) {
            return;
        }

        foreach ($returnDetails as $detail) {
            if ($detail && isset($detail->id)) {
                $this->reverseForPurchaseReturnDetail($detail);
            }
        }
    }

    /**
     * Validate sale return serial inputs
     */
    public function validateSaleReturn(array $details, int $warehouseId): array
    {
        $errors = [];
        if (!$this->isSupported()) {
            return $errors;
        }

        foreach ($details as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId || !$this->isProductTracked($productId)) {
                continue;
            }

            $pInfo = $this->getProductLabel($productId, $item);
            $pLabel = $pInfo['label'];

            $qty = (float) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $serials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            if (count($serials) !== (int) $qty) {
                $errors[] = "Quantity Unavailable: Product requires $qty serial numbers, but " . count($serials) . " were selected. {$pLabel}.";
                continue;
            }

            foreach ($serials as $serialNo) {
                $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                if ($serial && (int) $serial->product_id !== (int) $productId) {
                    $errors[] = "Product Mismatch: Serial number '$serialNo' does not belong to {$pLabel}.";
                } elseif ($serial && $serial->status === 'available' && $serial->current_location_id == $warehouseId) {
                    $errors[] = "Return Validation Failed: Product serial '$serialNo' is already available in the selected warehouse (not sold). {$pLabel}.";
                }
            }
        }

        return $errors;
    }

    /**
     * Apply serials for sale return
     */
    public function applyForSaleReturn($return, array $inputDetails, $persistedDetails): void
    {
        if (!$this->isSupported()) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();
        $userId = Auth::id();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (!$detail) {
                continue;
            }

            $productId = $row['product_id'] ?? null;
            if (!$this->isProductTracked($productId)) {
                continue;
            }

            $serials = $this->parseSerials($row['serial_numbers'] ?? $row['imei_number'] ?? '');
            if (empty($serials)) {
                continue;
            }

            $this->reverseForSaleReturnDetail($detail);

            if ($return->statut === 'received' || $return->statut === 'completed') {
                foreach ($serials as $serialNo) {
                    $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                    if ($serial) {
                        $serial->update([
                            'status' => 'available',
                            'current_location_id' => $return->warehouse_id,
                            'current_customer_id' => null,
                            'sold_sell_line_id' => null,
                        ]);
                    } else {
                        $serial = ProductSerialNumber::create([
                            'serial_no' => $serialNo,
                            'product_id' => $productId,
                            'variation_id' => $detail->product_variant_id,
                            'status' => 'available',
                            'current_location_id' => $return->warehouse_id,
                        ]);
                    }

                    SerialNumberHistory::create([
                        'serial_number_id' => $serial->id,
                        'type' => 'sale_return',
                        'reference_id' => $return->id,
                        'from_location_id' => null,
                        'to_location_id' => $return->warehouse_id,
                        'customer_id' => $return->client_id,
                        'notes' => 'Returned by customer via Sale Return: ' . $return->Ref,
                        'created_by' => $userId,
                    ]);
                }
            }
        }
    }

    protected function reverseForSaleReturnDetail($detail): void
    {
        $histories = SerialNumberHistory::where('type', 'sale_return')
            ->where('reference_id', $detail->sale_return_id)
            ->get();

        foreach ($histories as $history) {
            $serial = ProductSerialNumber::find($history->serial_number_id);
            if ($serial) {
                $wasSoldBefore = SerialNumberHistory::where('serial_number_id', $serial->id)
                    ->where('type', 'sell')
                    ->latest()
                    ->first();

                if ($wasSoldBefore) {
                    $serial->update([
                        'status' => 'sold',
                        'current_location_id' => null,
                        'current_customer_id' => $wasSoldBefore->customer_id,
                    ]);
                } else {
                    $serial->delete();
                }
            }
            $history->delete();
        }
    }

    public function reverseForSaleReturnDetails($returnDetails): void
    {
        if (!$this->isSupported()) {
            return;
        }

        foreach ($returnDetails as $detail) {
            if ($detail && isset($detail->id)) {
                $this->reverseForSaleReturnDetail($detail);
            }
        }
    }

    /**
     * Get available serial numbers for a product/variant/warehouse
     */
    public function getAvailableSerials(int $productId, ?int $variantId, int $warehouseId): array
    {
        if (!$this->isSupported()) {
            return [];
        }

        $query = ProductSerialNumber::where('product_id', $productId)
            ->where('current_location_id', $warehouseId)
            ->where('status', 'available');

        if ($variantId !== null) {
            $query->where('variation_id', $variantId);
        } else {
            $query->whereNull('variation_id');
        }

        return $query->get(['id', 'serial_no', 'status'])->toArray();
    }

    /**
     * Validate damage serial inputs (checks they exist and are available in the warehouse)
     */
    public function validateDamage(array $details, int $warehouseId): array
    {
        $errors = [];
        if (!$this->isSupported()) {
            return $errors;
        }

        foreach ($details as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId || !$this->isProductTracked($productId)) {
                continue;
            }

            $pInfo = $this->getProductLabel($productId, $item);
            $pLabel = $pInfo['label'];

            $qty = (float) ($item['quantity'] ?? 0);
            $requiredCount = (int) floor($qty);
            $serials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            if (count($serials) !== $requiredCount) {
                $errors[] = "Quantity Unavailable: Product requires $requiredCount serial numbers, but " . count($serials) . " were selected. {$pLabel}.";
                continue;
            }

            if (count(array_unique($serials)) !== count($serials)) {
                $errors[] = "Duplicate Submission: Duplicate serial numbers detected in input for {$pLabel}.";
                continue;
            }

            foreach ($serials as $serialNo) {
                $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                if (!$serial) {
                    $errors[] = "Product Not Found: Serial number '$serialNo' does not exist in the database. {$pLabel}.";
                } elseif ($serial->status !== 'available' || $serial->service_job_id !== null) {
                    $errors[] = "Quantity Unavailable: Serial number '$serialNo' is not available (Current status: {$serial->status}). {$pLabel}.";
                } elseif ($serial->current_location_id != $warehouseId) {
                    $errors[] = "Insufficient Stock: Serial number '$serialNo' is not located in the selected warehouse. {$pLabel}.";
                }
            }
        }

        return $errors;
    }

    /**
     * Apply serials for a damage
     */
    public function applyForDamage($damage, array $inputDetails, $persistedDetails): void
    {
        if (!$this->isSupported()) {
            return;
        }

        $persistedDetails = collect($persistedDetails)->values();
        $userId = Auth::id();

        foreach (array_values($inputDetails) as $i => $row) {
            $detail = $persistedDetails->get($i);
            if (!$detail) {
                continue;
            }

            $productId = $row['product_id'] ?? null;
            if (!$this->isProductTracked($productId)) {
                continue;
            }

            $serials = $this->parseSerials($row['serial_numbers'] ?? $row['imei_number'] ?? '');
            if (empty($serials)) {
                continue;
            }

            $this->reverseForDamageDetail($detail);

            foreach ($serials as $serialNo) {
                $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                if ($serial) {
                    $serial->update([
                        'status' => 'damaged',
                        'current_location_id' => null,
                    ]);

                    SerialNumberHistory::create([
                        'serial_number_id' => $serial->id,
                        'type' => 'damage',
                        'reference_id' => $damage->id,
                        'from_location_id' => $damage->warehouse_id,
                        'to_location_id' => null,
                        'notes' => 'Marked as Damaged via Damage record: ' . $damage->Ref,
                        'created_by' => $userId,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse damage detail mapping
     */
    protected function reverseForDamageDetail($detail): void
    {
        $histories = SerialNumberHistory::where('type', 'damage')
            ->where('reference_id', $detail->damage_id)
            ->get();

        foreach ($histories as $history) {
            $serial = ProductSerialNumber::find($history->serial_number_id);
            if ($serial) {
                $serial->update([
                    'status' => 'available',
                    'current_location_id' => $history->from_location_id,
                ]);
            }
            $history->delete();
        }
    }

    /**
     * Reverse multiple damage details mapping
     */
    public function reverseForDamageDetails($damageDetails): void
    {
        if (!$this->isSupported()) {
            return;
        }

        foreach ($damageDetails as $detail) {
            if ($detail && isset($detail->id)) {
                $this->reverseForDamageDetail($detail);
            }
        }
    }

    /**
     * Apply serials for service job device check-in / check-out
     */
    public function applyForServiceJob($job): void
    {
        if (!$this->isSupported()) {
            return;
        }

        $userId = Auth::id();

        // Restore any serial numbers currently marked as in_service for this service job back to sold.
        $oldSerials = ProductSerialNumber::where('service_job_id', $job->id)
            ->where('status', 'in_service')
            ->get();

        foreach ($oldSerials as $oldSerial) {
            $oldSerial->update([
                'status' => 'sold',
                'service_job_id' => null,
            ]);

            SerialNumberHistory::where('serial_number_id', $oldSerial->id)
                ->where('type', 'service')
                ->where('reference_id', $job->id)
                ->where('notes', 'like', 'Device checked in%')
                ->delete();
        }

        // Now lookup the new device serial
        $serialNo = $job->device_serial ?: $job->device_imei;
        if ($serialNo) {
            $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
            if ($serial) {
                if (in_array($job->status, ['delivered', 'completed'])) {
                    $serial->update([
                        'status' => 'sold',
                        'service_job_id' => null,
                        'current_customer_id' => $job->client_id,
                    ]);

                    $historyExists = SerialNumberHistory::where('serial_number_id', $serial->id)
                        ->where('type', 'service')
                        ->where('reference_id', $job->id)
                        ->where('notes', 'like', 'Repaired and delivered%')
                        ->exists();

                    if (!$historyExists) {
                        SerialNumberHistory::create([
                            'serial_number_id' => $serial->id,
                            'type' => 'service',
                            'reference_id' => $job->id,
                            'from_location_id' => null,
                            'to_location_id' => null,
                            'customer_id' => $job->client_id,
                            'notes' => 'Repaired and delivered back to customer via Service Job: ' . $job->Ref,
                            'created_by' => $userId,
                        ]);
                    }
                } else {
                    $serial->update([
                        'status' => 'in_service',
                        'service_job_id' => $job->id,
                        'current_customer_id' => $job->client_id,
                    ]);

                    SerialNumberHistory::create([
                        'serial_number_id' => $serial->id,
                        'type' => 'service',
                        'reference_id' => $job->id,
                        'from_location_id' => null,
                        'to_location_id' => null,
                        'customer_id' => $job->client_id,
                        'notes' => 'Device checked in for service via Service Job: ' . $job->Ref . ' (Status: ' . $job->status . ')',
                        'created_by' => $userId,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse service job serials mapping
     */
    public function reverseForServiceJob($job): void
    {
        if (!$this->isSupported()) {
            return;
        }

        ProductSerialNumber::where('service_job_id', $job->id)
            ->where('status', 'in_service')
            ->update([
                'status' => 'sold',
                'service_job_id' => null
            ]);

        SerialNumberHistory::where('type', 'service')
            ->where('reference_id', $job->id)
            ->delete();
    }

    /**
     * Calculate the purchase unit cost and total purchase cost for a single SaleDetail.
     *
     * @param SaleDetail $detail
     * @param string|null $saleDate
     * @param array $preloadedData Optional pre-fetched maps for bulk performance
     * @return array ['unit_cost' => float, 'total_cost' => float]
     */
    public function getSaleDetailPurchaseCost($detail, ?string $saleDate = null, array $preloadedData = []): array
    {
        $productId = $detail->product_id;
        $variantId = $detail->product_variant_id;
        $qty = (float) $detail->quantity;
        if ($qty <= 0) {
            return ['unit_cost' => 0.0, 'total_cost' => 0.0];
        }

        $date = $saleDate ?: ($detail->date ?? optional($detail->sale)->date ?? date('Y-m-d'));

        $baseCost = null;
        $matchedSerialCount = 0;
        $totalMatchedSerialCost = 0.0;

        // 1. Check linked ProductSerialNumber records by sold_sell_line_id
        if (isset($preloadedData['serials_by_line'][$detail->id])) {
            $serials = $preloadedData['serials_by_line'][$detail->id];
        } elseif ($this->isSupported()) {
            $serials = ProductSerialNumber::with('purchaseLine')
                ->where('sold_sell_line_id', $detail->id)
                ->get();
        } else {
            $serials = collect();
        }

        foreach ($serials as $s) {
            if ($s->purchaseLine && (float) $s->purchaseLine->cost >= 0) {
                $totalMatchedSerialCost += (float) $s->purchaseLine->cost;
                $matchedSerialCount++;
            }
        }

        // 2. Check text IMEIs in imei_number if serials not fully matched
        if ($matchedSerialCount < (int) floor($qty) && !empty($detail->imei_number)) {
            $parsedImeis = $this->parseSerials($detail->imei_number);
            foreach ($parsedImeis as $imei) {
                $cleanNo = trim((string) $imei);
                if (isset($preloadedData['serials_by_no'][$cleanNo])) {
                    $serialObj = $preloadedData['serials_by_no'][$cleanNo];
                    if ($serialObj && $serialObj->purchaseLine && (float) $serialObj->purchaseLine->cost >= 0) {
                        $totalMatchedSerialCost += (float) $serialObj->purchaseLine->cost;
                        $matchedSerialCount++;
                    }
                } elseif ($this->isSupported()) {
                    $serialObj = ProductSerialNumber::with('purchaseLine')
                        ->where('serial_no', $cleanNo)
                        ->first();
                    if ($serialObj && $serialObj->purchaseLine && (float) $serialObj->purchaseLine->cost >= 0) {
                        $totalMatchedSerialCost += (float) $serialObj->purchaseLine->cost;
                        $matchedSerialCount++;
                    }
                }
            }
        }

        // If we matched serial/IMEI costs
        if ($matchedSerialCount > 0) {
            $avgSerialCost = $totalMatchedSerialCost / $matchedSerialCount;
            if ($matchedSerialCount >= (int) floor($qty)) {
                $baseCost = $avgSerialCost;
            } else {
                $unmatchedQty = $qty - $matchedSerialCount;
                $fallbackBaseCost = $this->getPurchaseCostAsOfDate($productId, $variantId, $date, $preloadedData);
                $baseCost = (($totalMatchedSerialCost) + ($unmatchedQty * $fallbackBaseCost)) / $qty;
            }
        }

        // 3. If no serial cost matched, lookup purchase cost as of sale date
        if ($baseCost === null) {
            $baseCost = $this->getPurchaseCostAsOfDate($productId, $variantId, $date, $preloadedData);
        }

        // 4. Apply unit conversion if saleUnit exists
        $unitCost = $baseCost;
        $saleUnit = $detail->saleUnit;
        if (!$saleUnit && $detail->sale_unit_id) {
            $saleUnit = $preloadedData['units'][$detail->sale_unit_id] ?? null;
        }

        if ($saleUnit && (float) $saleUnit->operator_value > 0) {
            $opVal = (float) $saleUnit->operator_value;
            $unitCost = $saleUnit->operator === '/' ? $baseCost / $opVal : $baseCost * $opVal;
        }

        return [
            'unit_cost' => (float) $unitCost,
            'total_cost' => (float) ($unitCost * $qty),
        ];
    }

    /**
     * Helper to get purchase cost as of sale date, fallback to any purchase or product cost.
     */
    public function getPurchaseCostAsOfDate($productId, $variantId, string $date, array $preloadedData = []): float
    {
        $key = $productId . ':' . ($variantId ?? 'null');
        $prodKey = $productId . ':all';

        // Preloaded purchase costs as of sale date if available
        if (isset($preloadedData['as_of_costs'][$key])) {
            return (float) $preloadedData['as_of_costs'][$key];
        }
        if (isset($preloadedData['as_of_costs'][$prodKey])) {
            return (float) $preloadedData['as_of_costs'][$prodKey];
        }

        // Query latest purchase received on or before sale date
        $pd = PurchaseDetail::join('purchases as p', 'p.id', '=', 'purchase_details.purchase_id')
            ->whereNull('p.deleted_at')
            ->where('p.statut', 'received')
            ->where('purchase_details.product_id', $productId)
            ->when($variantId, fn ($q) => $q->where('purchase_details.product_variant_id', $variantId))
            ->where('p.date', '<=', $date)
            ->select('purchase_details.cost')
            ->orderBy('p.date', 'desc')
            ->orderBy('purchase_details.id', 'desc')
            ->first();

        if ($pd && (float) $pd->cost >= 0) {
            return (float) $pd->cost;
        }

        // If product has variant, check any purchase for product without variant constraint on or before sale date
        if ($variantId) {
            $pdProd = PurchaseDetail::join('purchases as p', 'p.id', '=', 'purchase_details.purchase_id')
                ->whereNull('p.deleted_at')
                ->where('p.statut', 'received')
                ->where('purchase_details.product_id', $productId)
                ->where('p.date', '<=', $date)
                ->select('purchase_details.cost')
                ->orderBy('p.date', 'desc')
                ->orderBy('purchase_details.id', 'desc')
                ->first();

            if ($pdProd && (float) $pdProd->cost >= 0) {
                return (float) $pdProd->cost;
            }
        }

        // Fallback: earliest purchase after sale date if none before
        $pdAfter = PurchaseDetail::join('purchases as p', 'p.id', '=', 'purchase_details.purchase_id')
            ->whereNull('p.deleted_at')
            ->where('p.statut', 'received')
            ->where('purchase_details.product_id', $productId)
            ->select('purchase_details.cost')
            ->orderBy('p.date', 'asc')
            ->orderBy('purchase_details.id', 'asc')
            ->first();

        if ($pdAfter && (float) $pdAfter->cost >= 0) {
            return (float) $pdAfter->cost;
        }

        // Fallback: product variant cost or product default cost
        if ($variantId) {
            if (isset($preloadedData['product_costs']['variant:' . $variantId])) {
                return (float) $preloadedData['product_costs']['variant:' . $variantId];
            }
            $varCost = \App\Models\ProductVariant::whereKey($variantId)->value('cost');
            if ($varCost !== null && (float) $varCost >= 0) {
                return (float) $varCost;
            }
        }

        if (isset($preloadedData['product_costs'][$prodKey])) {
            return (float) $preloadedData['product_costs'][$prodKey];
        }

        $prodCost = Product::whereKey($productId)->value('cost');
        return (float) ($prodCost ?? 0.0);
    }

    /**
     * Calculate total purchase cost for a collection of Sale models or array of Sales.
     * Returns an array mapping [sale_id => total_cost].
     */
    public function calculateSalePurchaseCosts($sales): array
    {
        $saleCostsMap = [];

        $detailIds = [];
        $productIds = [];
        $variantIds = [];
        $allImeis = [];

        foreach ($sales as $sale) {
            $saleCostsMap[$sale->id] = 0.0;
            foreach ($sale->details as $detail) {
                if ($detail->id) {
                    $detailIds[] = $detail->id;
                }
                if ($detail->product_id) {
                    $productIds[] = $detail->product_id;
                }
                if ($detail->product_variant_id) {
                    $variantIds[] = $detail->product_variant_id;
                }
                if (!empty($detail->imei_number)) {
                    $allImeis = array_merge($allImeis, $this->parseSerials($detail->imei_number));
                }
            }
        }

        $detailIds = array_unique(array_filter($detailIds));
        $productIds = array_unique(array_filter($productIds));
        $variantIds = array_unique(array_filter($variantIds));
        $allImeis = array_unique(array_filter($allImeis));

        $serialsByLine = [];
        if (!empty($detailIds) && $this->isSupported()) {
            $serials = ProductSerialNumber::with('purchaseLine')
                ->whereIn('sold_sell_line_id', $detailIds)
                ->get();
            foreach ($serials as $s) {
                $serialsByLine[$s->sold_sell_line_id][] = $s;
            }
        }

        $serialsByNo = [];
        if (!empty($allImeis) && $this->isSupported()) {
            $serials = ProductSerialNumber::with('purchaseLine')
                ->whereIn('serial_no', $allImeis)
                ->get();
            foreach ($serials as $s) {
                $serialsByNo[$s->serial_no] = $s;
            }
        }

        $productCosts = [];
        if (!empty($productIds)) {
            $prods = Product::whereIn('id', $productIds)->get(['id', 'cost']);
            foreach ($prods as $p) {
                $productCosts[$p->id . ':all'] = (float) $p->cost;
            }
        }
        if (!empty($variantIds)) {
            $vars = \App\Models\ProductVariant::whereIn('id', $variantIds)->get(['id', 'cost']);
            foreach ($vars as $v) {
                $productCosts['variant:' . $v->id] = (float) $v->cost;
            }
        }

        $preloadedData = [
            'serials_by_line' => $serialsByLine,
            'serials_by_no' => $serialsByNo,
            'product_costs' => $productCosts,
        ];

        foreach ($sales as $sale) {
            $saleDate = $sale->date ?? date('Y-m-d');
            $costTotal = 0.0;
            foreach ($sale->details as $detail) {
                $costRes = $this->getSaleDetailPurchaseCost($detail, $saleDate, $preloadedData);
                $costTotal += $costRes['total_cost'];
            }
            $saleCostsMap[$sale->id] = $costTotal;
        }

        return $saleCostsMap;
    }
}

