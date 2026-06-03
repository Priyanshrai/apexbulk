<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskRevertLog extends Model
{
    protected $fillable = [
        'bulk_edit_task_id',
        'shopify_product_id',
        'shopify_variant_id',
        'original_data',
    ];

    protected function casts(): array
    {
        return [
            'original_data' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(BulkEditTask::class, 'bulk_edit_task_id');
    }
}
