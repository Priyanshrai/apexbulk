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

class ProcessTagsRevertJob implements ShouldQueue
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

        $processed = 0;
        $errors = [];

        foreach ($logs as $log) {
            $data = $log->original_data;
            $originalTags = $data['tags'] ?? [];
            $productGid = 'gid://shopify/Product/' . $log->shopify_product_id;

            $result = ShopifyGraphQL::updateProductTags($shop, $productGid, 'replace', $originalTags);

            $userErrors = $result['userErrors'] ?? [];
            if (!empty($userErrors)) {
                foreach ($userErrors as $err) {
                    $errors[] = ($err['field'] ?? '?') . ': ' . ($err['message'] ?? '?');
                }
            } else {
                $processed++;
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
