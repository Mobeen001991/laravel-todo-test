<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $total
 * @property-read int $open
 * @property-read int $completed
 * @property-read int $overdue
 * @property-read int $due_within_week
 */
class TodoStatsResource extends JsonResource
{
    /**
     * @return array<string, int>
     */
    public function toArray(Request $request): array
    {
        return [
            'total' => (int) $this->resource['total'],
            'open' => (int) $this->resource['open'],
            'completed' => (int) $this->resource['completed'],
            'overdue' => (int) $this->resource['overdue'],
            'due_within_week' => (int) $this->resource['due_within_week'],
        ];
    }
}
