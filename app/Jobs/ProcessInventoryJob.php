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

class ProcessInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public function __construct(
        protected int $taskId,
    ) {}

    public function handle(): void
    {
        $task = BulkEditTask::findOrFail($this->taskId);
        $task->update(['status' => BulkEditTask::STATUS_RUNNING]);

        $shop = $task->user;
        $params = $task->parameters;
        $action = $params['action'];
        $quantity = (int) $params['quantity'];
        $locationId = $params['location_id'] ?? 'all';
        $applyVariants = $params['apply_variants'] ?? true;
        $trackInventory = $params['track_inventory'] ?? false;
        $continueSelling = $params['continue_selling'] ?? false;
        $productIds = $task->product_ids;

        try {
            $allEdges = ShopifyGraphQL::fetchProductsWithInventory($shop, $productIds);

            $processed = 0;
            $skipped = 0;
            $errors = [];

            foreach ($allEdges as $index => $edge) {
                $productGid = $edge['node']['id'];
                $gid = ShopifyGraphQL::extractId($productGid);
                $variantEdges = $edge['node']['variants']['edges'] ?? [];

                if (!$applyVariants) {
                    $variantEdges = array_slice($variantEdges, 0, 1);
                }

                $revertLogs = [];
                $qtyUpdates = [];
                $deltaChanges = [];

                foreach ($variantEdges as $vi => $ve) {
                    $variantId = $ve['node']['id'];
                    $invItem = $ve['node']['inventoryItem'] ?? null;
                    if (!$invItem) continue;

                    $invItemId = $invItem['id'];
                    $levels = $invItem['inventoryLevels']['edges'] ?? [];

                    foreach ($levels as $li => $levelEdge) {
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

                        if ($newQty === $currentQty) {
                            $skipped++;
                            continue;
                        }

                        if ($action === 'set_quantity') {
                            $qtyUpdates[] = [
                                'inventoryItemId' => $invItemId,
                                'locationId' => $locGid,
                                'quantity' => $newQty,
                                'changeFromQuantity' => $currentQty,
                            ];
                        } else {
                            $delta = match ($action) {
                                'add_quantity' => $quantity,
                                'remove_quantity' => -min($quantity, $currentQty),
                                default => 0,
                            };
                            $deltaChanges[] = [
                                'inventoryItemId' => $invItemId,
                                'locationId' => $locGid,
                                'delta' => $delta,
                                'changeFromQuantity' => $currentQty,
                            ];
                        }

                        $revertLogs[] = [
                            'bulk_edit_task_id' => $task->id,
                            'shopify_product_id' => $gid,
                            'shopify_variant_id' => $variantId,
                            'original_data' => json_encode([
                                'quantity' => $currentQty,
                                'inventoryItemId' => $invItemId,
                                'locationId' => $locGid,
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $processed++;
                    }
                }

                if (!empty($qtyUpdates)) {
                    $result = ShopifyGraphQL::setInventoryQuantities($shop, $qtyUpdates);
                    $errs = $result['userErrors'] ?? [];
                    if (!empty($errs)) {
                        foreach ($errs as $e) $errors[] = ($e['field'] ?? '?') . ': ' . ($e['message'] ?? '?');
                    } else {
                        TaskRevertLog::insert($revertLogs);
                    }
                }

                if (!empty($deltaChanges)) {
                    $result = ShopifyGraphQL::adjustInventoryQuantities($shop, $deltaChanges);
                    $errs = $result['userErrors'] ?? [];
                    if (!empty($errs)) {
                        foreach ($errs as $e) $errors[] = ($e['field'] ?? '?') . ': ' . ($e['message'] ?? '?');
                    } else {
                        TaskRevertLog::insert($revertLogs);
                    }
                }

                usleep(250000);

                if (!empty($qtyUpdates) || !empty($deltaChanges)) {
                    $this->updateVariantSettings($shop, $productGid, $variantEdges, $trackInventory, $continueSelling);
                }
            }

            if ($processed === 0 && empty($errors)) {
                $errors[] = 'No inventory found at the selected location for these products. The products may not be stocked at this location.';
            }

            $task->update([
                'status' => empty($errors) ? BulkEditTask::STATUS_COMPLETED : BulkEditTask::STATUS_FAILED,
                'failure_reason' => empty($errors) ? null : json_encode(array_slice($errors, 0, 50)),
            ]);

        } catch (\Exception $e) {
            $task->update([
                'status' => BulkEditTask::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
            ]);
        }
    }

    private function updateVariantSettings($shop, string $productGid, array $variantEdges, bool $track, bool $continueSelling): void
    {
        $variants = [];
        foreach ($variantEdges as $ve) {
            $v = $ve['node'];
            $entry = ['id' => $v['id']];

            if ($track) {
                $entry['tracked'] = true;
            }

            $entry['inventoryPolicy'] = $continueSelling ? 'CONTINUE' : 'DENY';
            $variants[] = $entry;
        }

        if (empty($variants)) return;

        ShopifyGraphQL::updateVariantSettings($shop, $productGid, $variants);
    }
}
