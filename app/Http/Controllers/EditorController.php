<?php

namespace App\Http\Controllers;

use App\Models\BulkEditTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EditorController extends Controller
{
    /**
     * Show the price editor form.
     */
    public function price()
    {
        return view('editor.price');
    }

    /**
     * Submit a bulk price edit task.
     */
    public function submitPrice(Request $request)
    {
        $validated = $request->validate([
            'product_ids' => 'nullable|array',
            'action' => 'required|in:set_specific,increase_amount,decrease_amount,increase_percent,decrease_percent',
            'value' => 'required|numeric',
            'rounding' => 'nullable|in:none,nearest_01,nearest_whole,end_99,end_custom',
            'rounding_value' => 'nullable|numeric',
        ]);

        $task = BulkEditTask::create([
            'user_id' => Auth::id(),
            'task_type' => BulkEditTask::TYPE_PRICE,
            'status' => BulkEditTask::STATUS_PENDING,
            'parameters' => [
                'action' => $validated['action'],
                'value' => $validated['value'],
                'rounding' => $validated['rounding'] ?? 'none',
                'rounding_value' => $validated['rounding_value'] ?? null,
            ],
            'product_ids' => $validated['product_ids'] ?? null,
        ]);

        // Will dispatch job in Phase 2
        // ProcessPriceJob::dispatch($task);

        return redirect('/tasks')->with('success', 'Price update task created!');
    }

    /**
     * Show the inventory editor form.
     */
    public function inventory()
    {
        return view('editor.inventory');
    }

    /**
     * Submit a bulk inventory edit task.
     */
    public function submitInventory(Request $request)
    {
        $validated = $request->validate([
            'product_ids' => 'nullable|array',
            'action' => 'required|in:set_quantity,add_quantity,remove_quantity',
            'quantity' => 'required|integer|min:0',
            'track_inventory' => 'nullable|boolean',
            'continue_selling' => 'nullable|boolean',
        ]);

        $task = BulkEditTask::create([
            'user_id' => Auth::id(),
            'task_type' => BulkEditTask::TYPE_INVENTORY,
            'status' => BulkEditTask::STATUS_PENDING,
            'parameters' => [
                'action' => $validated['action'],
                'quantity' => $validated['quantity'],
                'track_inventory' => $validated['track_inventory'] ?? null,
                'continue_selling' => $validated['continue_selling'] ?? null,
            ],
            'product_ids' => $validated['product_ids'] ?? null,
        ]);

        return redirect('/tasks')->with('success', 'Inventory update task created!');
    }

    /**
     * Show the tags editor form.
     */
    public function tags()
    {
        return view('editor.tags');
    }

    /**
     * Submit a bulk tags edit task.
     */
    public function submitTags(Request $request)
    {
        $validated = $request->validate([
            'product_ids' => 'nullable|array',
            'action' => 'required|in:add,remove,replace,clear',
            'tags' => 'nullable|array',
        ]);

        $task = BulkEditTask::create([
            'user_id' => Auth::id(),
            'task_type' => BulkEditTask::TYPE_TAGS,
            'status' => BulkEditTask::STATUS_PENDING,
            'parameters' => [
                'action' => $validated['action'],
                'tags' => $validated['tags'] ?? [],
            ],
            'product_ids' => $validated['product_ids'] ?? null,
        ]);

        return redirect('/tasks')->with('success', 'Tags update task created!');
    }
}
