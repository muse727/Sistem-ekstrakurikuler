<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Extracurricular;
use App\Models\ExtracurricularRegistration;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;
    protected User $coachUser;
    protected User $studentUser;
    protected User $studentUser2;
    protected Student $student;
    protected Student $student2;
    protected AcademicYear $academicYear;
    protected Extracurricular $ekskul;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'student_number' => '2026001',
            'nisn' => '0081234567',
            'name' => 'Ahmad Rizky',
            'gender' => Gender::MALE,
            'class_name' => 'X-IPA-1',
            'is_active' => true,
        ]);

        $this->student2 = Student::create([
            'student_number' => '2026002',
            'nisn' => '0087654321',
            'name' => 'Siti Nurhaliza',
            'gender' => Gender::FEMALE,
            'class_name' => 'X-IPA-2',
            'is_active' => true,
        ]);

        $this->ekskul = Extracurricular::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Basket',
            'code' => 'BSK',
            'description' => 'Basket club',
            'fee_amount' => 0,
            'quota' => 30,
            'is_active' => true,
        ]);

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
            'student_id' => $this->student->id,
        ]);
        $this->studentUser2 = User::factory()->create([
            'role' => UserRole::STUDENT,
            'is_active' => true,
            'student_id' => $this->student2->id,
        ]);
    }

    // 1. Unauthenticated cannot access student endpoints (401)
    public function test_unauthenticated_cannot_access_student_registrations(): void
    {
        $this->getJson('/api/v1/student/registrations')->assertStatus(401);
        $this->postJson('/api/v1/student/registrations', ['extracurricular_id' => $this->ekskul->id])->assertStatus(401);
    }

    // 2. Unauthenticated cannot access admin endpoints (401)
    public function test_unauthenticated_cannot_access_admin_registrations(): void
    {
        $this->getJson('/api/v1/admin/registrations')->assertStatus(401);
    }

    // 3. Coach denied on student endpoints (403)
    public function test_coach_denied_on_student_endpoints(): void
    {
        $this->actingAs($this->coachUser, 'sanctum')
            ->getJson('/api/v1/student/registrations')->assertStatus(403);
        $this->actingAs($this->coachUser, 'sanctum')
            ->postJson('/api/v1/student/registrations', ['extracurricular_id' => $this->ekskul->id])->assertStatus(403);
    }

    // 4. Coach denied on admin registrations (403)
    public function test_coach_denied_on_admin_registrations(): void
    {
        $this->actingAs($this->coachUser, 'sanctum')
            ->getJson('/api/v1/admin/registrations')->assertStatus(403);
    }

    // 5. Student cannot access admin endpoints (403)
    public function test_student_cannot_access_admin_registrations(): void
    {
        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/admin/registrations')->assertStatus(403);
    }

    // 6. Admin cannot use student endpoints (403)
    public function test_admin_cannot_use_student_endpoints(): void
    {
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/student/registrations')->assertStatus(403);
    }

    // 7. Student can list available extracurriculars
    public function test_student_can_list_extracurriculars(): void
    {
        $res = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/student/extracurriculars');
        $res->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.0.name', 'Basket');
    }

    // 8. Student can view extracurricular detail
    public function test_student_can_view_extracurricular_detail(): void
    {
        $res = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/student/extracurriculars/{$this->ekskul->id}");
        $res->assertStatus(200)->assertJsonPath('data.id', $this->ekskul->id);
    }

    // 9. Submit creates SUBMITTED with academic year + submitted_at
    public function test_student_submit_creates_submitted_registration(): void
    {
        $res = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/registrations', ['extracurricular_id' => $this->ekskul->id]);
        $res->assertStatus(201)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.academic_year_id', $this->academicYear->id)
            ->assertJsonPath('data.student_id', $this->student->id);
        $this->assertNotNull($res->json('data.submitted_at'));
        $this->assertDatabaseHas('extracurricular_registrations', [
            'student_id' => $this->student->id,
            'extracurricular_id' => $this->ekskul->id,
            'status' => 'submitted',
        ]);
    }

    // 10. Duplicate submit => 409
    public function test_duplicate_submit_returns_409(): void
    {
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/registrations', ['extracurricular_id' => $this->ekskul->id])
            ->assertStatus(201);
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/registrations', ['extracurricular_id' => $this->ekskul->id])
            ->assertStatus(409);
    }

    // 11. Inactive extracurricular => 422
    public function test_submit_inactive_extracurricular_returns_422(): void
    {
        $this->ekskul->update(['is_active' => false]);
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/registrations', ['extracurricular_id' => $this->ekskul->id])
            ->assertStatus(422);
    }

    // 12. Ownership: cannot view others (403)
    public function test_student_cannot_view_other_registration(): void
    {
        $reg = ExtracurricularRegistration::create([
            'student_id' => $this->student2->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/student/registrations/{$reg->id}")->assertStatus(403);
    }

    // 13. Ownership: cannot cancel others (403)
    public function test_student_cannot_cancel_other_registration(): void
    {
        $reg = ExtracurricularRegistration::create([
            'student_id' => $this->student2->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/student/registrations/{$reg->id}/cancel")->assertStatus(403);
    }

    // 14. Admin list with pagination
    public function test_admin_can_list_registrations(): void
    {
        ExtracurricularRegistration::create([
            'student_id' => $this->student->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $res = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/registrations');
        $res->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    // 15. Admin filters
    public function test_admin_filters_work(): void
    {
        ExtracurricularRegistration::create([
            'student_id' => $this->student->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/registrations?status=submitted')->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/admin/registrations?academic_year_id={$this->academicYear->id}")->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/admin/registrations?extracurricular_id={$this->ekskul->id}")->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/admin/registrations?student_id={$this->student->id}")->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/registrations?search=Ahmad')->assertStatus(200);
    }

    // 16. Admin detail
    public function test_admin_can_view_detail(): void
    {
        $reg = ExtracurricularRegistration::create([
            'student_id' => $this->student->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/admin/registrations/{$reg->id}")
            ->assertStatus(200)->assertJsonPath('data.id', $reg->id);
    }

    // 17. Approve submitted => approved with audit
    public function test_admin_approve_flow(): void
    {
        $reg = ExtracurricularRegistration::create([
            'student_id' => $this->student->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $res = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/registrations/{$reg->id}/approve");
        $res->assertStatus(200)->assertJsonPath('data.status', 'approved');
        $this->assertNotNull($res->json('data.approved_at'));
        $this->assertEquals($this->adminUser->id, $res->json('data.approved_by'));
    }

    // 18. Approve invalid transition => 422
    public function test_approve_invalid_transition_returns_422(): void
    {
        $reg = ExtracurricularRegistration::create([
            'student_id' => $this->student->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/registrations/{$reg->id}/approve")->assertStatus(422);
    }

    // 19. Reject requires reason + success
    public function test_reject_requires_reason_and_succeeds(): void
    {
        $reg = ExtracurricularRegistration::create([
            'student_id' => $this->student->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/registrations/{$reg->id}/reject", [])
            ->assertStatus(422);
        $res = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/registrations/{$reg->id}/reject", ['rejection_reason' => 'Kuota penuh']);
        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Kuota penuh');
        $this->assertEquals($this->adminUser->id, $res->json('data.rejected_by'));
    }

    // 20. Activate approved => active, invalid => 422
    public function test_activate_flow(): void
    {
        $reg = ExtracurricularRegistration::create([
            'student_id' => $this->student->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/registrations/{$reg->id}/activate")
            ->assertStatus(200)->assertJsonPath('data.status', 'active');

        $reg2 = ExtracurricularRegistration::create([
            'student_id' => $this->student2->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/registrations/{$reg2->id}/activate")->assertStatus(422);
    }

    // 21. Cancel preserves history
    public function test_cancel_preserves_history(): void
    {
        $reg = ExtracurricularRegistration::create([
            'student_id' => $this->student->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $res = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/student/registrations/{$reg->id}/cancel");
        $res->assertStatus(200)->assertJsonPath('data.status', 'cancelled');
        $this->assertNotNull($res->json('data.cancelled_at'));
        $this->assertDatabaseHas('extracurricular_registrations', ['id' => $reg->id, 'status' => 'cancelled']);
    }

    // 22. Quota full => 409 on approve
    public function test_quota_full_returns_409(): void
    {
        $this->ekskul->update(['quota' => 1]);
        $s2 = Student::create([
            'student_number' => '2026009', 'name' => 'Quota One',
            'gender' => Gender::MALE, 'is_active' => true,
        ]);
        ExtracurricularRegistration::create([
            'student_id' => $s2->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'approved',
            'submitted_at' => now(), 'approved_at' => now(),
        ]);
        $reg = ExtracurricularRegistration::create([
            'student_id' => $this->student->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/registrations/{$reg->id}/approve")->assertStatus(409);
    }

    // 23. Re-register after rejected/cancelled allowed + response shape
    public function test_reregister_after_terminal_allowed(): void
    {
        ExtracurricularRegistration::create([
            'student_id' => $this->student->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'rejected',
            'submitted_at' => now(), 'rejected_at' => now(),
            'rejection_reason' => 'old',
        ]);
        $res = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/registrations', ['extracurricular_id' => $this->ekskul->id]);
        $res->assertStatus(201)
            ->assertJsonStructure(['success', 'message', 'data' => ['id', 'status', 'student_id', 'extracurricular_id', 'academic_year_id']]);
    }
}
