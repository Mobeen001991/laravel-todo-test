<?php

namespace Tests\Feature;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TodoStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_endpoint_returns_aggregates_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Todo::query()->create([
            'user_id' => $user->id,
            'title' => 'Open one',
            'description' => null,
            'priority' => 'medium',
            'is_completed' => false,
            'completed_at' => null,
            'due_at' => now()->subDay(),
        ]);
        Todo::query()->create([
            'user_id' => $user->id,
            'title' => 'Open two',
            'description' => null,
            'priority' => 'low',
            'is_completed' => false,
            'completed_at' => null,
            'due_at' => now()->addDays(3),
        ]);
        Todo::query()->create([
            'user_id' => $user->id,
            'title' => 'Done',
            'description' => null,
            'priority' => 'high',
            'is_completed' => true,
            'completed_at' => now(),
            'due_at' => null,
        ]);

        $response = $this->getJson('/api/todos/stats');

        $response->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.open', 2)
            ->assertJsonPath('data.completed', 1)
            ->assertJsonPath('data.overdue', 1);
    }

    public function test_toggle_completion_flips_state(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $todo = Todo::query()->create([
            'user_id' => $user->id,
            'title' => 'Task',
            'description' => null,
            'priority' => 'medium',
            'is_completed' => false,
            'completed_at' => null,
            'due_at' => null,
        ]);

        $this->postJson("/api/todos/{$todo->id}/toggle-completion")
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);

        $this->assertTrue($todo->fresh()->is_completed);

        $this->postJson("/api/todos/{$todo->id}/toggle-completion")
            ->assertOk()
            ->assertJsonPath('data.is_completed', false);
    }
}
