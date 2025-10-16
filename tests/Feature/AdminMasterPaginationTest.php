<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMasterPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function authenticateAdmin(): User
    {
        $admin = User::factory()->create([
            'level' => UserRole::ADMIN->value,
        ]);

        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_paginate_units(): void
    {
        $this->authenticateAdmin();

        Unit::factory()->count(25)->create();

        $response = $this->getJson('/api/units?per_page=10');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
                'last_page',
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertSame(25, $response->json('total'));
    }

    public function test_units_all_query_returns_all_records(): void
    {
        $this->authenticateAdmin();

        Unit::factory()->count(7)->create();

        $response = $this->getJson('/api/units?all=1');

        $response->assertOk();
        $this->assertCount(7, $response->json());
    }

    public function test_admin_can_paginate_users(): void
    {
        $this->authenticateAdmin();

        User::factory()->count(12)->create();

        $response = $this->getJson('/api/admin/users?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
                'last_page',
            ]);

        $this->assertCount(5, $response->json('data'));
        $this->assertSame(13, $response->json('total'));
    }
}
