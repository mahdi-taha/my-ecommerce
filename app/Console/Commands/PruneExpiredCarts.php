<?php

namespace App\Console\Commands;

use App\Services\CartService;
use Illuminate\Console\Command;

class PruneExpiredCarts extends Command
{
    protected $signature = 'carts:prune {--limit=500}';

    protected $description = 'Delete expired storefront carts';

    public function handle(CartService $cartService): int
    {
        $total = 0;
        $limit = max(1, (int) $this->option('limit'));

        do {
            $deleted = $cartService->pruneExpired($limit);
            $total += $deleted;
        } while ($deleted === $limit);

        $this->info("Deleted {$total} expired cart(s).");

        return self::SUCCESS;
    }
}
