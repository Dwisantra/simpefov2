<?php

namespace Tests\Feature;

use App\Enums\ManagerCategory;
use App\Enums\UserRole;
use App\Models\FeatureRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JangmedPriorityTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsJangmedManager(): User
    {
        $manager = User::factory()->create([
            'level' => UserRole::MANAGER->value,
            'manager_category_id' => ManagerCategory::JANGMED->value,
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        return $manager;
    }

    protected function createRequester(ManagerCategory $category = ManagerCategory::JANGMED, string $instansi = 'wiradadi'): User
    {
        $unit = Unit::factory()->create([
            'manager_category_id' => $category->value,
            'instansi' => $instansi,
        ]);

        return User::factory()->create([
            'level' => UserRole::USER->value,
            'verified_at' => now(),
            'unit_id' => $unit->id,
            'instansi' => $unit->instansi,
        ]);
    }

    protected function createFeature(User $requester, array $attributes = []): FeatureRequest
    {
        $requester->loadMissing('unit');

        return FeatureRequest::create(array_merge([
            'user_id' => $requester->id,
            'title' => 'Pengujian Prioritas',
            'description' => 'Deskripsi singkat',
            'status' => 'approved_b',
            'priority' => 'biasa',
            'development_status' => 1,
            'request_types' => ['new_feature'],
            'requester_unit_id' => $requester->unit?->id,
            'requester_instansi' => $requester->instansi ?? 'wiradadi',
            'manager_category_id' => $requester->unit?->manager_category_id,
        ], $attributes));
    }

    public function test_jangmed_manager_sees_active_requests_by_default(): void
    {
        $manager = $this->actingAsJangmedManager();
        $requester = $this->createRequester();
        $otherRequester = $this->createRequester(ManagerCategory::YANMUM, 'raffa');

        $completedA = $this->createFeature($requester, ['status' => 'approved_b']);
        $otherApproved = $this->createFeature($otherRequester, ['status' => 'approved_b']);
        $completedB = $this->createFeature($requester, ['status' => 'done']);
        $this->createFeature($requester, ['status' => 'pending']);

        $response = $this->getJson('/api/manager/jangmed/priorities');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'total',
            ]);

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($completedA->id));
        $this->assertTrue($ids->contains($otherApproved->id));
        $this->assertFalse($ids->contains($completedB->id));
        $this->assertCount(2, $response->json('data'));
    }

    public function test_jangmed_manager_can_view_completed_scope(): void
    {
        $this->actingAsJangmedManager();
        $requester = $this->createRequester();
        $otherRequester = $this->createRequester(ManagerCategory::YANMED, 'wiradadi');

        $this->createFeature($requester, ['status' => 'approved_b']);
        $done = $this->createFeature($requester, ['status' => 'done']);
        $otherDone = $this->createFeature($otherRequester, ['status' => 'done']);

        $response = $this->getJson('/api/manager/jangmed/priorities?scope=completed');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($done->id));
        $this->assertTrue($ids->contains($otherDone->id));
        $this->assertCount(2, $ids);
    }

    public function test_jangmed_manager_sees_requests_from_all_categories(): void
    {
        $this->actingAsJangmedManager();

        $jangmedRequester = $this->createRequester();
        $yanmumRequester = $this->createRequester(ManagerCategory::YANMUM, 'raffa');

        $visible = $this->createFeature($jangmedRequester, [
            'status' => 'approved_b',
            'manager_category_id' => ManagerCategory::JANGMED->value,
        ]);

        $other = $this->createFeature($yanmumRequester, [
            'status' => 'approved_b',
            'manager_category_id' => ManagerCategory::YANMUM->value,
            'requester_unit_id' => $yanmumRequester->unit->id,
            'requester_instansi' => $yanmumRequester->unit->instansi,
        ]);

        $response = $this->getJson('/api/manager/jangmed/priorities');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($visible->id));
        $this->assertTrue($ids->contains($other->id));
        $this->assertCount(2, $ids);
    }

    public function test_non_jangmed_manager_cannot_access_priorities(): void
    {
        $manager = User::factory()->create([
            'level' => UserRole::MANAGER->value,
            'manager_category_id' => ManagerCategory::YANMED->value,
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/manager/jangmed/priorities');

        $response->assertForbidden();
    }

    public function test_jangmed_manager_can_update_priority_for_completed_request(): void
    {
        $manager = $this->actingAsJangmedManager();
        $requester = $this->createRequester();

        $feature = $this->createFeature($requester, ['status' => 'done']);

        $response = $this->patchJson("/api/manager/jangmed/priorities/{$feature->id}", [
            'priority' => 'cito',
        ]);

        $response->assertOk()->assertJson([
            'message' => 'Prioritas ticket berhasil diperbarui.',
        ]);

        $this->assertSame('cito', $feature->fresh()->priority);
    }

    public function test_cannot_update_priority_when_request_not_completed(): void
    {
        $manager = $this->actingAsJangmedManager();
        $requester = $this->createRequester();

        $feature = $this->createFeature($requester, ['status' => 'pending']);

        $response = $this->patchJson("/api/manager/jangmed/priorities/{$feature->id}", [
            'priority' => 'sedang',
        ]);

        $response->assertStatus(422);
        $this->assertSame('biasa', $feature->fresh()->priority);
    }
}
