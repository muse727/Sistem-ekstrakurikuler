<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Coach;
use App\Models\Extracurricular;
use App\Models\ExtracurricularEvaluation;
use App\Models\ExtracurricularRegistration;
use App\Models\ExtracurricularSession;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use App\Models\Venue;
use App\Services\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;
    protected User $coachUser;
    protected User $coachUser2;
    protected User $studentUser;
    protected User $studentUser2;
    protected Student $student;
    protected Student $student2;
    protected Coach $coach;
    protected Coach $coach2;
    protected AcademicYear $academicYear;
    protected AcademicYear $academicYear2;
    protected Extracurricular $ekskul;
    protected Extracurricular $ekskul2;
    protected Venue $venue;
    protected ExtracurricularRegistration $registration;
    protected ExtracurricularRegistration $registration2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01',
            'end_date' => '2027-06-30', 'is_active' => true,
        ]);
        $this->academicYear2 = AcademicYear::create([
            'name' => '2025/2026', 'start_date' => '2025-07-01',
            'end_date' => '2026-06-30', 'is_active' => false,
        ]);

        $this->venue = Venue::create([
            'name' => 'Lapangan Utama', 'address' => 'Jl. Test',
            'latitude' => -6.2088000, 'longitude' => 106.8456000,
            'radius_meters' => 100, 'is_active' => true,
        ]);

        $this->student = Student::create([
            'student_number' => '2026001', 'nisn' => '0081234567',
            'name' => 'Ahmad', 'gender' => Gender::MALE,
            'class_name' => 'X-1', 'is_active' => true,
        ]);
        $this->student2 = Student::create([
            'student_number' => '2026002', 'nisn' => '0087654321',
            'name' => 'Siti', 'gender' => Gender::FEMALE,
            'class_name' => 'X-2', 'is_active' => true,
        ]);

        $this->coach = Coach::create(['employee_number' => 'EMP1', 'name' => 'Coach A', 'is_active' => true]);
        $this->coach2 = Coach::create(['employee_number' => 'EMP2', 'name' => 'Coach B', 'is_active' => true]);

        $this->ekskul = Extracurricular::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Basket', 'code' => 'BSK', 'fee_amount' => 0,
            'quota' => 30, 'is_active' => true,
        ]);
        $this->ekskul2 = Extracurricular::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Pramuka', 'code' => 'PRM', 'fee_amount' => 0,
            'quota' => 30, 'is_active' => true,
        ]);
        $this->ekskul->coaches()->attach([$this->coach->id => ['role' => 'primary']]);
        $this->ekskul2->coaches()->attach([$this->coach2->id => ['role' => 'primary']]);

        $this->adminUser = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->superAdminUser = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $this->coachUser = User::factory()->create(['role' => UserRole::COACH, 'is_active' => true, 'coach_id' => $this->coach->id]);
        $this->coachUser2 = User::factory()->create(['role' => UserRole::COACH, 'is_active' => true, 'coach_id' => $this->coach2->id]);
        $this->studentUser = User::factory()->create(['role' => UserRole::STUDENT, 'is_active' => true, 'student_id' => $this->student->id]);
        $this->studentUser2 = User::factory()->create(['role' => UserRole::STUDENT, 'is_active' => true, 'student_id' => $this->student2->id]);

        $this->registration = ExtracurricularRegistration::create([
            'student_id' => $this->student->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'active',
            'submitted_at' => now()->subDays(5),
            'approved_at' => now()->subDays(4),
        ]);
        $this->registration2 = ExtracurricularRegistration::create([
            'student_id' => $this->student2->id,
            'extracurricular_id' => $this->ekskul2->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'active',
            'submitted_at' => now()->subDays(5),
            'approved_at' => now()->subDays(4),
        ]);
    }

    protected function draftPayload(?int $registrationId = null, string $period = 'midterm', array $scores = []): array
    {
        return array_merge([
            'registration_id' => $registrationId ?? $this->registration->id,
            'evaluation_period' => $period,
            'notes' => 'Catatan evaluasi',
        ], $scores);
    }

    protected function fullScores(): array
    {
        return [
            'attendance_score' => 100,
            'activity_score' => 80,
            'skill_score' => 60,
            'discipline_score' => 40,
        ];
    }

    // 1. eligible create (draft, incomplete allowed)
    public function test_coach_can_create_draft_for_eligible_registration(): void
    {
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload());
        $res->assertStatus(201)->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('extracurricular_evaluations', [
            'registration_id' => $this->registration->id,
            'evaluation_period' => 'midterm',
            'status' => 'draft',
            'evaluator_id' => $this->coachUser->id,
        ]);
    }

    // 2. wrong ekskul denied (coach not assigned)
    public function test_coach_cannot_evaluate_other_ekskul(): void
    {
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload($this->registration2->id));
        $res->assertStatus(403);
    }

    // 3. ineligible registration denied (cancelled)
    public function test_ineligible_registration_denied(): void
    {
        $this->registration->update(['status' => 'cancelled']);
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload());
        $res->assertStatus(422);
    }

    // 4. submitted registration also ineligible
    public function test_submitted_registration_denied(): void
    {
        $this->registration->update(['status' => 'submitted']);
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload());
        $res->assertStatus(422);
    }

    // 5. student cannot create
    public function test_student_cannot_create_evaluation(): void
    {
        $res = $this->actingAs($this->studentUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload());
        $res->assertStatus(403);
        $res2 = $this->actingAs($this->studentUser, 'sanctum')->postJson('/api/v1/student/evaluations', $this->draftPayload());
        $res2->assertStatus(405);
    }

    // 6. admin can view list + detail
    public function test_admin_can_view_evaluations(): void
    {
        $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload())->assertStatus(201);
        $eval = ExtracurricularEvaluation::first();
        $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/evaluations')->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')->getJson("/api/v1/admin/evaluations/{$eval->id}")->assertStatus(200);
    }

    // 7. student sees only own published
    public function test_student_views_own_published_only(): void
    {
        $svc = app(EvaluationService::class);
        $draft = $svc->createDraft($this->coachUser, $this->registration->id, 'midterm', $this->fullScores());
        $svc->publish($this->coachUser, $draft);

        // other student's published (different ekskul via coach2)
        $other = $svc->createDraft($this->coachUser2, $this->registration2->id, 'midterm', $this->fullScores());
        $svc->publish($this->coachUser2, $other);

        $list = $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/student/evaluations');
        $list->assertStatus(200);
        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertContains($draft->fresh()->id, $ids);
        $this->assertNotContains($other->fresh()->id, $ids);

        // cannot view other's published
        $this->actingAs($this->studentUser, 'sanctum')->getJson("/api/v1/student/evaluations/{$other->id}")->assertStatus(403);
    }

    // 8. student cannot view draft (404)
    public function test_student_cannot_view_draft(): void
    {
        $svc = app(EvaluationService::class);
        $draft = $svc->createDraft($this->coachUser, $this->registration->id, 'midterm', []);
        $this->actingAs($this->studentUser, 'sanctum')->getJson("/api/v1/student/evaluations/{$draft->id}")->assertStatus(404);
        // draft not in list either
        $list = $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/student/evaluations');
        $this->assertCount(0, $list->json('data'));
    }

    // 9. scores below 0 rejected
    public function test_scores_below_zero_rejected(): void
    {
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload(null, 'midterm', ['attendance_score' => -5]));
        $res->assertStatus(422);
    }

    // 10. scores above 100 rejected
    public function test_scores_above_100_rejected(): void
    {
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload(null, 'midterm', ['skill_score' => 150]));
        $res->assertStatus(422);
    }

    // 11. missing scores prevent publish
    public function test_missing_scores_prevent_publish(): void
    {
        $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload())->assertStatus(201);
        $eval = ExtracurricularEvaluation::first();
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/evaluations/{$eval->id}/publish");
        $res->assertStatus(422);
    }

    // 12. server calculates final (71.00 for 100/80/60/40)
    public function test_server_calculates_final_score(): void
    {
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload(null, 'midterm', $this->fullScores()));
        $res->assertStatus(201);
        $this->assertEquals(71.0, (float) $res->json('data.final_score'));
    }

    // 13. client final_score ignored
    public function test_client_final_score_ignored(): void
    {
        $payload = array_merge($this->draftPayload(null, 'midterm', $this->fullScores()), ['final_score' => 100]);
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $payload);
        $res->assertStatus(201);
        $this->assertEquals(71.0, (float) $res->json('data.final_score'));
    }

    // 14. weighting verified via service directly
    public function test_weighting_formula_correct(): void
    {
        $this->assertEquals(71.0, EvaluationService::calculateFinal(100, 80, 60, 40));
        $this->assertEquals(80.0, EvaluationService::calculateFinal(80, 80, 80, 80));
        $this->assertNull(EvaluationService::calculateFinal(80, null, 80, 80));
    }

    // 15. duplicate registration+period -> 409
    public function test_duplicate_returns_409(): void
    {
        $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload())->assertStatus(201);
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload());
        $res->assertStatus(409);
    }

    // 16. same registration different period allowed
    public function test_different_period_allowed(): void
    {
        $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload(null, 'midterm'))->assertStatus(201);
        $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload(null, 'final'))->assertStatus(201);
    }

    // 17. no impersonation: evaluator derived from auth
    public function test_evaluator_derived_from_auth_user(): void
    {
        $payload = array_merge($this->draftPayload(), ['evaluator_id' => $this->coachUser2->id]);
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $payload);
        $res->assertStatus(201)->assertJsonPath('data.evaluator_id', $this->coachUser->id);
    }

    // 18. anti-tampering: student/ekskul/year/final derived from registration
    public function test_identity_fields_derived_from_registration(): void
    {
        $payload = array_merge($this->draftPayload(null, 'midterm', $this->fullScores()), [
            'student_id' => $this->student2->id,
            'extracurricular_id' => $this->ekskul2->id,
            'academic_year_id' => $this->academicYear2->id,
            'final_score' => 99.99,
        ]);
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $payload);
        $res->assertStatus(201);
        $res->assertJsonPath('data.student_id', $this->student->id);
        $res->assertJsonPath('data.extracurricular_id', $this->ekskul->id);
        $res->assertJsonPath('data.academic_year_id', $this->academicYear->id);
        $this->assertEquals(71.0, (float) $res->json('data.final_score'));
    }

    // 19. admin can correct draft
    public function test_admin_can_correct_draft(): void
    {
        $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload(null, 'midterm', ['attendance_score' => 50]))->assertStatus(201);
        $eval = ExtracurricularEvaluation::first();
        $res = $this->actingAs($this->adminUser, 'sanctum')->patchJson("/api/v1/admin/evaluations/{$eval->id}", ['attendance_score' => 90, 'notes' => 'Koreksi admin']);
        $res->assertStatus(200);
        $this->assertEquals(90.0, (float) $res->json('data.attendance_score'));
    }

    // 20. publish works + sets evaluated_at
    public function test_publish_sets_published_and_evaluated_at(): void
    {
        $this->actingAs($this->coachUser, 'sanctum')->postJson('/api/v1/coach/evaluations', $this->draftPayload(null, 'midterm', $this->fullScores()))->assertStatus(201);
        $eval = ExtracurricularEvaluation::first();
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/evaluations/{$eval->id}/publish");
        $res->assertStatus(200)->assertJsonPath('data.status', 'published');
        $this->assertNotNull($res->json('data.evaluated_at'));
    }

    // 21. invalid transition: update published rejected
    public function test_update_published_rejected(): void
    {
        $svc = app(EvaluationService::class);
        $draft = $svc->createDraft($this->coachUser, $this->registration->id, 'midterm', $this->fullScores());
        $svc->publish($this->coachUser, $draft);
        $res = $this->actingAs($this->coachUser, 'sanctum')->patchJson("/api/v1/coach/evaluations/{$draft->id}", ['notes' => 'ubah']);
        $res->assertStatus(422);
        $res2 = $this->actingAs($this->adminUser, 'sanctum')->patchJson("/api/v1/admin/evaluations/{$draft->id}", ['notes' => 'ubah']);
        $res2->assertStatus(422);
    }

    // 22. double publish rejected
    public function test_double_publish_rejected(): void
    {
        $svc = app(EvaluationService::class);
        $draft = $svc->createDraft($this->coachUser, $this->registration->id, 'midterm', $this->fullScores());
        $svc->publish($this->coachUser, $draft);
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/evaluations/{$draft->id}/publish");
        $res->assertStatus(422);
    }

    // 23. attendance summary matches records
    public function test_attendance_summary_matches(): void
    {
        $svc = app(EvaluationService::class);
        $eval = $svc->createDraft($this->coachUser, $this->registration->id, 'midterm', []);

        $s1 = ExtracurricularSession::create([
            'extracurricular_id' => $this->ekskul->id, 'academic_year_id' => $this->academicYear->id,
            'venue_id' => $this->venue->id, 'session_date' => now()->toDateString(),
            'start_time' => '15:00:00', 'end_time' => '17:00:00', 'status' => 'completed',
            'topic' => 'L1', 'created_by' => $this->adminUser->id,
        ]);
        $s2 = ExtracurricularSession::create([
            'extracurricular_id' => $this->ekskul->id, 'academic_year_id' => $this->academicYear->id,
            'venue_id' => $this->venue->id, 'session_date' => now()->toDateString(),
            'start_time' => '15:00:00', 'end_time' => '17:00:00', 'status' => 'completed',
            'topic' => 'L2', 'created_by' => $this->adminUser->id,
        ]);
        StudentAttendance::create(['session_id' => $s1->id, 'student_id' => $this->student->id, 'registration_id' => $this->registration->id, 'status' => 'hadir', 'recorded_by' => $this->coachUser->id, 'recorded_at' => now()]);
        StudentAttendance::create(['session_id' => $s2->id, 'student_id' => $this->student->id, 'registration_id' => $this->registration->id, 'status' => 'izin', 'recorded_by' => $this->coachUser->id, 'recorded_at' => now()]);

        $res = $this->actingAs($this->coachUser, 'sanctum')->getJson("/api/v1/coach/evaluations/{$eval->id}/summary");
        $res->assertStatus(200)
            ->assertJsonPath('data.attendance_summary.total_sessions', 2)
            ->assertJsonPath('data.attendance_summary.hadir', 1)
            ->assertJsonPath('data.attendance_summary.izin', 1);
        $this->assertEquals(50.0, (float) $res->json('data.attendance_summary.percentage'));
    }

    // 24. evaluation ops never mutate attendance
    public function test_evaluation_does_not_mutate_attendance(): void
    {
        $s1 = ExtracurricularSession::create([
            'extracurricular_id' => $this->ekskul->id, 'academic_year_id' => $this->academicYear->id,
            'venue_id' => $this->venue->id, 'session_date' => now()->toDateString(),
            'start_time' => '15:00:00', 'end_time' => '17:00:00', 'status' => 'completed',
            'topic' => 'L1', 'created_by' => $this->adminUser->id,
        ]);
        StudentAttendance::create(['session_id' => $s1->id, 'student_id' => $this->student->id, 'registration_id' => $this->registration->id, 'status' => 'hadir', 'recorded_by' => $this->coachUser->id, 'recorded_at' => now()]);
        $before = StudentAttendance::count();

        $svc = app(EvaluationService::class);
        $eval = $svc->createDraft($this->coachUser, $this->registration->id, 'midterm', $this->fullScores());
        $svc->publish($this->coachUser, $eval);
        $svc->attendanceSummary($eval->fresh());

        $this->assertEquals($before, StudentAttendance::count());
        $this->assertEquals('hadir', StudentAttendance::first()->status instanceof \BackedEnum ? StudentAttendance::first()->status->value : (string) StudentAttendance::first()->status);
    }

    // 25. 401/403 + coach isolation on read
    public function test_auth_and_isolation(): void
    {
        $svc = app(EvaluationService::class);
        $eval = $svc->createDraft($this->coachUser, $this->registration->id, 'midterm', []);

        $this->getJson('/api/v1/coach/evaluations')->assertStatus(401);
        $this->getJson('/api/v1/admin/evaluations')->assertStatus(401);
        $this->getJson('/api/v1/student/evaluations')->assertStatus(401);

        $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/admin/evaluations')->assertStatus(403);
        $this->actingAs($this->coachUser, 'sanctum')->getJson('/api/v1/admin/evaluations')->assertStatus(403);
        $this->actingAs($this->coachUser, 'sanctum')->getJson('/api/v1/student/evaluations')->assertStatus(403);

        // coach2 (other ekskul) cannot view coach1's evaluation
        $this->actingAs($this->coachUser2, 'sanctum')->getJson("/api/v1/coach/evaluations/{$eval->id}")->assertStatus(403);
    }
}
