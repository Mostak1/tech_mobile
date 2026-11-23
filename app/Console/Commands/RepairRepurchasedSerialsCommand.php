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

        $soldSerials = ProductSerialNumber::where('status', 'sold')
            ->whereNotNull('purchase_line_id')
            ->get();

        $repairedCount = 0;

        foreach ($soldSerials as $serial) {
            $originalStatus = $serial->status;
            $serialService->healRepurchasedSerial($serial);
            $serial->refresh();

            if ($serial->status === 'available') {
                $repairedCount++;
                $this->line("Repaired serial [{$serial->serial_no}] (Product ID: {$serial->product_id}) -> Restored to available in warehouse #{$serial->current_location_id}");
            }
        }

        $this->info("Completed. Repaired {$repairedCount} of {$soldSerials->count()} scanned serials.");

        return 0;
    }
}

