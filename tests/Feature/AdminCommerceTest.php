<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_manager_can_open_commerce_workspace(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'admin_role' => 'finance_manager']);

        $this->actingAs($user)->get('/admin/commerce')
            ->assertOk()
            ->assertSee('ملخص التجارة')
            ->assertSee('خطط الاشتراك');
    }

    public function test_non_finance_staff_cannot_open_commerce_workspace(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'admin_role' => 'content_editor']);

        $this->actingAs($user)->get('/admin/commerce')->assertForbidden();
    }
}
