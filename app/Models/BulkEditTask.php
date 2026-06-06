<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkEditTask extends Model
{
    protected $fillable = [
        'user_id',
        'task_type',
        'status',
        'parameters',
        'product_ids',
        'scheduled_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'product_ids' => 'array',
            'scheduled_at' => 'datetime',
        ];
    }

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REVERTING = 'reverting';
    const STATUS_REVERTED = 'reverted';

    // Task types
    const TYPE_PRICE = 'price';
    const TYPE_INVENTORY = 'inventory';
    const TYPE_TAGS = 'tags';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revertLogs(): HasMany
    {
        return $this->hasMany(TaskRevertLog::class);
    }

    // Helpers
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canRevert(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function productCount(): int
    {
        if (empty($this->product_ids)) {
            return 0;
        }
        return count($this->product_ids);
    }

    public function isAllProducts(): bool
    {
        return empty($this->product_ids);
    }

    public function actionSummary(): string
    {
        $p = $this->parameters ?? [];
        $action = $p['action'] ?? '?';
        $value = $p['value'] ?? '';
        $rounding = $p['rounding'] ?? 'none';

        $labels = [
            'set_specific' => 'Set to',
            'increase_amount' => '+',
            'decrease_amount' => '−',
            'increase_percent' => '+',
            'decrease_percent' => '−',
        ];

        $label = $labels[$action] ?? $action;
        $suffix = in_array($action, ['increase_percent', 'decrease_percent']) ? '%' : '';

        $parts = ["{$label}{$value}{$suffix}"];

        if ($rounding !== 'none') {
            $roundLabels = [
                'nearest_01' => '· nearest cent',
                'nearest_whole' => '· whole number',
                'end_99' => '· ends in .99',
                'end_custom' => '· custom ending',
            ];
            $parts[] = $roundLabels[$rounding] ?? "· {$rounding}";
        }

        if ($this->task_type === self::TYPE_PRICE) {
            return implode(' ', $parts);
        }

        if ($this->task_type === self::TYPE_INVENTORY) {
            $qty = $p['quantity'] ?? 0;
            $locId = $p['location_id'] ?? 'all';
            $locName = $locId === 'all' ? 'all locations' : 'loc #' . last(explode('/', (string) $locId));
            $labels = [
                'set_quantity' => "Set to {$qty}",
                'add_quantity' => "Add {$qty}",
                'remove_quantity' => "Remove {$qty}",
            ];
            $label = $labels[$action] ?? ucfirst(str_replace('_', ' ', $action));
            $track = !empty($p['track_inventory']) ? ' · track on' : '';
            return "{$label} ({$locName}){$track}";
        }

        if ($this->task_type === self::TYPE_TAGS) {
            $tags = $p['tags'] ?? [];
            $tagStr = !empty($tags) ? implode(', ', $tags) : 'all tags';
            $labels = [
                'add' => "Add: {$tagStr}",
                'remove' => "Remove: {$tagStr}",
                'replace' => "Replace: {$tagStr}",
                'clear' => 'Clear all tags',
            ];
            return $labels[$action] ?? ucfirst(str_replace('_', ' ', $action));
        }

        return ucfirst(str_replace('_', ' ', $action));
    }

    public function productLinks(): array
    {
        if (empty($this->product_ids) || !$this->user) {
            return [];
        }

        $domain = str_replace('.myshopify.com', '', $this->user->name);
        $titles = $this->parameters['product_titles'] ?? [];
        $links = [];

        foreach (array_slice($this->product_ids, 0, 5) as $id) {
            $links[] = [
                'id' => $id,
                'title' => $titles[$id] ?? null,
                'url' => "https://admin.shopify.com/store/{$domain}/products/{$id}",
            ];
        }

        return $links;
    }

    public function productCountLabel(): string
    {
        if ($this->isAllProducts()) {
            return 'All products';
        }

        $count = $this->productCount();

        return $count . ' product' . ($count !== 1 ? 's' : '');
    }
}
