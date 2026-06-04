<?php

namespace App\Http\Controllers;

use App\Models\BulkEditTask;
use App\Jobs\ProcessRevertJob;
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
        ProcessRevertJob::dispatch($task->id);

        return back()->with('success', 'Revert started! Prices are being restored.');
    }
}
