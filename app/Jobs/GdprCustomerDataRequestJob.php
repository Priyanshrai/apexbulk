<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * Handles GDPR customers/data_request webhook.
 * A customer requests to view all personal data stored about them.
 *
 * ApexBulk does NOT store any customer personal data (no orders, no customers).
 * We simply log and acknowledge — Shopify requires the app to respond.
 */
class GdprCustomerDataRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $domain,
        protected stdClass $data,
    ) {}

    public function handle(): void
    {
        $customerId = $this->data->customer->id ?? 'unknown';

        Log::info('GDPR customers/data_request received', [
            'domain' => $this->domain,
            'customer_id' => $customerId,
            'data_request_id' => $this->data->data_request->id ?? null,
        ]);

        // ApexBulk does not store customer data — nothing to return.
        // Shopify requires a 200 response, which the WebhookController handles.
    }
}
