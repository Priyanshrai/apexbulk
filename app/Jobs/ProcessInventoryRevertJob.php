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

class ProcessInventoryRevertJob implements ShouldQueue
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

        // Fetch current quantities to use as changeFromQuantity
        $items = [];
        foreach ($logs as $log) {
            $items[] = [
                'inventoryItemId' => $log->original_data['inventoryItemId'] ?? '',
                'locationId' => $log->original_data['locationId'] ?? '',
            ];
        }
        $currentQtys = ShopifyGraphQL::fetchCurrentQuantities($shop, $items);

        $quantities = [];
        foreach ($logs as $log) {
            $invItemId = $log->original_data['inventoryItemId'] ?? '';
            $quantities[] = [
                'inventoryItemId' => $invItemId,
                'locationId' => $log->original_data['locationId'] ?? '',
                'quantity' => (int) ($log->original_data['quantity'] ?? 0),
                'changeFromQuantity' => $currentQtys[$invItemId] ?? 0,
            ];
        }

        $processed = 0;
        $errors = [];
        $chunks = array_chunk($quantities, 50);

        foreach ($chunks as $chunk) {
            $result = ShopifyGraphQL::setInventoryQuantities($shop, $chunk);

            $userErrors = $result['userErrors'] ?? [];
            if (!empty($userErrors)) {
                foreach ($userErrors as $err) {
                    $field = is_scalar($err['field'] ?? null) ? (string) $err['field'] : '?';
                    $msg = is_scalar($err['message'] ?? null) ? (string) $err['message'] : '?';
                    $errors[] = $field . ': ' . $msg;
                }
            } else {
                $processed += count($chunk);
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
