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
 * Handles GDPR customers/redact webhook.
 * A store owner requests deletion of a customer's personal data.
 *
 * ApexBulk does NOT store any customer personal data (no orders, no customers).
 * We simply log and acknowledge the request.
 */
class GdprCustomerRedactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $domain,
        protected stdClass $data,
    ) {}

    public function handle(): void
    {
        $customerId = $this->data->customer->id ?? 'unknown';

        Log::info('GDPR customers/redact received', [
            'domain' => $this->domain,
            'customer_id' => $customerId,
        ]);

        // ApexBulk does not store customer data — nothing to delete.
        // Shopify requires a 200 response, which the WebhookController handles.
    }
}
