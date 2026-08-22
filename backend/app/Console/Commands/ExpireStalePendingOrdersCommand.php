<?php

namespace App\Console\Commands;

use App\Application\Guardian\Purchase\GuardianPurchaseService;
use App\Models\Order;
use Illuminate\Console\Command;
use Throwable;

class ExpireStalePendingOrdersCommand extends Command
{
    protected $signature = 'orders:expire-stale-pending';

    protected $description = 'Stripe Webhookが未到達のまま放置されたpending注文をStripe側の状態と同期する';

    public function handle(GuardianPurchaseService $service): int
    {
        $ttlMinutes = (int) config('purchase.checkout_session_ttl_minutes');
        $staleBefore = now()->subMinutes($ttlMinutes)->subMinutes(5);

        $syncedCount = 0;

        Order::query()
            ->where('status', 'pending')
            ->whereNotNull('stripe_checkout_session_id')
            ->where('created_at', '<=', $staleBefore)
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($service, &$syncedCount): void {
                foreach ($orders as $order) {
                    try {
                        $service->syncPendingOrderWithStripe($order);
                        $syncedCount++;
                    } catch (Throwable $exception) {
                        $this->components->warn("order {$order->id} sync failed: {$exception->getMessage()}");
                    }
                }
            });

        $this->components->info("synced {$syncedCount} stale pending order(s)");

        return self::SUCCESS;
    }
}
