<?php

namespace App\Console\Commands;

use App\Models\ProductSerialNumber;
use App\Services\SerialTrackingService;
use Illuminate\Console\Command;

class RepairRepurchasedSerialsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'serials:repair-repurchased';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan and repair product serial numbers that were repurchased but remain marked as sold';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(SerialTrackingService $serialService)
    {
        $this->info('Scanning product serial numbers for repurchased items stuck in sold status...');

        if (!$serialService->isSupported()) {
            $this->warn('Serial tracking is not supported or tables missing.');
            return 0;
        }

        $repairedCount = 0;

        // 1. Scan all serials currently marked as 'sold'
        $soldSerials = ProductSerialNumber::where('status', 'sold')->get();

        foreach ($soldSerials as $serial) {
            $serialService->healRepurchasedSerial($serial);
            $serial->refresh();

            if ($serial->status === 'available') {
                $repairedCount++;
                $this->line("Repaired serial [{$serial->serial_no}] (Product ID: {$serial->product_id}) -> Restored to available in warehouse #{$serial->current_location_id}");
            }
        }

        // 2. Also scan received PurchaseDetails with imei_number to catch any past purchases
        $receivedPurchaseLines = \App\Models\PurchaseDetail::whereHas('purchase', function ($q) {
                $q->where('statut', 'received');
            })
            ->whereNotNull('imei_number')
            ->where('imei_number', '!=', '')
            ->get();

        foreach ($receivedPurchaseLines as $pl) {
            $serials = $serialService->parseSerials($pl->imei_number);
            foreach ($serials as $sn) {
                $cleanNo = trim((string) $sn);
                if ($cleanNo === '') {
                    continue;
                }

                $serialObj = ProductSerialNumber::where('serial_no', $cleanNo)->first();
                if ($serialObj && $serialObj->status === 'sold') {
                    $serialService->healRepurchasedSerial($serialObj);
                    $serialObj->refresh();
                    if ($serialObj->status === 'available') {
                        $repairedCount++;
                        $this->line("Repaired serial [{$serialObj->serial_no}] from Purchase Ref: {$pl->purchase->Ref} -> Available in warehouse #{$serialObj->current_location_id}");
                    }
                }
            }
        }

        $this->info("Completed. Repaired {$repairedCount} serials.");

        return 0;
    }
}

