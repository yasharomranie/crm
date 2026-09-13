<?php

namespace Tests\Feature;

use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_and_ancestor_ids_returns_just_itself_for_a_top_level_department(): void
    {
        $holding = Department::factory()->create();

        $this->assertSame([$holding->id], $holding->selfAndAncestorIds());
    }

    public function test_self_and_ancestor_ids_walks_up_the_full_parent_chain(): void
    {
        $holding = Department::factory()->create();
        $division = Department::factory()->create(['parent_id' => $holding->id]);
        $team = Department::factory()->create(['parent_id' => $division->id]);

        $this->assertSame(
            [$team->id, $division->id, $holding->id],
            $team->fresh()->selfAndAncestorIds(),
        );
    }
}
