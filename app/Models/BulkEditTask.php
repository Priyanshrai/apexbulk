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
            return 0; // "all products" — count unknown until execution
        }
        return count($this->product_ids);
    }
}
