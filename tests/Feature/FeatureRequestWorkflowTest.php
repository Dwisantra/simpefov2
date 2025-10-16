<?php

namespace Tests\Feature;

use App\Enums\ManagerCategory;
use App\Enums\UserRole;
use App\Models\FeatureRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeatureRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function createUnitWithCategory(int $category, string $instansi = 'wiradadi'): Unit
    {
        return Unit::factory()->create([
            'manager_category_id' => $category,
            'instansi' => $instansi,
        ]);
    }

    protected function createUserForUnit(Unit $unit, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'level' => UserRole::USER->value,
            'verified_at' => now(),
            'unit_id' => $unit->id,
            'instansi' => $unit->instansi,
        ], $overrides));
    }

    protected function createFeatureRequest(User $requester, string $status): FeatureRequest
    {
        $requester->loadMissing('unit');

        return FeatureRequest::create([
            'user_id' => $requester->id,
            'title' => 'Ticket ' . ucfirst($status),
            'description' => 'Pengujian stage',
            'status' => $status,
            'priority' => 'biasa',
            'development_status' => 1,
            'request_types' => ['new_feature'],
            'requester_unit' => $requester->unit?->name ?? 'Unit',
            'requester_instansi' => $requester->instansi ?? 'wiradadi',
            'manager_category_id' => $requester->unit?->manager_category_id,
        ]);
    }

    public function test_submission_stage_only_returns_management_phase_tickets(): void
    {
        $admin = User::factory()->create(['level' => UserRole::ADMIN->value]);
        Sanctum::actingAs($admin);

        $unit = $this->createUnitWithCategory(ManagerCategory::YANMUM->value);
        $requester = $this->createUserForUnit($unit);

        $this->createFeatureRequest($requester, 'pending');
        $this->createFeatureRequest($requester, 'approved_manager');
        $this->createFeatureRequest($requester, 'approved_a');
        $this->createFeatureRequest($requester, 'approved_b');

        $response = $this->getJson('/api/feature-requests?stage=submission&per_page=10');

        $response->assertOk();

        $statuses = collect($response->json('data'))->pluck('status');

        $this->assertCount(3, $statuses);
        $this->assertTrue($statuses->contains('pending'));
        $this->assertTrue($statuses->contains('approved_manager'));
        $this->assertTrue($statuses->contains('approved_a'));
        $this->assertFalse($statuses->contains('approved_b'));
    }

    public function test_development_stage_only_returns_it_phase_tickets(): void
    {
        $admin = User::factory()->create(['level' => UserRole::ADMIN->value]);
        Sanctum::actingAs($admin);

        $unit = $this->createUnitWithCategory(ManagerCategory::YANMED->value, 'raffa');
        $requester = $this->createUserForUnit($unit);

        $this->createFeatureRequest($requester, 'approved_b');
        $this->createFeatureRequest($requester, 'done');
        $this->createFeatureRequest($requester, 'pending');

        $response = $this->getJson('/api/feature-requests?stage=development&per_page=10');

        $response->assertOk();

        $statuses = collect($response->json('data'))->pluck('status');

        $this->assertCount(2, $statuses);
        $this->assertTrue($statuses->contains('approved_b'));
        $this->assertTrue($statuses->contains('done'));
        $this->assertFalse($statuses->contains('pending'));
    }

    public function test_manager_only_receives_requests_for_mapped_category(): void
    {
        $yanmumUnit = $this->createUnitWithCategory(ManagerCategory::YANMUM->value);
        $yanmedUnit = $this->createUnitWithCategory(ManagerCategory::YANMED->value);

        $yanmumManager = User::factory()->create([
            'level' => UserRole::MANAGER->value,
            'manager_category_id' => ManagerCategory::YANMUM->value,
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($yanmumManager);

        $yanmumRequester = $this->createUserForUnit($yanmumUnit);
        $yanmedRequester = $this->createUserForUnit($yanmedUnit);

        $visible = $this->createFeatureRequest($yanmumRequester, 'pending');
        $this->createFeatureRequest($yanmedRequester, 'pending');

        $response = $this->getJson('/api/feature-requests?stage=submission&per_page=10');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($visible->id));
        $this->assertCount(1, $ids);
    }

    public function test_manager_cannot_approve_request_outside_of_category(): void
    {
        $yanmumUnit = $this->createUnitWithCategory(ManagerCategory::YANMUM->value);
        $yanmedUnit = $this->createUnitWithCategory(ManagerCategory::YANMED->value);

        $manager = User::factory()->create([
            'level' => UserRole::MANAGER->value,
            'manager_category_id' => ManagerCategory::YANMUM->value,
            'verified_at' => now(),
            'kode_sign' => Hash::make('12345'),
        ]);

        Sanctum::actingAs($manager);

        $requester = $this->createUserForUnit($yanmedUnit);

        $feature = $this->createFeatureRequest($requester, 'pending');

        $response = $this->postJson("/api/feature-requests/{$feature->id}/approve", [
            'sign_code' => '12345',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseCount('approvals', 0);
    }
}
