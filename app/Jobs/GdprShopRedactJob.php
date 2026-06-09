<?php

namespace App\Jobs;

use App\Models\BulkEditTask;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * Handles GDPR shop/redact webhook.
 * 48 hours after a store uninstalls, Shopify sends this webhook.
 * We MUST permanently delete ALL data associated with this shop.
 */
class GdprShopRedactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $domain,
        protected stdClass $data,
    ) {}

    public function handle(): void
    {
        Log::info('GDPR shop/redact received', ['domain' => $this->domain]);

        // Find the shop by domain (including soft-deleted)
        $shop = User::withTrashed()->where('name', $this->domain)->first();

        if (!$shop) {
            Log::info('GDPR shop/redact: shop not found, nothing to delete', ['domain' => $this->domain]);
            return;
        }

        // Permanently delete all related data (cascade delete handles revert logs)
        BulkEditTask::where('user_id', $shop->id)->forceDelete();

        // Permanently delete the shop/user record
        $shop->forceDelete();

        Log::info('GDPR shop/redact: all data deleted', ['domain' => $this->domain, 'shop_id' => $this->data->shop_id]);
    }
}
