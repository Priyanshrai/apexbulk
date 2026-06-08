<?php

namespace App\Services;

use App\Models\BulkEditTask;
use Illuminate\Support\Facades\Cache;

class UsageTracker
{
    const FREE_LIMIT = 100;

    /**
     * Count unique products edited this month (completed tasks only).
     */
    public function countThisMonth(int $userId): int
    {
        return Cache::remember("usage:{$userId}", 300, function () use ($userId) {
            $tasks = BulkEditTask::where('user_id', $userId)
                ->where('status', BulkEditTask::STATUS_COMPLETED)
                ->where('created_at', '>=', now()->startOfMonth())
                ->get();

            $allIds = [];
            foreach ($tasks as $task) {
                if (!empty($task->product_ids)) {
                    $allIds = array_merge($allIds, $task->product_ids);
                }
            }

            return count(array_unique($allIds));
        });
    }

    /**
     * Check if shop has exceeded the free limit.
     */
    public function isOverLimit(int $userId): bool
    {
        return $this->countThisMonth($userId) >= self::FREE_LIMIT;
    }

    /**
     * Get remaining free edits.
     */
    public function remaining(int $userId): int
    {
        return max(0, self::FREE_LIMIT - $this->countThisMonth($userId));
    }

    /**
     * Clear cache for a shop after a new task is created.
     */
    public function clearCache(int $userId): void
    {
        Cache::forget("usage:{$userId}");
    }
}
