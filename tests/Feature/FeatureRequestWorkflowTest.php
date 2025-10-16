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

    protected function createFeatureRequest(User $requester, string $status, array $overrides = []): FeatureRequest
    {
        $requester->loadMissing('unit');

        return FeatureRequest::create(array_merge([
            'user_id' => $requester->id,
            'title' => 'Ticket ' . ucfirst($status),
            'description' => 'Pengujian stage',
            'status' => $status,
            'priority' => 'biasa',
            'development_status' => 1,
            'request_types' => ['new_feature'],
            'requester_unit_id' => $requester->unit?->id,
            'requester_instansi' => $requester->instansi ?? 'wiradadi',
            'manager_category_id' => $requester->unit?->manager_category_id,
        ], $overrides));
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

    public function test_wiradadi_request_requires_raffa_director_before_wiradadi_director(): void
    {
        $unit = $this->createUnitWithCategory(ManagerCategory::YANMED->value, 'wiradadi');
        $requester = $this->createUserForUnit($unit);
        $feature = $this->createFeatureRequest($requester, 'pending');

        $manager = User::factory()->create([
            'level' => UserRole::MANAGER->value,
            'manager_category_id' => $unit->manager_category_id,
            'verified_at' => now(),
            'kode_sign' => Hash::make('1111'),
            'instansi' => $unit->instansi,
        ]);

        $directorRaffa = User::factory()->create([
            'level' => UserRole::DIRECTOR_A->value,
            'verified_at' => now(),
            'kode_sign' => Hash::make('2222'),
            'instansi' => 'raffa',
        ]);

        $directorWiradadi = User::factory()->create([
            'level' => UserRole::DIRECTOR_B->value,
            'verified_at' => now(),
            'kode_sign' => Hash::make('3333'),
            'instansi' => 'wiradadi',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/feature-requests/{$feature->id}/approve", [
            'sign_code' => '1111',
        ])->assertOk();

        $feature->refresh();
        $this->assertSame('approved_manager', $feature->status);

        Sanctum::actingAs($directorWiradadi);

        $this->postJson("/api/feature-requests/{$feature->id}/approve", [
            'sign_code' => '3333',
        ])->assertStatus(422);

        $feature->refresh();
        $this->assertSame('approved_manager', $feature->status);

        Sanctum::actingAs($directorRaffa);

        $this->postJson("/api/feature-requests/{$feature->id}/approve", [
            'sign_code' => '2222',
        ])->assertOk();

        $feature->refresh();
        $this->assertSame('approved_a', $feature->status);

        Sanctum::actingAs($directorWiradadi);

        $this->postJson("/api/feature-requests/{$feature->id}/approve", [
            'sign_code' => '3333',
        ])->assertOk();

        $feature->refresh();
        $this->assertSame('approved_b', $feature->status);
    }

    public function test_wiradadi_request_skips_raffa_director_when_configured(): void
    {
        config(['feature-requests.skip_raffa_director_for_wiradadi' => true]);

        $unit = $this->createUnitWithCategory(ManagerCategory::YANMED->value, 'wiradadi');
        $requester = $this->createUserForUnit($unit);
        $feature = $this->createFeatureRequest($requester, 'pending');

        $manager = User::factory()->create([
            'level' => UserRole::MANAGER->value,
            'manager_category_id' => $unit->manager_category_id,
            'verified_at' => now(),
            'kode_sign' => Hash::make('1111'),
            'instansi' => $unit->instansi,
        ]);

        $directorRaffa = User::factory()->create([
            'level' => UserRole::DIRECTOR_A->value,
            'verified_at' => now(),
            'kode_sign' => Hash::make('2222'),
            'instansi' => 'raffa',
        ]);

        $directorWiradadi = User::factory()->create([
            'level' => UserRole::DIRECTOR_B->value,
            'verified_at' => now(),
            'kode_sign' => Hash::make('3333'),
            'instansi' => 'wiradadi',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/feature-requests/{$feature->id}/approve", [
            'sign_code' => '1111',
        ])->assertOk();

        $feature->refresh();
        $this->assertSame('approved_a', $feature->status);

        Sanctum::actingAs($directorWiradadi);

        $this->postJson("/api/feature-requests/{$feature->id}/approve", [
            'sign_code' => '3333',
        ])->assertOk();

        $feature->refresh();
        $this->assertSame('approved_b', $feature->status);

        Sanctum::actingAs($directorRaffa);

        $this->postJson("/api/feature-requests/{$feature->id}/approve", [
            'sign_code' => '2222',
        ])->assertStatus(422);

        $feature->refresh();
        $this->assertSame('approved_b', $feature->status);
    }

    public function test_raffa_director_does_not_see_wiradadi_requests_when_skip_is_enabled(): void
    {
        config(['feature-requests.skip_raffa_director_for_wiradadi' => true]);

        $wiradadiUnit = $this->createUnitWithCategory(ManagerCategory::YANMED->value, 'wiradadi');
        $wiradadiRequester = $this->createUserForUnit($wiradadiUnit);
        $hiddenFeature = $this->createFeatureRequest($wiradadiRequester, 'approved_a');

        $raffaUnit = $this->createUnitWithCategory(ManagerCategory::YANMED->value, 'raffa');
        $raffaRequester = $this->createUserForUnit($raffaUnit);
        $visibleFeature = $this->createFeatureRequest($raffaRequester, 'approved_manager');

        $directorRaffa = User::factory()->create([
            'level' => UserRole::DIRECTOR_A->value,
            'verified_at' => now(),
            'instansi' => 'raffa',
        ]);

        Sanctum::actingAs($directorRaffa);

        $response = $this->getJson('/api/feature-requests?stage=submission&per_page=10');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($visibleFeature->id));
        $this->assertFalse($ids->contains($hiddenFeature->id));
    }

    public function test_monitoring_tabs_filter_tickets_by_progress_stage(): void
    {
        $unit = $this->createUnitWithCategory(ManagerCategory::YANMED->value, 'wiradadi');
        $requesterA = $this->createUserForUnit($unit);
        $requesterB = $this->createUserForUnit($unit);

        $inProgress = $this->createFeatureRequest($requesterA, 'approved_b', ['development_status' => 2]);
        $readyRelease = $this->createFeatureRequest($requesterA, 'approved_b', ['development_status' => 4]);
        $completed = $this->createFeatureRequest($requesterB, 'done', ['development_status' => 4]);
        $this->createFeatureRequest($requesterA, 'pending');

        Sanctum::actingAs($requesterB);

        $inProgressResponse = $this->getJson('/api/feature-requests/monitoring?tab=pengerjaan&per_page=10');

        $inProgressResponse->assertOk();

        $inProgressIds = collect($inProgressResponse->json('data'))->pluck('id');

        $this->assertTrue($inProgressIds->contains($inProgress->id));
        $this->assertFalse($inProgressIds->contains($completed->id));
        $this->assertFalse($inProgressIds->contains($readyRelease->id));

        $completedResponse = $this->getJson('/api/feature-requests/monitoring?tab=selesai&per_page=10');

        $completedResponse->assertOk();

        $completedIds = collect($completedResponse->json('data'))->pluck('id');

        $this->assertTrue($completedIds->contains($completed->id));
        $this->assertTrue($completedIds->contains($readyRelease->id));
        $this->assertFalse($completedIds->contains($inProgress->id));
    }

    public function test_monitoring_defaults_to_active_development_tab(): void
    {
        $unit = $this->createUnitWithCategory(ManagerCategory::YANMED->value, 'raffa');
        $requester = $this->createUserForUnit($unit);

        $inProgress = $this->createFeatureRequest($requester, 'approved_b', ['development_status' => 3]);
        $this->createFeatureRequest($requester, 'done', ['development_status' => 4]);

        Sanctum::actingAs($requester);

        $response = $this->getJson('/api/feature-requests/monitoring?per_page=10');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($inProgress->id));
        $this->assertCount(1, $ids);
    }
}
