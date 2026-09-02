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

            $qty = (float) ($item['quantity'] ?? 0);
            $requiredCount = (int) floor($qty);
            $serials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            if (count($serials) !== $requiredCount) {
                $errors[] = "Product " . ($item['name'] ?? "ID $productId") . " requires $requiredCount serial numbers, but " . count($serials) . " were provided.";
                continue;
            }

            // Check for duplicates in current input
            if (count(array_unique($serials)) !== count($serials)) {
                $errors[] = "Product " . ($item['name'] ?? "ID $productId") . " contains duplicate serial numbers in the input.";
                continue;
            }

            // Check for global duplicates in DB that are currently available/active in stock
            // or have already been registered under a normal purchase (purchase_line_id is not null)
            $query = ProductSerialNumber::whereIn('serial_no', $serials)
                ->where(function ($q) {
                    $q->whereIn('status', ['available', 'in_service'])
                      ->orWhereNotNull('purchase_line_id');
                });
            if ($purchaseId) {
                // Ignore serials registered under current purchase
                $query->where(function ($q) use ($purchaseId) {
                    $q->whereNull('purchase_line_id')
                      ->orWhereHas('purchaseLine', function ($qp) use ($purchaseId) {
                          $qp->where('purchase_id', '!=', $purchaseId);
                      });
                });
            }

            $dbDuplicates = $query->pluck('serial_no')->toArray();
            if (!empty($dbDuplicates)) {
                $errors[] = "The following serial numbers are already registered in the system: " . implode(', ', $dbDuplicates);
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
            $this->reverseForPurchaseDetail($detail);

            if ($purchase->statut === 'received') {
                foreach ($serials as $serialNo) {
                    $cleanNo = trim((string) $serialNo);
                    $serial = ProductSerialNumber::where('serial_no', $cleanNo)->first();
                    if (!$serial) {
                        $serial = ProductSerialNumber::whereRaw('LOWER(TRIM(serial_no)) = ?', [strtolower($cleanNo)])->first();
                    }

                    if ($serial && $serial->status === 'sold' && is_null($serial->purchase_line_id)) {
                        // Negative-sold serial: link the purchase without changing sold status or resetting references
                        $serial->update([
                            'product_id' => $productId,
                            'variation_id' => $detail->product_variant_id,
                            'purchase_line_id' => $detail->id,
                        ]);
                    } else {
                        // Create or update serial number
                        $serial = ProductSerialNumber::updateOrCreate(
                            ['serial_no' => $cleanNo],
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
                    }

                    // Log history
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

    /**
     * Reverse serials for a single purchase detail line
     */
    protected function reverseForPurchaseDetail(PurchaseDetail $detail): void
    {
        $serialNumbers = ProductSerialNumber::where('purchase_line_id', $detail->id)->get();
        foreach ($serialNumbers as $serial) {
            if ($serial->status === 'sold') {
                throw new \Exception("Cannot modify/delete purchase: Serial number {$serial->serial_no} is already sold.");
            }
            SerialNumberHistory::where('serial_number_id', $serial->id)->delete();
            $serial->delete();
        }
    }

    /**
     * Reverse serials for multiple purchase detail lines
     */
    public function reverseForPurchaseDetails($purchaseDetails): void
    {
        if (!$this->isSupported()) {
            return;
        }

        foreach ($purchaseDetails as $detail) {
            if ($detail && isset($detail->id)) {
                $this->reverseForPurchaseDetail($detail);
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

        $posSetting = \App\Models\PosSetting::whereNull('deleted_at')->first();
        $allowOverselling = $posSetting ? (bool) $posSetting->allow_overselling : false;

        foreach ($details as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId || !$this->isProductTracked($productId)) {
                continue;
            }

            $qty = (float) ($item['quantity'] ?? 0);
            $requiredCount = (int) floor($qty);
            $serials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            if (count($serials) !== $requiredCount) {
                $errors[] = "Product " . ($item['name'] ?? "ID $productId") . " requires $requiredCount serial numbers, but " . count($serials) . " were selected.";
                continue;
            }

            if (count(array_unique($serials)) !== count($serials)) {
                $errors[] = "Product " . ($item['name'] ?? "ID $productId") . " contains duplicate serial numbers in the input.";
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
                    if (!$allowOverselling) {
                        $errors[] = "Serial number $cleanNo does not exist in the database.";
                    }
                } elseif ((int) $serial->product_id !== (int) $productId) {
                    $errors[] = "Serial number $cleanNo belongs to a different product.";
                } elseif (($serial->status !== 'available' || $serial->service_job_id !== null) && ! $belongsToCurrentSale) {
                    if (!$allowOverselling || $serial->status === 'sold') {
                        $errors[] = "Serial number $cleanNo is not available (Current status: {$serial->status}).";
                    }
                } elseif (! $belongsToCurrentSale && !empty($serial->current_location_id) && (int)$serial->current_location_id !== (int)$warehouseId) {
                    if (!$allowOverselling) {
                        $errors[] = "Serial number $cleanNo is not located in the selected warehouse.";
                    }
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
                        'current_location_id' => $sale->warehouse_id,
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
                } else {
                    // Create negative-sold serial directly in sold status with no purchase reference
                    $serial = ProductSerialNumber::create([
                        'serial_no' => $cleanNo,
                        'product_id' => $productId,
                        'variation_id' => $detail->product_variant_id,
                        'status' => 'sold',
                        'sold_sell_line_id' => $detail->id,
                        'current_customer_id' => $sale->client_id,
                        'current_location_id' => $sale->warehouse_id,
                    ]);

                    SerialNumberHistory::create([
                        'serial_number_id' => $serial->id,
                        'type' => 'sell',
                        'reference_id' => $sale->id,
                        'from_location_id' => $sale->warehouse_id,
                        'to_location_id' => null,
                        'customer_id' => $sale->client_id,
                        'notes' => 'Sold (Negative Sell) via Sale: ' . $sale->Ref,
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
            if (is_null($serial->purchase_line_id)) {
                // Negative-sold serial that was never purchased: delete it and its histories completely
                SerialNumberHistory::where('serial_number_id', $serial->id)->delete();
                $serial->delete();
            } else {
                // Restore status to available
                $serial->update([
                    'status' => 'available',
                    'sold_sell_line_id' => null,
                    'current_customer_id' => null,
                ]);

                // Delete the sale history record
                SerialNumberHistory::where('serial_number_id', $serial->id)
                    ->where('type', 'sell')
                    ->where('reference_id', $detail->sale_id)
                    ->delete();
            }
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

            $qty = (float) ($item['quantity'] ?? 0);
            $requiredCount = (int) floor($qty);
            $serials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            if (count($serials) !== $requiredCount) {
                $errors[] = "Product " . ($item['name'] ?? "ID $productId") . " requires $requiredCount serial numbers, but " . count($serials) . " were selected.";
                continue;
            }

            if (count(array_unique($serials)) !== count($serials)) {
                $errors[] = "Product " . ($item['name'] ?? "ID $productId") . " contains duplicate serial numbers in the input.";
                continue;
            }

            // Verify they exist and are available in fromWarehouseId
            foreach ($serials as $serialNo) {
                $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                if (!$serial) {
                    $errors[] = "Serial number $serialNo does not exist in the database.";
                } elseif ((int) $serial->product_id !== (int) $productId) {
                    $errors[] = "Serial number $serialNo belongs to a different product.";
                } elseif ($serial->status !== 'available' || $serial->service_job_id !== null) {
                    $errors[] = "Serial number $serialNo is not available (Current status: {$serial->status}).";
                } elseif ($serial->current_location_id != $fromWarehouseId) {
                    $errors[] = "Serial number $serialNo is not located in the source warehouse.";
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

            $qty = (float) ($item['quantity'] ?? 0);
            $type = $item['type'] ?? 'sub';
            $serials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            if (count($serials) !== (int) $qty) {
                $errors[] = "Product " . ($item['name'] ?? "ID $productId") . ": Quantity is $qty, but " . count($serials) . " serial numbers were provided.";
                continue;
            }

            if ($type === 'sub') {
                foreach ($serials as $serialNo) {
                    $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                    if (!$serial) {
                        $errors[] = "Serial number $serialNo does not exist in the database.";
                    } elseif ($serial->status !== 'available' || $serial->service_job_id !== null) {
                        $errors[] = "Serial number $serialNo is not available (Current status: {$serial->status}).";
                    } elseif ($serial->current_location_id != $warehouseId) {
                        $errors[] = "Serial number $serialNo is not located in the selected warehouse.";
                    }
                }
            } else {
                if (count(array_unique($serials)) !== count($serials)) {
                    $errors[] = "Product " . ($item['name'] ?? "ID $productId") . " contains duplicate serial numbers in the input.";
                    continue;
                }

                $dbDuplicates = ProductSerialNumber::whereIn('serial_no', $serials)
                    ->whereIn('status', ['available', 'in_service'])
                    ->pluck('serial_no')
                    ->toArray();
                if (!empty($dbDuplicates)) {
                    $errors[] = "The following serial numbers are already registered in the system: " . implode(', ', $dbDuplicates);
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
    public function validatePurchaseReturn(array $details, int $warehouseId): array
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

            $qty = (float) ($item['quantity'] ?? 0);
            $serials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            if (count($serials) !== (int) $qty) {
                $errors[] = "Product " . ($item['name'] ?? "ID $productId") . ": Quantity is $qty, but " . count($serials) . " serial numbers were selected.";
                continue;
            }

            foreach ($serials as $serialNo) {
                $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                if (!$serial) {
                    $errors[] = "Serial number $serialNo does not exist in the database.";
                } elseif ($serial->status !== 'available' || $serial->service_job_id !== null) {
                    $errors[] = "Serial number $serialNo is not available (Current status: {$serial->status}).";
                } elseif ($serial->current_location_id != $warehouseId) {
                    $errors[] = "Serial number $serialNo is not located in the selected warehouse.";
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

            $serials = $this->parseSerials($row['serial_numbers'] ?? $row['imei_number'] ?? '');
            if (empty($serials)) {
                continue;
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

            $qty = (float) ($item['quantity'] ?? 0);
            $serials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            if (count($serials) !== (int) $qty) {
                $errors[] = "Product " . ($item['name'] ?? "ID $productId") . ": Quantity is $qty, but " . count($serials) . " serial numbers were selected.";
                continue;
            }

            foreach ($serials as $serialNo) {
                $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                if ($serial && $serial->status === 'available' && $serial->current_location_id == $warehouseId) {
                    $errors[] = "Serial number $serialNo is already available in the selected warehouse.";
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

            $qty = (float) ($item['quantity'] ?? 0);
            $requiredCount = (int) floor($qty);
            $serials = $this->parseSerials($item['serial_numbers'] ?? $item['imei_number'] ?? '');

            if (count($serials) !== $requiredCount) {
                $errors[] = "Product " . ($item['name'] ?? "ID $productId") . " requires $requiredCount serial numbers, but " . count($serials) . " were selected.";
                continue;
            }

            if (count(array_unique($serials)) !== count($serials)) {
                $errors[] = "Product " . ($item['name'] ?? "ID $productId") . " contains duplicate serial numbers in the input.";
                continue;
            }

            foreach ($serials as $serialNo) {
                $serial = ProductSerialNumber::where('serial_no', $serialNo)->first();
                if (!$serial) {
                    $errors[] = "Serial number $serialNo does not exist in the database.";
                } elseif ($serial->status !== 'available' || $serial->service_job_id !== null) {
                    $errors[] = "Serial number $serialNo is not available (Current status: {$serial->status}).";
                } elseif ($serial->current_location_id != $warehouseId) {
                    $errors[] = "Serial number $serialNo is not located in the selected warehouse.";
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
}
