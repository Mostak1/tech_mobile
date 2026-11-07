<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetails;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetails;
use App\Models\Unit;

class TransactionValidationService
{
    /**
     * Resolve product info (name, code/sku)
     */
    public function getProductInfo($productId, $variantId = null): array
    {
        $product = Product::find($productId);
        $name = $product ? $product->name : "Product #{$productId}";
        $sku = $product ? $product->code : "N/A";

        if ($variantId) {
            $variant = ProductVariant::find($variantId);
            if ($variant) {
                $name .= " [{$variant->name}]";
                if (!empty($variant->code)) {
                    $sku = $variant->code;
                }
            }
        }

        return [
            'product' => $product,
            'name' => $name,
            'sku' => $sku,
        ];
    }

    /**
     * Convert requested quantity to base warehouse stock units
     */
    public function convertToBaseQuantity(float $quantity, $unitId = null): float
    {
        if (!$unitId) {
            return $quantity;
        }
        $unit = Unit::find($unitId);
        if (!$unit) {
            return $quantity;
        }
        if ($unit->operator === '/') {
            return $quantity / (float) $unit->operator_value;
        }
        return $quantity * (float) $unit->operator_value;
    }

    /**
     * Validate sale stock before submission (for POS, Create Sale, Edit Sale)
     */
    public function validateSaleStock(array $details, int $warehouseId, ?int $saleId = null): array
    {
        $errors = [];

        foreach ($details as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId) {
                $errors[] = "Product Mismatch: Invalid product identifier provided.";
                continue;
            }

            $variantId = $item['product_variant_id'] ?? null;
            $info = $this->getProductInfo($productId, $variantId);
            $product = $info['product'];
            $pName = $info['name'];
            $pSku = $info['sku'];

            if (!$product) {
                $errors[] = "Product Not Found: Product not found. Product Name: {$pName}, SKU: {$pSku}.";
                continue;
            }

            // Skip services
            if ($product->type === 'is_service' || ($item['product_type'] ?? '') === 'is_service') {
                continue;
            }

            $requestedQty = (float) ($item['quantity'] ?? 0);
            if ($requestedQty <= 0) {
                continue;
            }

            $unitId = $item['sale_unit_id'] ?? $product->unit_sale_id;
            $requiredBaseQty = $this->convertToBaseQuantity($requestedQty, $unitId);

            // Find stock in warehouse
            $pwQuery = product_warehouse::whereNull('deleted_at')
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId);

            if ($variantId) {
                $pwQuery->where('product_variant_id', $variantId);
            } else {
                $pwQuery->whereNull('product_variant_id');
            }

            $pw = $pwQuery->first();

            // If manage_stock is explicitly disabled (0), skip
            if ($pw && isset($pw->manage_stock) && (int)$pw->manage_stock === 0) {
                continue;
            }

            $availableQty = $pw ? (float) $pw->qte : 0.0;

            // If updating an existing sale, add back the previously sold quantity in this sale
            if ($saleId) {
                $oldLine = SaleDetail::where('sale_id', $saleId)
                    ->where('product_id', $productId)
                    ->where('product_variant_id', $variantId)
                    ->first();
                if ($oldLine) {
                    $availableQty += $this->convertToBaseQuantity((float) $oldLine->quantity, $oldLine->sale_unit_id);
                }
            }

            if ($availableQty < $requiredBaseQty) {
                $reqDisplay = round($requiredBaseQty, 2);
                $availDisplay = round($availableQty, 2);
                $errors[] = "Quantity Unavailable: Insufficient stock for Product: {$pName}, SKU: {$pSku}. Required: {$reqDisplay}, Available: {$availDisplay}.";
            }
        }

        return $errors;
    }

    /**
     * Validate customer sale return
     */
    public function validateSaleReturn(array $details, int $warehouseId, ?int $saleId = null, ?int $returnId = null): array
    {
        $errors = [];

        foreach ($details as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId) {
                continue;
            }

            $variantId = $item['product_variant_id'] ?? null;
            $info = $this->getProductInfo($productId, $variantId);
            $pName = $info['name'];
            $pSku = $info['sku'];

            $returnQty = (float) ($item['quantity'] ?? 0);
            if ($returnQty <= 0) {
                continue;
            }

            if ($saleId) {
                $saleLine = SaleDetail::where('sale_id', $saleId)
                    ->where('product_id', $productId)
                    ->where('product_variant_id', $variantId)
                    ->first();

                if (!$saleLine) {
                    $errors[] = "Return Validation Failed: Unable to process return for Product: {$pName}, SKU: {$pSku}. Please verify the original sales transaction.";
                    continue;
                }

                // Calculate total previously returned for this sale item
                $priorReturnQuery = SaleReturn::where('sale_id', $saleId)
                    ->whereNull('deleted_at');
                if ($returnId) {
                    $priorReturnQuery->where('id', '!=', $returnId);
                }
                $priorReturnIds = $priorReturnQuery->pluck('id');

                $alreadyReturned = (float) SaleReturnDetails::whereIn('sale_return_id', $priorReturnIds)
                    ->where('product_id', $productId)
                    ->where('product_variant_id', $variantId)
                    ->sum('quantity');

                $maxReturnable = (float) $saleLine->quantity - $alreadyReturned;

                if ($returnQty > $maxReturnable) {
                    $errors[] = "Return Validation Failed: Return quantity exceeds refundable quantity for Product: {$pName}, SKU: {$pSku}. Sold: {$saleLine->quantity}, Already Returned: {$alreadyReturned}, Remaining: {$maxReturnable}, Attempted: {$returnQty}.";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate purchase return to supplier
     */
    public function validatePurchaseReturn(array $details, int $warehouseId, ?int $purchaseId = null, ?int $returnId = null): array
    {
        $errors = [];

        foreach ($details as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId) {
                continue;
            }

            $variantId = $item['product_variant_id'] ?? null;
            $info = $this->getProductInfo($productId, $variantId);
            $product = $info['product'];
            $pName = $info['name'];
            $pSku = $info['sku'];

            $returnQty = (float) ($item['quantity'] ?? 0);
            if ($returnQty <= 0) {
                continue;
            }

            // 1. Verify against original purchase if linked
            if ($purchaseId) {
                $purchaseLine = PurchaseDetail::where('purchase_id', $purchaseId)
                    ->where('product_id', $productId)
                    ->where('product_variant_id', $variantId)
                    ->first();

                if (!$purchaseLine) {
                    $errors[] = "Return Validation Failed: Unable to process return for Product: {$pName}, SKU: {$pSku}. Product was not part of the original purchase.";
                    continue;
                }

                $priorReturnQuery = PurchaseReturn::where('purchase_id', $purchaseId)
                    ->whereNull('deleted_at');
                if ($returnId) {
                    $priorReturnQuery->where('id', '!=', $returnId);
                }
                $priorReturnIds = $priorReturnQuery->pluck('id');

                $alreadyReturned = (float) PurchaseReturnDetails::whereIn('purchase_return_id', $priorReturnIds)
                    ->where('product_id', $productId)
                    ->where('product_variant_id', $variantId)
                    ->sum('quantity');

                $maxReturnable = (float) $purchaseLine->quantity - $alreadyReturned;
                if ($returnQty > $maxReturnable) {
                    $errors[] = "Return Validation Failed: Return quantity exceeds purchased quantity for Product: {$pName}, SKU: {$pSku}. Purchased: {$purchaseLine->quantity}, Already Returned: {$alreadyReturned}, Remaining: {$maxReturnable}, Attempted: {$returnQty}.";
                    continue;
                }
            }

            // 2. Verify on-hand warehouse stock for non-service items
            if ($product && $product->type !== 'is_service') {
                $unitId = $item['purchase_unit_id'] ?? $product->unit_purchase_id;
                $requiredBaseQty = $this->convertToBaseQuantity($returnQty, $unitId);

                $pw = product_warehouse::whereNull('deleted_at')
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $productId)
                    ->where('product_variant_id', $variantId)
                    ->first();

                $availableStock = $pw ? (float) $pw->qte : 0.0;

                // If updating return, add back old return quantity
                if ($returnId) {
                    $oldLine = PurchaseReturnDetails::where('purchase_return_id', $returnId)
                        ->where('product_id', $productId)
                        ->where('product_variant_id', $variantId)
                        ->first();
                    if ($oldLine) {
                        $availableStock += $this->convertToBaseQuantity((float) $oldLine->quantity, $oldLine->purchase_unit_id);
                    }
                }

                if ($availableStock < $requiredBaseQty) {
                    $reqDisplay = round($requiredBaseQty, 2);
                    $availDisplay = round($availableStock, 2);
                    $errors[] = "Insufficient stock for Product: {$pName}, SKU: {$pSku}. Required: {$reqDisplay}, Available: {$availDisplay}.";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate transfer stock from source warehouse
     */
    public function validateTransferStock(array $details, int $fromWarehouseId): array
    {
        $errors = [];

        foreach ($details as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId) {
                continue;
            }

            $variantId = $item['product_variant_id'] ?? null;
            $info = $this->getProductInfo($productId, $variantId);
            $product = $info['product'];
            $pName = $info['name'];
            $pSku = $info['sku'];

            if ($product && $product->type === 'is_service') {
                continue;
            }

            $transferQty = (float) ($item['quantity'] ?? 0);
            if ($transferQty <= 0) {
                continue;
            }

            $unitId = $item['purchase_unit_id'] ?? ($product ? $product->unit_purchase_id : null);
            $requiredBaseQty = $this->convertToBaseQuantity($transferQty, $unitId);

            $pw = product_warehouse::whereNull('deleted_at')
                ->where('warehouse_id', $fromWarehouseId)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->first();

            $availableStock = $pw ? (float) $pw->qte : 0.0;

            if ($availableStock < $requiredBaseQty) {
                $reqDisplay = round($requiredBaseQty, 2);
                $availDisplay = round($availableStock, 2);
                $errors[] = "Insufficient stock for Product: {$pName}, SKU: {$pSku}. Required: {$reqDisplay}, Available: {$availDisplay}.";
            }
        }

        return $errors;
    }

    /**
     * Validate adjustment stock (subtraction)
     */
    public function validateAdjustmentStock(array $details, int $warehouseId): array
    {
        $errors = [];

        foreach ($details as $item) {
            $type = $item['type'] ?? 'sub';
            if ($type !== 'sub') {
                continue;
            }

            $productId = $item['product_id'] ?? null;
            if (!$productId) {
                continue;
            }

            $variantId = $item['product_variant_id'] ?? null;
            $info = $this->getProductInfo($productId, $variantId);
            $product = $info['product'];
            $pName = $info['name'];
            $pSku = $info['sku'];

            if ($product && $product->type === 'is_service') {
                continue;
            }

            $subQty = (float) ($item['quantity'] ?? 0);
            if ($subQty <= 0) {
                continue;
            }

            $pw = product_warehouse::whereNull('deleted_at')
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->first();

            $availableStock = $pw ? (float) $pw->qte : 0.0;

            if ($availableStock < $subQty) {
                $reqDisplay = round($subQty, 2);
                $availDisplay = round($availableStock, 2);
                $errors[] = "Insufficient stock for Product: {$pName}, SKU: {$pSku}. Required: {$reqDisplay}, Available: {$availDisplay}.";
            }
        }

        return $errors;
    }
}

