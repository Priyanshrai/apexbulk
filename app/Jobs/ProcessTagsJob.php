<?php

namespace App\Jobs;

use App\Models\BulkEditTask;
use App\Models\TaskRevertLog;
use App\Services\ShopifyGraphQL;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTagsJob implements ShouldQueue
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
        $tags = $params['tags'] ?? [];
        $productIds = $task->product_ids;

        try {
            $allEdges = ShopifyGraphQL::fetchProductsWithTags($shop, $productIds);

            $processed = 0;
            $errors = [];
            $processedProductIds = [];

            foreach ($allEdges as $edge) {
                $productGid = $edge['node']['id'];
                $gid = ShopifyGraphQL::extractId($productGid);
                $currentTags = $edge['node']['tags'] ?? [];

                // Store original tags for revert
                TaskRevertLog::create([
                    'bulk_edit_task_id' => $task->id,
                    'shopify_product_id' => $gid,
                    'shopify_variant_id' => '',
                    'original_data' => ['tags' => $currentTags],
                ]);

                // Skip if no change needed
                if ($action === 'add' && empty($tags)) continue;
                if ($action === 'remove' && empty($tags)) continue;
                if ($action === 'replace' && $currentTags === $tags) continue;
                if ($action === 'clear' && empty($currentTags)) continue;

                $result = ShopifyGraphQL::updateProductTags($shop, $productGid, $action, $tags);

                $userErrors = $result['userErrors'] ?? [];
                if (!empty($userErrors)) {
                    foreach ($userErrors as $err) {
                        $errors[] = ($err['field'] ?? '?') . ': ' . ($err['message'] ?? '?');
                    }
                } else {
                    $processed++;
                    $processedProductIds[] = $gid;
                }

                usleep(250000);
            }

            $updateData = [
                'status' => empty($errors) ? BulkEditTask::STATUS_COMPLETED : BulkEditTask::STATUS_FAILED,
                'failure_reason' => empty($errors) ? null : json_encode(array_slice($errors, 0, 50)),
            ];

            // If All Products mode and success, store actual processed product IDs
            if (empty($errors) && $task->product_ids === null) {
                $updateData['product_ids'] = array_values(array_unique($processedProductIds));
            }

            $task->update($updateData);

        } catch (\Exception $e) {
            $task->update([
                'status' => BulkEditTask::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
            ]);
        } finally {
            // Clear usage cache so count updates immediately on next page load
            app(\App\Services\UsageTracker::class)->clearCache($task->user_id);
        }
    }
}
