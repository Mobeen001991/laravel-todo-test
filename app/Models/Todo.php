<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Todo extends Model
{
    protected $fillable = [
        'title',
        'description',
        'file_path',
        'user_id',
        'priority',
        'is_completed',
        'completed_at',
        'due_at',
    ];

    protected $casts = [
        'priority' => 'string',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'due_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Todo>  $query
     * @return Builder<Todo>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_completed', false);
    }

    /**
     * @param  Builder<Todo>  $query
     * @return Builder<Todo>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('is_completed', true);
    }

    /**
     * @param  Builder<Todo>  $query
     * @return Builder<Todo>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('is_completed', false)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }
}
