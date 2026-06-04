<?php

namespace App\Jobs;

use App\Models\BulkEditTask;
use App\Models\TaskRevertLog;
use App\Services\PriceCalculator;
use App\Services\ShopifyGraphQL;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

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
        $applyVariants = $params['apply_variants'] ?? true;
        $productIds = $task->product_ids;

        try {
            $allEdges = ShopifyGraphQL::fetchProducts($shop, $productIds);

            $processed = 0;
            $skipped = 0;
            $errors = [];
            $apiCalls = 0;

            foreach ($allEdges as $index => $edge) {
                $productGid = $edge['node']['id'];
                $gid = ShopifyGraphQL::extractId($productGid);
                $variantEdges = $edge['node']['variants']['edges'] ?? [];

                if (!$applyVariants) {
                    $variantEdges = array_slice($variantEdges, 0, 1);
                }

                $variantUpdates = [];
                $revertLogs = [];

                foreach ($variantEdges as $ve) {
                    $variantId = $ve['node']['id'];
                    $currentPrice = (float) $ve['node']['price'];

                    $newPrice = PriceCalculator::calculate($currentPrice, $action, $value);
                    $newPrice = PriceCalculator::round($newPrice, $rounding, $roundingValue);

                    if ($newPrice == $currentPrice) {
                        $skipped++;
                        continue;
                    }

                    $variantUpdates[] = [
                        'id' => $variantId,
                        'price' => number_format($newPrice, 2, '.', ''),
                    ];

                    $revertLogs[] = [
                        'bulk_edit_task_id' => $task->id,
                        'shopify_product_id' => $gid,
                        'shopify_variant_id' => $variantId,
                        'original_data' => json_encode(['price' => (string) $currentPrice]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (empty($variantUpdates)) {
                    continue;
                }

                $result = ShopifyGraphQL::updateVariantPrices($shop, $productGid, $variantUpdates);
                $apiCalls++;

                usleep(250000);

                $userErrors = $result['userErrors'] ?? [];

                if (!empty($userErrors)) {
                    foreach ($userErrors as $err) {
                        $errors[] = ($err['field'] ?? '?') . ': ' . ($err['message'] ?? '?');
                    }
                } else {
                    TaskRevertLog::insert($revertLogs);
                    $processed += count($variantUpdates);
                }
            }

            $task->update([
                'status' => empty($errors) ? BulkEditTask::STATUS_COMPLETED : BulkEditTask::STATUS_FAILED,
                'failure_reason' => empty($errors) ? null : json_encode(array_slice($errors, 0, 50)),
            ]);

        } catch (\Exception $e) {
            $task->update([
                'status' => BulkEditTask::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
            ]);
        }
    }
}
