<?php

namespace App\Listeners;

use Osiset\ShopifyApp\Messaging\Events\AppInstalledEvent;

class FetchShopTimezone
{
    public function handle(AppInstalledEvent $event): void
    {
        try {
            $shop = $event->getShop();
            $response = $shop->api()->graph('{ shop { ianaTimezone } }');
            $timezone = $response['body']['data']['shop']['ianaTimezone'] ?? null;

            if ($timezone) {
                $shop->update(['timezone' => $timezone]);
            }
        } catch (\Exception $e) {
            report($e);
        }
    }
}
