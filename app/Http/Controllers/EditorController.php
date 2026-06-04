<?php

namespace App\Http\Controllers;

use App\Models\BulkEditTask;
use App\Jobs\ProcessPriceJob;
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
            'product_ids' => 'nullable|string',
            'selection_mode' => 'required|in:all,manual',
            'action' => 'required|in:set_specific,increase_amount,decrease_amount,increase_percent,decrease_percent',
            'value' => 'required|numeric|min:0|max:99999999',
            'rounding' => 'nullable|in:none,nearest_01,nearest_whole,end_99,end_custom',
            'rounding_value' => 'nullable|numeric|min:0|max:0.99',
            'apply_variants' => 'nullable|boolean',
        ]);

        // Decode JSON product IDs, or null for "all products"
        $productIds = null;
        if ($validated['selection_mode'] === 'manual' && !empty($validated['product_ids'])) {
            $productIds = json_decode($validated['product_ids'], true);
            if (empty($productIds)) {
                return back()->withErrors(['product_ids' => 'Please select at least one product.']);
            }
        }

        $task = BulkEditTask::create([
            'user_id' => Auth::id(),
            'task_type' => BulkEditTask::TYPE_PRICE,
            'status' => BulkEditTask::STATUS_PENDING,
            'parameters' => [
                'action' => $validated['action'],
                'value' => $validated['value'],
                'rounding' => $validated['rounding'] ?? 'none',
                'rounding_value' => $validated['rounding_value'] ?? null,
                'selection_mode' => $validated['selection_mode'],
                'apply_variants' => (bool) ($validated['apply_variants'] ?? false),
            ],
            'product_ids' => $productIds,
        ]);

        ProcessPriceJob::dispatch($task->id);

        return redirect(url('/tasks') . '?' . http_build_query(request()->query()))
            ->with('success', 'Price update task created!');
    }

    public function inventory()
    {
        return view('editor.inventory');
    }

    public function submitInventory(Request $request)
    {
        $validated = $request->validate([
            'product_ids' => 'nullable|string',
            'action' => 'required|in:set_quantity,add_quantity,remove_quantity',
            'quantity' => 'required|integer|min:0',
            'track_inventory' => 'nullable|boolean',
            'continue_selling' => 'nullable|boolean',
        ]);

        return redirect(url('/tasks') . '?' . http_build_query(request()->query()))
            ->with('success', 'Inventory task created!');
    }

    public function tags()
    {
        return view('editor.tags');
    }

    public function submitTags(Request $request)
    {
        $validated = $request->validate([
            'product_ids' => 'nullable|string',
            'action' => 'required|in:add,remove,replace,clear',
            'tags' => 'nullable|array',
        ]);

        return redirect(url('/tasks') . '?' . http_build_query(request()->query()))
            ->with('success', 'Tags task created!');
    }
}
