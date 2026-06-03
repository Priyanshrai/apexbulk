<?php

namespace App\Http\Controllers;

use App\Models\BulkEditTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /**
     * List all tasks for the current shop.
     */
    public function index()
    {
        $tasks = BulkEditTask::where('user_id', Auth::id())
            ->latest()
            ->paginate(25);

        return view('tasks.index', compact('tasks'));
    }

    /**
     * Copy a task as a new pending task.
     */
    public function copy(BulkEditTask $task)
    {
        $newTask = $task->replicate();
        $newTask->status = BulkEditTask::STATUS_PENDING;
        $newTask->save();

        return redirect('/editor/' . $task->task_type)
            ->with('success', 'Task copied! Parameters pre-filled.');
    }

    /**
     * Revert a completed task.
     */
    public function revert(BulkEditTask $task)
    {
        if (!$task->canRevert()) {
            return back()->with('error', 'This task cannot be reverted.');
        }

        $task->update(['status' => BulkEditTask::STATUS_REVERTING]);

        // Will dispatch RevertTaskJob in Phase 5
        // RevertTaskJob::dispatch($task);

        return back()->with('success', 'Revert started!');
    }
}
