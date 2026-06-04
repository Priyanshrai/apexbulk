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

class ProcessRevertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public function __construct(
        protected int $taskId,
    ) {}

    public function handle(): void
    {
        $task = BulkEditTask::findOrFail($this->taskId);
        $task->update(['status' => BulkEditTask::STATUS_REVERTING]);

        $shop = $task->user;
        $logs = TaskRevertLog::where('bulk_edit_task_id', $task->id)->get();

        if ($logs->isEmpty()) {
            $task->update(['status' => BulkEditTask::STATUS_FAILED, 'failure_reason' => 'No revert logs found']);
            return;
        }

        $byProduct = [];
        foreach ($logs as $log) {
            $pid = $log->shopify_product_id;
            if (!isset($byProduct[$pid])) {
                $byProduct[$pid] = [];
            }
            $byProduct[$pid][] = [
                'id' => $log->shopify_variant_id,
                'price' => $log->original_data['price'] ?? '0.00',
            ];
        }

        $processed = 0;
        $errors = [];

        foreach ($byProduct as $productId => $variants) {
            $productGid = "gid://shopify/Product/{$productId}";
            $result = ShopifyGraphQL::updateVariantPrices($shop, $productGid, $variants);

            $userErrors = $result['userErrors'] ?? [];
            if (!empty($userErrors)) {
                foreach ($userErrors as $err) {
                    $errors[] = ($err['field'] ?? '?') . ': ' . ($err['message'] ?? '?');
                }
            } else {
                $processed += count($variants);
            }

            usleep(250000);
        }

        if (!empty($errors)) {
            $task->update([
                'status' => BulkEditTask::STATUS_FAILED,
                'failure_reason' => json_encode(array_slice($errors, 0, 20)),
            ]);
            return;
        }

        $task->update(['status' => BulkEditTask::STATUS_REVERTED]);
    }
}
