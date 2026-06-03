<?php

namespace App\Jobs;

use App\Models\BulkEditTask;
use App\Models\TaskRevertLog;
use App\Services\PriceCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Osiset\ShopifyApp\Contracts\ShopModel;

class ProcessPriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;

    public function __construct(
        protected int $taskId,
    ) {}

    public function handle(): void
    {
        $task = BulkEditTask::findOrFail($this->taskId);
        $task->update(['status' => BulkEditTask::STATUS_RUNNING]);

        $shop = $task->user;
        $params = $task->parameters;
        $action = $params['action'];
        $value = (float) $params['value'];
        $rounding = $params['rounding'] ?? 'none';
        $roundingValue = $params['rounding_value'] ?? null;

        $productIds = $task->product_ids;

        \Log::info('PriceJob: Starting', [
            'task_id' => $task->id,
            'action' => $action,
            'value' => $value,
            'rounding' => $rounding,
            'product_ids_count' => $productIds ? count($productIds) : 'ALL',
        ]);

        try {
            $allProducts = $this->fetchProducts($shop, $productIds);

            \Log::info('PriceJob: Products fetched', [
                'count' => count($allProducts),
            ]);

            $processed = 0;
            $errors = [];

            foreach ($allProducts as $product) {
                $productId = $product['node']['id'];
                $gid = $this->extractId($productId);

                foreach ($product['node']['variants']['edges'] as $variantEdge) {
                    $variant = $variantEdge['node'];
                    $variantId = $variant['id'];
                    $currentPrice = (float) $variant['price'];

                    try {
                        $newPrice = PriceCalculator::calculate($currentPrice, $action, $value);
                        $newPrice = PriceCalculator::round($newPrice, $rounding, $roundingValue);

                        if ($newPrice == $currentPrice) continue;

                        TaskRevertLog::create([
                            'bulk_edit_task_id' => $task->id,
                            'shopify_product_id' => $gid,
                            'shopify_variant_id' => $variantId,
                            'original_data' => ['price' => (string) $currentPrice],
                        ]);

                        $this->updateVariantPrice($shop, $variantId, $newPrice);
                        $processed++;
                    } catch (\Exception $e) {
                        $errors[$variantId] = $e->getMessage();
                    }
                }
            }

            \Log::info('PriceJob: Complete', [
                'processed' => $processed,
                'errors' => count($errors),
            ]);

            $task->update([
                'status' => empty($errors) ? BulkEditTask::STATUS_COMPLETED : BulkEditTask::STATUS_FAILED,
                'failure_reason' => empty($errors) ? null : json_encode($errors),
            ]);

        } catch (\Exception $e) {
            $task->update([
                'status' => BulkEditTask::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch products with variants & prices from Shopify.
     */
    private function fetchProducts(ShopModel $shop, ?array $productIds): array
    {
        $allProducts = [];
        $cursor = null;

        do {
            $filter = '';
            if ($productIds && count($productIds) > 0) {
                $gids = array_map(fn($id) => '"gid://shopify/Product/' . $id . '"', $productIds);
                $filter = ', query: "id:(' . implode(' OR ', $gids) . ')"';
            }

            $query = sprintf('{
                products(first: 50, after: %s%s) {
                    edges {
                        node {
                            id
                            variants(first: 50) {
                                edges {
                                    node {
                                        id
                                        price
                                    }
                                }
                            }
                        }
                        cursor
                    }
                    pageInfo { hasNextPage }
                }
            }', $cursor ? '"' . $cursor . '"' : 'null', $filter);

            $response = $shop->api()->graph($query);
            $products = $response['body']['data']['products'] ?? [];
            $edges = $products['edges'] ?? [];
            $allProducts = array_merge($allProducts, $edges);

            $pageInfo = $products['pageInfo'] ?? [];
            if (!empty($pageInfo['hasNextPage']) && !empty($edges)) {
                $lastEdge = end($edges);
                $cursor = $lastEdge['cursor'] ?? null;
            } else {
                $cursor = null;
            }
        } while ($cursor);

        return $allProducts;
    }

    /**
     * Update a single variant price via GraphQL.
     */
    private function updateVariantPrice(ShopModel $shop, string $variantId, float $newPrice): void
    {
        $formattedPrice = number_format($newPrice, 2, '.', '');
        $query = sprintf('mutation { productVariantUpdate(input: { id: "%s", price: "%s" }) { productVariant { id price } userErrors { field message } } }', $variantId, $formattedPrice);

        $response = $shop->api()->graph($query);
        $userErrors = $response['body']['data']['productVariantUpdate']['userErrors'] ?? [];
        if (!empty($userErrors)) {
            \Log::warning('PriceJob: variant update error', [
                'variant_id' => $variantId,
                'new_price' => $formattedPrice,
                'errors' => $userErrors,
            ]);
        }
    }

    private function extractId(string $gid): string
    {
        return explode('/', $gid)[4] ?? $gid;
    }
}
