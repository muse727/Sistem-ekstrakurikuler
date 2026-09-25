<?php

namespace Tests\Feature\Api\V1;

use App\Enums\CoachRole;
use App\Enums\DayOfWeek;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Coach;
use App\Models\Extracurricular;
use App\Models\ExtracurricularSchedule;
use App\Models\Student;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;
    protected User $coachUser;
    protected User $studentUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->superAdminUser = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->coachUser = User::factory()->create([
            'role' => UserRole::COACH,
            'is_active' => true,
        ]);

        $this->studentUser = User::factory()->create([
            'role' => UserRole::STUDENT,
            'is_active' => true,
        ]);
    }

    // 1. RBAC & Auth protection tests
    public function test_unauthenticated_user_cannot_access_admin_routes(): void
    {
        $response = $this->getJson('/api/v1/admin/academic-years');
        $response->assertStatus(401);
    }

    public function test_coach_and_student_receive_403_on_admin_routes(): void
    {
        $coachResponse = $this->actingAs($this->coachUser, 'sanctum')
            ->getJson('/api/v1/admin/academic-years');
        $coachResponse->assertStatus(403);

        $studentResponse = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/admin/students');
        $studentResponse->assertStatus(403);
    }

    public function test_admin_and_super_admin_can_access_admin_routes(): void
    {
        $adminResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/academic-years');
        $adminResponse->assertStatus(200);

        $superAdminResponse = $this->actingAs($this->superAdminUser, 'sanctum')
            ->getJson('/api/v1/admin/academic-years');
        $superAdminResponse->assertStatus(200);
    }

    // 2. Academic Years Management & Transactional Activation
    public function test_academic_year_crud_and_transactional_activation(): void
    {
        $ay1 = AcademicYear::create([
            'name' => '2025/2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        // Create new AY with is_active = true, ay1 should automatically become false
        $createResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/academic-years', [
                'name' => '2026/2027',
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'is_active' => true,
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.name', '2026/2027')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('academic_years', [
            'id' => $ay1->id,
            'is_active' => false,
        ]);

        $ay2Id = $createResponse->json('data.id');

        // Test activate endpoint on ay1
        $activateResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/academic-years/{$ay1->id}/activate");

        $activateResponse->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('academic_years', [
            'id' => $ay2Id,
            'is_active' => false,
        ]);

        // Test deactivate endpoint on ay1
        $deactivateResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/academic-years/{$ay1->id}/deactivate");

        $deactivateResponse->assertStatus(200)
            ->assertJsonPath('data.is_active', false);
    }

    // 3. Pagination & Search Filtering
    public function test_pagination_and_search_filtering(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            Student::create([
                'student_number' => 'STD' . sprintf('%03d', $i),
                'name' => "Student {$i}",
                'gender' => Gender::MALE,
                'class_name' => $i <= 10 ? 'X-IPA-1' : 'X-IPS-1',
            ]);
        }

        // Default pagination = 15
        $res = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/students');

        $res->assertStatus(200)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonCount(15, 'data');

        // Custom per_page = 5
        $resCustom = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/students?per_page=5');

        $resCustom->assertStatus(200)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonCount(5, 'data');

        // Search filtering: STD01 matches STD010..STD019 (10 records)
        $resSearch = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/students?search=STD01');

        $resSearch->assertStatus(200)
            ->assertJsonPath('meta.total', 10);
    }

    // 4. Extracurricular Coach Detach & Schedule Delete
    public function test_extracurricular_coach_detach_and_schedule_delete(): void
    {
        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $coach = Coach::create([
            'employee_number' => 'EMP100',
            'name' => 'Coach Alpha',
        ]);

        $venue = Venue::create([
            'name' => 'GOR Utama',
        ]);

        $ekskul = Extracurricular::create([
            'academic_year_id' => $ay->id,
            'name' => 'Futsal',
            'code' => 'FTS',
            'fee_amount' => 150000,
        ]);

        // Assign coach
        $assignRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/extracurriculars/{$ekskul->id}/coaches", [
                'coach_id' => $coach->id,
                'role' => 'primary',
            ]);

        $assignRes->assertStatus(200)
            ->assertJsonCount(1, 'data.coaches');

        // Detach coach
        $detachRes = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/admin/extracurriculars/{$ekskul->id}/coaches/{$coach->id}");

        $detachRes->assertStatus(200)
            ->assertJsonCount(0, 'data.coaches');

        // Add schedule
        $schedRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/extracurriculars/{$ekskul->id}/schedules", [
                'venue_id' => $venue->id,
                'day_of_week' => DayOfWeek::MONDAY->value,
                'start_time' => '14:00',
                'end_time' => '16:00',
            ]);

        $schedRes->assertStatus(201)
            ->assertJsonPath('data.day_of_week', 'monday');

        $scheduleId = $schedRes->json('data.id');

        // Delete schedule
        $delSchedRes = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/admin/extracurriculars/{$ekskul->id}/schedules/{$scheduleId}");

        $delSchedRes->assertStatus(200);

        $this->assertDatabaseMissing('extracurricular_schedules', [
            'id' => $scheduleId,
        ]);
    }

    // 5. Verification that DELETE is NOT allowed on main entities
    public function test_delete_http_method_not_allowed_on_master_entities(): void
    {
        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        $res = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/admin/academic-years/{$ay->id}");

        $res->assertStatus(405);
    }
}
