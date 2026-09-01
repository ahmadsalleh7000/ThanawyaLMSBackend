<?php

namespace Tests\Feature;

use App\Models\ParentStudentLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_code_requires_explicit_student_approval_before_parent_access(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $parent = User::factory()->create(['role' => User::ROLE_PARENT]);
        $code = $this->actingAs($student, 'sanctum')->postJson('/api/student/link-code')->assertCreated()->json('data.code');

        $linkId = $this->actingAs($parent, 'sanctum')->postJson('/api/parent/link-code', ['code' => $code])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->getJson('/api/parent/students')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($student, 'sanctum')->getJson('/api/student/link-requests')
            ->assertOk()->assertJsonPath('data.0.parent.id', $parent->id);
        $this->postJson("/api/student/link-requests/{$linkId}/accept")
            ->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->actingAs($parent, 'sanctum')->getJson('/api/parent/students')
            ->assertOk()->assertJsonPath('data.0.id', (string) $student->id);
    }

    public function test_codes_are_one_time_and_pending_requests_can_be_rejected(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $parent = User::factory()->create(['role' => User::ROLE_PARENT]);
        $otherParent = User::factory()->create(['role' => User::ROLE_PARENT]);
        $code = $this->actingAs($student, 'sanctum')->postJson('/api/student/link-code')->json('data.code');
        $linkId = $this->actingAs($parent, 'sanctum')->postJson('/api/parent/link-code', ['code' => $code])->json('data.id');

        $this->actingAs($otherParent, 'sanctum')->postJson('/api/parent/link-code', ['code' => $code])->assertUnprocessable();
        $this->actingAs($student, 'sanctum')->postJson("/api/student/link-requests/{$linkId}/reject")
            ->assertOk()->assertJsonPath('data.status', 'rejected');
    }

    public function test_only_a_link_participant_can_unlink(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $parent = User::factory()->create(['role' => User::ROLE_PARENT]);
        $outsider = User::factory()->create(['role' => User::ROLE_PARENT]);
        $link = ParentStudentLink::create(['parent_id' => $parent->id, 'student_id' => $student->id, 'status' => 'accepted', 'linked_at' => now(), 'accepted_at' => now()]);

        $this->actingAs($outsider, 'sanctum')->deleteJson("/api/parent/links/{$link->id}")->assertNotFound();
        $this->actingAs($parent, 'sanctum')->deleteJson("/api/parent/links/{$link->id}")->assertOk();
        $this->assertDatabaseHas('parent_student_links', [
            'id' => $link->id,
            'status' => 'revoked',
        ]);
        $this->assertNotNull($link->fresh()->revoked_at);
    }
}
