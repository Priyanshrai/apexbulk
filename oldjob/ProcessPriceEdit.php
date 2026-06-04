<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\BulkEditTask;

class ProcessPriceEdit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $products;
    protected $action;
    protected $value;
    protected $shop;
    protected $applyToVariants;
    protected $taskId;

    public function __construct(array $products, string $action, $value, $shop, bool $applyToVariants = false, $taskId = null)
    {
        $this->products = $products;
        $this->action = $action;
        $this->value = $value;
        $this->shop = $shop;
        $this->applyToVariants = $applyToVariants;
        $this->taskId = $taskId;
    }

    public function handle()
    {
        try {
            Log::info('Starting price edit job', [
                'action' => $this->action,
                'products' => count($this->products),
                'task_id' => $this->taskId,
                'value' => $this->value,
                'apply_to_variants' => $this->applyToVariants
            ]);

            $task = null;
            if ($this->taskId) {
                $task = BulkEditTask::find($this->taskId);
                if ($task) {
                    $task->update(['status' => BulkEditTask::STATUS_WORKING]);
                }
            }

            $errors = [];
            foreach ($this->products as $productId) {
                try {
                    $this->processProduct($productId);
                } catch (\Exception $e) {
                    $errors[$productId] = $e->getMessage();
                    Log::error("Error processing product {$productId}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'product_id' => $productId,
                        'action' => $this->action,
                        'value' => $this->value
                    ]);
                }
            }

            if ($task) {
                $task->update([
                    'status' => empty($errors) ? BulkEditTask::STATUS_COMPLETED : BulkEditTask::STATUS_FAILED,
                    'completed_at' => now(),
                    'error_log' => !empty($errors) ? json_encode($errors) : null
                ]);
            }

            Log::info('Price edit job completed', [
                'products_processed' => count($this->products),
                'success_count' => count($this->products) - count($errors),
                'error_count' => count($errors),
                'errors' => $errors
            ]);

            if (!empty($errors)) {
                throw new \Exception("Failed to process some products: " . json_encode($errors));
            }

        } catch (\Exception $e) {
            if ($task) {
                $task->update([
                    'status' => BulkEditTask::STATUS_FAILED,
                    'error_log' => $e->getMessage()
                ]);
            }
            Log::error('Price edit job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $this->taskId
            ]);
            throw $e;
        }
    }

    protected function processProduct($productId)
    {
        try {
            Log::info("Starting to process product", [
                'product_id' => $productId,
                'action' => $this->action,
                'value' => $this->value
            ]);

            // Build mutation based on edit type
            $mutation = $this->buildPriceMutation($productId);
            
            // Execute mutation
            $response = $this->shop->api()->graph($mutation);
            
            $this->handleResponse($response, $productId);

            Log::info("Successfully updated product prices", [
                'product_id' => $productId,
                'response' => $response['body']
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to process product", [
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Build price mutation
     */
    protected function buildPriceMutation($productId)
    {
        // First get the current price and variants
        $query = <<<QUERY
        {
            product(id: "$productId") {
                title
                variants(first: 250) {
                    edges {
                        node {
                            id
                            price
                            title
                        }
                    }
                }
            }
        }
        QUERY;

        $response = $this->shop->api()->graph($query);
        
        if (!isset($response['body']['data']['product']['variants']['edges'])) {
            throw new \Exception("Failed to fetch variants for product {$productId}");
        }

        $variants = $response['body']['data']['product']['variants']['edges'];
        $productTitle = $response['body']['data']['product']['title'];

        if (empty($variants)) {
            throw new \Exception("No variants found for product {$productId}");
        }

        Log::info("Processing price update for product", [
            'product_id' => $productId,
            'product_title' => $productTitle,
            'total_variants' => count($variants),
            'apply_to_all_variants' => $this->applyToVariants,
            'action' => $this->action,
            'value' => $this->value
        ]);

        // If not applying to all variants, only update the first one
        if (!$this->applyToVariants) {
            Log::info("Updating only first variant", [
                'product_id' => $productId,
                'variant_id' => $variants[0]['node']['id'],
                'variant_title' => $variants[0]['node']['title']
            ]);
            $variants = [$variants[0]];
        }

        // Build variant inputs for bulk update
        $variantInputs = [];
        foreach ($variants as $variant) {
            $variantNode = $variant['node'];
            $currentPrice = floatval($variantNode['price']);
            $variantId = $variantNode['id'];
            $newPrice = $this->calculateNewPrice($currentPrice);

            Log::info("Processing variant price update", [
                'variant_id' => $variantId,
                'variant_title' => $variantNode['title'],
                'old_price' => $currentPrice,
                'new_price' => $newPrice,
                'action' => $this->action
            ]);

            $variantInputs[] = "{ id: \"{$variantId}\", price: \"{$newPrice}\" }";
        }

        // Build the bulk update mutation
        $variantsList = implode(",\n                ", $variantInputs);
        return <<<MUTATION
        mutation {
            productVariantsBulkUpdate(
                productId: "$productId",
                variants: [
                    $variantsList
                ]
            ) {
                productVariants {
                    id
                    price
                }
                userErrors {
                    field
                    message
                }
            }
        }
        MUTATION;
    }

    protected function calculateNewPrice($currentPrice)
    {
        $value = floatval($this->value);
        
        switch ($this->action) {
            case 'increase_percent':
                return round($currentPrice * (1 + $value / 100), 2);
            case 'decrease_percent':
                return round($currentPrice * (1 - $value / 100), 2);
            case 'increase_amount':
                return round($currentPrice + $value, 2);
            case 'decrease_amount':
                return round($currentPrice - $value, 2);
            case 'set_amount':
                return round($value, 2);
            default:
                throw new \Exception("Invalid price action: {$this->action}");
        }
    }

    protected function handleResponse($response, $productId)
    {
        // Check for API errors first
        if (isset($response['errors']) && !empty($response['errors'])) {
            throw new \Exception("API Error for product {$productId}: " . json_encode($response['errors']));
        }

        // Validate response structure
        if (!isset($response['body']['data'])) {
            throw new \Exception("Invalid API response format for product {$productId}: " . json_encode($response));
        }

        $data = $response['body']['data'];
        
        // Check for productVariantsBulkUpdate data
        if (!isset($data['productVariantsBulkUpdate'])) {
            throw new \Exception("Missing productVariantsBulkUpdate in response for product {$productId}");
        }

        // Check for user errors - only throw if there are actual errors
        if (isset($data['productVariantsBulkUpdate']['userErrors']) && 
            is_array($data['productVariantsBulkUpdate']['userErrors']) && 
            !empty($data['productVariantsBulkUpdate']['userErrors'])) {
            throw new \Exception("User errors for product {$productId}: " . 
                json_encode($data['productVariantsBulkUpdate']['userErrors']));
        }

        // Validate that we have updated variants in the response
        if (!isset($data['productVariantsBulkUpdate']['productVariants']) || 
            empty($data['productVariantsBulkUpdate']['productVariants'])) {
            throw new \Exception("No variants were updated for product {$productId}");
        }

        // Log success with updated variant details
        Log::info("Successfully updated variants", [
            'product_id' => $productId,
            'updated_variants' => count($data['productVariantsBulkUpdate']['productVariants'])
        ]);
    }
} 