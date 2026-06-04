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

        if ($task->task_type === BulkEditTask::TYPE_PRICE) {
            $this->revertPrice($task, $shop, $logs);
        } elseif ($task->task_type === BulkEditTask::TYPE_INVENTORY) {
            $this->revertInventory($task, $shop, $logs);
        } else {
            $task->update([
                'status' => BulkEditTask::STATUS_FAILED,
                'failure_reason' => 'Unsupported task type for revert: ' . $task->task_type,
            ]);
        }
    }

    private function revertPrice(BulkEditTask $task, $shop, $logs): void
    {
        $byProduct = [];
        foreach ($logs as $log) {
            $data = $log->original_data;
            if (is_string($data)) {
                $data = json_decode($data, true) ?? [];
            }
            $pid = $log->shopify_product_id;
            if (!isset($byProduct[$pid])) {
                $byProduct[$pid] = [];
            }
            $byProduct[$pid][] = [
                'id' => $log->shopify_variant_id,
                'price' => $data['price'] ?? '0.00',
            ];
        }

        $errors = [];

        foreach ($byProduct as $productId => $variants) {
            $productGid = "gid://shopify/Product/{$productId}";
            $result = ShopifyGraphQL::updateVariantPrices($shop, $productGid, $variants);

            $userErrors = $result['userErrors'] ?? [];
            if (!empty($userErrors)) {
                foreach ($userErrors as $err) {
                    $errors[] = ($err['field'] ?? '?') . ': ' . ($err['message'] ?? '?');
                }
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

    private function revertInventory(BulkEditTask $task, $shop, $logs): void
    {
        $quantities = [];
        foreach ($logs as $log) {
            $data = $log->original_data;
            if (is_string($data)) {
                $data = json_decode($data, true) ?? [];
            }
            \Log::info('RevertInv: data', ['data' => $data]);
            $quantities[] = [
                'inventoryItemId' => (string) ($data['inventoryItemId'] ?? ''),
                'locationId' => (string) ($data['locationId'] ?? ''),
                'quantity' => (int) ($data['quantity'] ?? 0),
                'changeFromQuantity' => 0,
            ];
        }

        $errors = [];
        $chunks = array_chunk($quantities, 50);

        foreach ($chunks as $chunk) {
            $result = ShopifyGraphQL::setInventoryQuantities($shop, $chunk);

            $userErrors = $result['userErrors'] ?? [];
            if (!empty($userErrors)) {
                foreach ($userErrors as $err) {
                    $errors[] = ($err['field'] ?? '?') . ': ' . ($err['message'] ?? '?');
                }
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
