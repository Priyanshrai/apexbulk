<?php

namespace App\Http\Controllers;

use App\Models\BulkEditTask;
use App\Jobs\ProcessRevertJob;
use App\Jobs\ProcessInventoryRevertJob;
use App\Jobs\ProcessTagsRevertJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function index()
    {
        $tasks = BulkEditTask::where('user_id', Auth::id())
            ->latest()
            ->paginate(25);

        return view('tasks.index', compact('tasks'));
    }

    public function copy(BulkEditTask $task)
    {
        $newTask = $task->replicate();
        $newTask->status = BulkEditTask::STATUS_PENDING;
        $newTask->save();

        return redirect('/editor/' . $task->task_type)
            ->with('success', 'Task copied! Parameters pre-filled.');
    }

    public function revert(BulkEditTask $task)
    {
        if (!$task->canRevert()) {
            return back()->with('error', 'This task cannot be reverted.');
        }

        if ($task->revertLogs()->count() === 0) {
            return back()->with('error', 'No revert data found for this task.');
        }

        $task->update(['status' => BulkEditTask::STATUS_REVERTING]);

        if ($task->task_type === BulkEditTask::TYPE_PRICE) {
            ProcessRevertJob::dispatch($task->id);
        } elseif ($task->task_type === BulkEditTask::TYPE_INVENTORY) {
            ProcessInventoryRevertJob::dispatch($task->id);
        } else {
            ProcessTagsRevertJob::dispatch($task->id);
        }

        return back()->with('success', 'Revert started!');
    }
}
