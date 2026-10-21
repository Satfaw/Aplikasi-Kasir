<?php

namespace App\Console\Commands;

use App\Models\Purchase;
use Illuminate\Console\Command;

class SyncPurchaseStock extends Command
{
    protected $signature = 'purchase:sync-stock';

    protected $description = 'Sync product stock from all received purchases';

    public function handle(): void
    {
        $purchases = Purchase::where('status', 'received')
            ->with('purchaseDetails.product')
            ->get();

        $this->info("Found {$purchases->count()} received purchases.");

        foreach ($purchases as $purchase) {
            foreach ($purchase->purchaseDetails as $detail) {
                if ($detail->product) {
                    $detail->product->increment('stock', $detail->total_qty);
                    $this->line("Updated [{$detail->product->name}] stock += {$detail->total_qty}");
                }
            }
        }

        $this->info('Stock sync completed.');
    }
}
