<?php

namespace App\Http\Controllers;

use App\Models\BulkEditTask;
use App\Jobs\ProcessPriceJob;
use App\Jobs\ProcessInventoryJob;
use App\Jobs\ProcessTagsJob;
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
        $productTitles = [];
        if ($validated['selection_mode'] === 'manual') {
            if (empty($validated['product_ids'])) {
                return back()->withErrors(['product_ids' => 'Please select at least one product.']);
            }
            $productIds = json_decode($validated['product_ids'], true);
            if (empty($productIds)) {
                return back()->withErrors(['product_ids' => 'Please select at least one product.']);
            }
            if (!empty($request->input('product_titles'))) {
                $productTitles = json_decode($request->input('product_titles'), true) ?? [];
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
                'product_titles' => $productTitles,
            ],
            'product_ids' => $productIds,
            'scheduled_at' => $request->boolean('is_scheduled') && $request->input('schedule_at')
                ? $request->input('schedule_at')
                : null,
        ]);

        if (!$task->scheduled_at) {
            ProcessPriceJob::dispatch($task->id);
        }

        $msg = $task->scheduled_at
            ? 'Price update scheduled for ' . $task->scheduled_at->format('M d, Y h:i A') . '!'
            : 'Price update task created!';

        return \Redirect::to(\URL::tokenRoute('tasks.index', ['host' => $request->get('host')]))
            ->with('success', $msg);
    }

    public function inventory()
    {
        $locations = [];

        try {
            $locations = \App\Services\ShopifyGraphQL::fetchLocations(Auth::user());
        } catch (\Exception $e) {
            $locations = [];
        }

        return view('editor.inventory', compact('locations'));
    }

    public function submitInventory(Request $request)
    {
        $validated = $request->validate([
            'product_ids' => 'nullable|string',
            'selection_mode' => 'required|in:all,manual',
            'location_id' => 'nullable|string',
            'action' => 'required|in:set_quantity,add_quantity,remove_quantity',
            'quantity' => 'required|integer|min:0|max:999999',
            'track_inventory' => 'nullable|boolean',
            'continue_selling' => 'nullable|boolean',
            'apply_variants' => 'nullable|boolean',
        ]);

        $productIds = null;
        $productTitles = [];
        if ($validated['selection_mode'] === 'manual') {
            if (empty($validated['product_ids'])) {
                return back()->withErrors(['product_ids' => 'Please select at least one product.']);
            }
            $productIds = json_decode($validated['product_ids'], true);
            if (empty($productIds)) {
                return back()->withErrors(['product_ids' => 'Please select at least one product.']);
            }
            if (!empty($request->input('product_titles'))) {
                $productTitles = json_decode($request->input('product_titles'), true) ?? [];
            }
        }

        $task = BulkEditTask::create([
            'user_id' => Auth::id(),
            'task_type' => BulkEditTask::TYPE_INVENTORY,
            'status' => BulkEditTask::STATUS_PENDING,
            'parameters' => [
                'action' => $validated['action'],
                'quantity' => (int) $validated['quantity'],
                'location_id' => $validated['location_id'] ?? 'all',
                'track_inventory' => (bool) ($validated['track_inventory'] ?? false),
                'continue_selling' => (bool) ($validated['continue_selling'] ?? false),
                'apply_variants' => (bool) ($validated['apply_variants'] ?? true),
                'product_titles' => $productTitles,
            ],
            'product_ids' => $productIds,
            'scheduled_at' => $request->boolean('is_scheduled') && $request->input('schedule_at')
                ? $request->input('schedule_at')
                : null,
        ]);

        if (!$task->scheduled_at) {
            ProcessInventoryJob::dispatch($task->id);
        }

        $msg = $task->scheduled_at
            ? 'Inventory update scheduled for ' . $task->scheduled_at->format('M d, Y h:i A') . '!'
            : 'Inventory task created!';

        return \Redirect::to(\URL::tokenRoute('tasks.index', ['host' => $request->get('host')]))
            ->with('success', $msg);
    }

    public function tags()
    {
        return view('editor.tags');
    }

    public function submitTags(Request $request)
    {
        $validated = $request->validate([
            'product_ids' => 'nullable|string',
            'selection_mode' => 'required|in:all,manual',
            'action' => 'required|in:add,remove,replace,clear',
            'tags' => 'nullable|array',
        ]);

        $tags = $validated['tags'] ?? [];

        $productIds = null;
        $productTitles = [];
        if ($validated['selection_mode'] === 'manual') {
            if (empty($validated['product_ids'])) {
                return back()->withErrors(['product_ids' => 'Please select at least one product.']);
            }
            $productIds = json_decode($validated['product_ids'], true);
            if (empty($productIds)) {
                return back()->withErrors(['product_ids' => 'Please select at least one product.']);
            }
            if (!empty($request->input('product_titles'))) {
                $productTitles = json_decode($request->input('product_titles'), true) ?? [];
            }
        }

        $task = BulkEditTask::create([
            'user_id' => Auth::id(),
            'task_type' => BulkEditTask::TYPE_TAGS,
            'status' => BulkEditTask::STATUS_PENDING,
            'parameters' => [
                'action' => $validated['action'],
                'tags' => $tags,
                'product_titles' => $productTitles,
            ],
            'product_ids' => $productIds,
            'scheduled_at' => $request->boolean('is_scheduled') && $request->input('schedule_at')
                ? $request->input('schedule_at')
                : null,
        ]);

        if (!$task->scheduled_at) {
            ProcessTagsJob::dispatch($task->id);
        }

        $msg = $task->scheduled_at
            ? 'Tags update scheduled for ' . $task->scheduled_at->format('M d, Y h:i A') . '!'
            : 'Tags task created!';

        return \Redirect::to(\URL::tokenRoute('tasks.index', ['host' => $request->get('host')]))
            ->with('success', $msg);
    }

    /**
     * AJAX preview for price editor — returns before/after for up to 3 products, grouped.
     */
    public function previewPrice(Request $request)
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

        $productIds = null;
        if ($validated['selection_mode'] === 'manual' && !empty($validated['product_ids'])) {
            $productIds = json_decode($validated['product_ids'], true);
        }

        $shop = Auth::user();
        $allEdges = \App\Services\ShopifyGraphQL::fetchProducts($shop, $productIds);

        $action = $validated['action'];
        $value = (float) $validated['value'];
        $rounding = $validated['rounding'] ?? 'none';
        $roundingValue = $validated['rounding_value'] ?? null;
        $applyVariants = (bool) ($validated['apply_variants'] ?? false);

        $MAX_PRODUCTS = 3;
        $MAX_VARIANTS_PER_PRODUCT = 3;

        $previewProducts = [];
        $totalProducts = 0;
        $hasChanges = false;

        foreach ($allEdges as $edge) {
            $productTitle = $edge['node']['title'] ?? 'Untitled';
            $variantEdges = $edge['node']['variants']['edges'] ?? [];

            if (!$applyVariants) {
                $variantEdges = array_slice($variantEdges, 0, 1);
            }

            $productVariants = [];
            $productTotalVariants = 0;

            foreach ($variantEdges as $ve) {
                $productTotalVariants++;
                $variantTitle = $ve['node']['title'] ?? 'Default';
                $currentPrice = (float) ($ve['node']['price'] ?? 0);

                $newPrice = \App\Services\PriceCalculator::calculate($currentPrice, $action, $value);
                $newPrice = \App\Services\PriceCalculator::round($newPrice, $rounding, $roundingValue);

                if ($newPrice == $currentPrice) {
                    continue;
                }
                $hasChanges = true;

                if (count($productVariants) < $MAX_VARIANTS_PER_PRODUCT) {
                    $productVariants[] = [
                        'variant_title' => $variantTitle,
                        'old_value' => number_format($currentPrice, 2, '.', ''),
                        'new_value' => number_format($newPrice, 2, '.', ''),
                    ];
                }
            }

            if (empty($productVariants)) {
                continue;
            }

            $totalProducts++;

            if (count($previewProducts) < $MAX_PRODUCTS) {
                $shownV = count($productVariants);
                $previewProducts[] = [
                    'product_title' => $productTitle,
                    'total_variants' => $productTotalVariants,
                    'is_expandable' => $applyVariants && $productTotalVariants > 1,
                    'variants' => $productVariants,
                    'variant_shown' => min($shownV, $productTotalVariants),
                    'variant_more' => max(0, $productTotalVariants - $shownV),
                ];
            }
        }

        $actionLabels = [
            'set_specific' => 'Set to',
            'increase_amount' => '+',
            'decrease_amount' => '−',
            'increase_percent' => '+',
            'decrease_percent' => '−',
        ];
        $actionLabel = $actionLabels[$action] ?? $action;
        $suffix = in_array($action, ['increase_percent', 'decrease_percent']) ? '%' : '';
        $summary = "{$actionLabel}{$value}{$suffix}";

        $roundLabels = [
            'none' => '',
            'nearest_01' => ' · nearest cent',
            'nearest_whole' => ' · whole number',
            'end_99' => ' · ends in .99',
            'end_custom' => ' · custom ending',
        ];
        $summary .= $roundLabels[$rounding] ?? '';

        return response()->json([
            'preview_products' => $previewProducts,
            'summary' => $summary,
            'has_changes' => $hasChanges,
            'shown_products' => count($previewProducts),
            'more_products' => max(0, $totalProducts - count($previewProducts)),
        ]);
    }

    /**
     * AJAX preview for inventory editor — returns before/after for up to 3 products, grouped.
     */
    public function previewInventory(Request $request)
    {
        $validated = $request->validate([
            'product_ids' => 'nullable|string',
            'selection_mode' => 'required|in:all,manual',
            'location_id' => 'nullable|string',
            'action' => 'required|in:set_quantity,add_quantity,remove_quantity',
            'quantity' => 'required|integer|min:0|max:999999',
            'track_inventory' => 'nullable|boolean',
            'continue_selling' => 'nullable|boolean',
            'apply_variants' => 'nullable|boolean',
        ]);

        $productIds = null;
        if ($validated['selection_mode'] === 'manual' && !empty($validated['product_ids'])) {
            $productIds = json_decode($validated['product_ids'], true);
        }

        $shop = Auth::user();
        $allEdges = \App\Services\ShopifyGraphQL::fetchProductsWithInventory($shop, $productIds);

        $action = $validated['action'];
        $quantity = (int) $validated['quantity'];
        $locationId = $validated['location_id'] ?? 'all';
        $applyVariants = (bool) ($validated['apply_variants'] ?? true);
        $trackInventory = (bool) ($validated['track_inventory'] ?? false);
        $continueSelling = (bool) ($validated['continue_selling'] ?? false);

        // Resolve location name
        $locationName = 'All Locations';
        if ($locationId !== 'all') {
            $locations = \App\Services\ShopifyGraphQL::fetchLocations($shop);
            foreach ($locations as $loc) {
                if ($loc['id'] === $locationId) {
                    $locationName = $loc['name'];
                    break;
                }
            }
        }

        $MAX_PRODUCTS = 3;
        $MAX_VARIANTS_PER_PRODUCT = 3;

        $previewProducts = [];
        $totalProducts = 0;
        $hasChanges = false;

        foreach ($allEdges as $edge) {
            $productTitle = $edge['node']['title'] ?? 'Untitled';
            $variantEdges = $edge['node']['variants']['edges'] ?? [];

            if (!$applyVariants) {
                $variantEdges = array_slice($variantEdges, 0, 1);
            }

            $productVariants = [];
            $productTotalVariants = 0;

            foreach ($variantEdges as $ve) {
                $productTotalVariants++;
                $variantTitle = $ve['node']['title'] ?? 'Default';
                $invItem = $ve['node']['inventoryItem'] ?? null;
                if (!$invItem) continue;

                $levels = $invItem['inventoryLevels']['edges'] ?? [];

                foreach ($levels as $levelEdge) {
                    $level = $levelEdge['node'];
                    $locGid = $level['location']['id'];

                    if ($locationId !== 'all' && $locGid !== $locationId) continue;

                    $currentQty = 0;
                    foreach ($level['quantities'] as $q) {
                        if ($q['name'] === 'available') {
                            $currentQty = (int) $q['quantity'];
                            break;
                        }
                    }

                    $newQty = match ($action) {
                        'set_quantity' => $quantity,
                        'add_quantity' => $currentQty + $quantity,
                        'remove_quantity' => max(0, $currentQty - $quantity),
                        default => $currentQty,
                    };

                    if ($newQty === $currentQty) continue;
                    $hasChanges = true;

                    if (count($productVariants) < $MAX_VARIANTS_PER_PRODUCT) {
                        $productVariants[] = [
                            'variant_title' => $variantTitle,
                            'location' => $locationId === 'all' ? 'All Locations' : $locationName,
                            'old_value' => (string) $currentQty,
                            'new_value' => (string) $newQty,
                        ];
                    }
                }
            }

            if (empty($productVariants)) {
                continue;
            }

            $totalProducts++;

            if (count($previewProducts) < $MAX_PRODUCTS) {
                $shownV = count($productVariants);
                $previewProducts[] = [
                    'product_title' => $productTitle,
                    'total_variants' => $productTotalVariants,
                    'is_expandable' => $applyVariants && $productTotalVariants > 1,
                    'variants' => $productVariants,
                    'variant_shown' => min($shownV, $productTotalVariants),
                    'variant_more' => max(0, $productTotalVariants - $shownV),
                ];
            }
        }

        $actionLabels = [
            'set_quantity' => "Set to {$quantity}",
            'add_quantity' => "Add {$quantity}",
            'remove_quantity' => "Remove {$quantity}",
        ];
        $summary = $actionLabels[$action] ?? ucfirst(str_replace('_', ' ', $action));
        $summary .= " ({$locationName})";
        if ($trackInventory) $summary .= ' · track on';
        if ($continueSelling) $summary .= ' · continue selling';

        return response()->json([
            'preview_products' => $previewProducts,
            'summary' => $summary,
            'has_changes' => $hasChanges,
            'shown_products' => count($previewProducts),
            'more_products' => max(0, $totalProducts - count($previewProducts)),
        ]);
    }

    /**
     * AJAX preview for tags editor — returns before/after for up to 3 products.
     */
    public function previewTags(Request $request)
    {
        $validated = $request->validate([
            'product_ids' => 'nullable|string',
            'selection_mode' => 'required|in:all,manual',
            'action' => 'required|in:add,remove,replace,clear',
            'tags' => 'nullable|array',
        ]);

        $tags = $validated['tags'] ?? [];

        $productIds = null;
        if ($validated['selection_mode'] === 'manual' && !empty($validated['product_ids'])) {
            $productIds = json_decode($validated['product_ids'], true);
        }

        $shop = Auth::user();
        $allEdges = \App\Services\ShopifyGraphQL::fetchProductsWithTags($shop, $productIds);

        $action = $validated['action'];

        $MAX_PRODUCTS = 3;

        $previewProducts = [];
        $totalProducts = 0;
        $hasChanges = false;

        foreach ($allEdges as $edge) {
            $totalProducts++;
            $productTitle = $edge['node']['title'] ?? 'Untitled';
            $currentTags = $edge['node']['tags'] ?? [];

            // Compute new tags
            $newTags = match ($action) {
                'add' => array_values(array_unique(array_merge($currentTags, $tags))),
                'remove' => array_values(array_diff($currentTags, $tags)),
                'replace' => $tags,
                'clear' => [],
                default => $currentTags,
            };

            // Determine changed tags
            $added = array_values(array_diff($newTags, $currentTags));
            $removed = array_values(array_diff($currentTags, $newTags));

            if (empty($added) && empty($removed) && $action !== 'clear') continue;
            if ($action === 'clear' && empty($currentTags)) continue;
            $hasChanges = true;

            if (count($previewProducts) < $MAX_PRODUCTS) {
                $previewProducts[] = [
                    'product_title' => $productTitle,
                    'old_tags' => $currentTags,
                    'new_tags' => $newTags,
                    'added' => $added,
                    'removed' => $removed,
                ];
            }
        }

        $actionLabels = [
            'add' => 'Add tags: ' . implode(', ', $tags),
            'remove' => 'Remove tags: ' . implode(', ', $tags),
            'replace' => 'Replace with: ' . implode(', ', $tags),
            'clear' => 'Clear all tags',
        ];
        $summary = $actionLabels[$action] ?? ucfirst(str_replace('_', ' ', $action));

        return response()->json([
            'preview_products' => $previewProducts,
            'summary' => $summary,
            'has_changes' => $hasChanges,
            'shown_products' => count($previewProducts),
            'more_products' => max(0, $totalProducts - count($previewProducts)),
        ]);
    }
}
