<?php

namespace App\Console\Commands;

use App\Models\BulkEditTask;
use App\Jobs\ProcessPriceJob;
use App\Jobs\ProcessInventoryJob;
use App\Jobs\ProcessTagsJob;
use Illuminate\Console\Command;

class ProcessScheduledTasks extends Command
{
    protected $signature = 'apexbulk:process-scheduled';
    protected $description = 'Dispatch scheduled tasks that are due';

    public function handle(): int
    {
        $tasks = BulkEditTask::where('status', BulkEditTask::STATUS_PENDING)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No scheduled tasks due.');

            return self::SUCCESS;
        }

        foreach ($tasks as $task) {
            $this->info("Dispatching task #{$task->id} ({$task->task_type})");

            match ($task->task_type) {
                BulkEditTask::TYPE_PRICE => ProcessPriceJob::dispatch($task->id),
                BulkEditTask::TYPE_INVENTORY => ProcessInventoryJob::dispatch($task->id),
                BulkEditTask::TYPE_TAGS => ProcessTagsJob::dispatch($task->id),
                default => null,
            };
        }

        $this->info("Dispatched {$tasks->count()} scheduled task(s).");

        return self::SUCCESS;
    }
}
