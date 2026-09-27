<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Coach;
use App\Models\Extracurricular;
use App\Models\ExtracurricularEvaluation;
use App\Models\ExtracurricularInvoice;
use App\Models\ExtracurricularRegistration;
use App\Models\ExtracurricularSession;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;
    protected User $coachUser;
    protected User $coachUser2;
    protected User $studentUser;
    protected User $studentUser2;
    protected User $studentUser3;
    protected Student $student;
    protected Student $student2;
    protected Student $student3;
    protected Coach $coach;
    protected Coach $coach2;
    protected AcademicYear $year;
    protected AcademicYear $year2;
    protected Extracurricular $ekskul;
    protected Extracurricular $ekskul2;
    protected Extracurricular $ekskulOtherYear;
    protected Venue $venue;

    protected int $invSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01',
            'end_date' => '2027-06-30', 'is_active' => true,
        ]);
        $this->year2 = AcademicYear::create([
            'name' => '2025/2026', 'start_date' => '2025-07-01',
            'end_date' => '2026-06-30', 'is_active' => false,
        ]);

        $this->venue = Venue::create([
            'name' => 'Lapangan', 'address' => 'Jl. Test',
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
        $this->student3 = Student::create([
            'student_number' => '2026003', 'nisn' => '0081112223',
            'name' => 'Budi', 'gender' => Gender::MALE,
            'class_name' => 'X-3', 'is_active' => true,
        ]);

        $this->coach = Coach::create(['employee_number' => 'EMP1', 'name' => 'Coach A', 'is_active' => true]);
        $this->coach2 = Coach::create(['employee_number' => 'EMP2', 'name' => 'Coach B', 'is_active' => true]);

        $this->ekskul = Extracurricular::create([
            'academic_year_id' => $this->year->id, 'name' => 'Basket',
            'code' => 'BSK', 'fee_amount' => 250000, 'quota' => 30, 'is_active' => true,
        ]);
        $this->ekskul2 = Extracurricular::create([
            'academic_year_id' => $this->year->id, 'name' => 'Pramuka',
            'code' => 'PRM', 'fee_amount' => 100000, 'quota' => 30, 'is_active' => true,
        ]);
        $this->ekskulOtherYear = Extracurricular::create([
            'academic_year_id' => $this->year2->id, 'name' => 'Futsal Lama',
            'code' => 'FTL', 'fee_amount' => 50000, 'quota' => 30, 'is_active' => true,
        ]);

        $this->ekskul->coaches()->attach([$this->coach->id => ['role' => 'primary']]);
        $this->ekskul2->coaches()->attach([$this->coach2->id => ['role' => 'primary']]);

        $this->adminUser = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->superAdminUser = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $this->coachUser = User::factory()->create(['role' => UserRole::COACH, 'is_active' => true, 'coach_id' => $this->coach->id]);
        $this->coachUser2 = User::factory()->create(['role' => UserRole::COACH, 'is_active' => true, 'coach_id' => $this->coach2->id]);
        $this->studentUser = User::factory()->create(['role' => UserRole::STUDENT, 'is_active' => true, 'student_id' => $this->student->id]);
        $this->studentUser2 = User::factory()->create(['role' => UserRole::STUDENT, 'is_active' => true, 'student_id' => $this->student2->id]);
        $this->studentUser3 = User::factory()->create(['role' => UserRole::STUDENT, 'is_active' => true, 'student_id' => $this->student3->id]);
    }

    protected function makeReg(Student $s, Extracurricular $e, string $status, ?AcademicYear $y = null): ExtracurricularRegistration
    {
        return ExtracurricularRegistration::create([
            'student_id' => $s->id,
            'extracurricular_id' => $e->id,
            'academic_year_id' => ($y ?? $this->year)->id,
            'status' => $status,
            'submitted_at' => now()->subDays(5),
        ]);
    }

    protected function makeInvoice(ExtracurricularRegistration $reg, string $status, int $amount, ?string $issuedAt = null): ExtracurricularInvoice
    {
        $this->invSeq++;
        return ExtracurricularInvoice::create([
            'registration_id' => $reg->id,
            'invoice_number' => 'INV-TEST-'.$reg->id.'-'.$this->invSeq.'-'.uniqid(),
            'amount' => $amount,
            'issued_at' => $issuedAt ?? now()->toDateTimeString(),
            'status' => $status,
            'paid_at' => $status === 'paid' ? now()->toDateTimeString() : null,
        ]);
    }

    protected function makeSession(Extracurricular $e, string $status, string $date, ?AcademicYear $y = null): ExtracurricularSession
    {
        return ExtracurricularSession::create([
            'extracurricular_id' => $e->id,
            'academic_year_id' => ($y ?? $this->year)->id,
            'venue_id' => $this->venue->id,
            'session_date' => $date,
            'start_time' => '15:00:00',
            'end_time' => '17:00:00',
            'status' => $status,
            'topic' => 'Latihan',
            'created_by' => $this->adminUser->id,
        ]);
    }

    protected function makeAttendance(ExtracurricularSession $sess, Student $s, ExtracurricularRegistration $reg, string $status): StudentAttendance
    {
        return StudentAttendance::create([
            'session_id' => $sess->id,
            'student_id' => $s->id,
            'registration_id' => $reg->id,
            'status' => $status,
            'recorded_by' => $this->adminUser->id,
            'recorded_at' => now()->toDateTimeString(),
        ]);
    }

    protected function makeEval(Student $s, ExtracurricularRegistration $reg, Extracurricular $e, string $status, ?float $final, string $period = 'midterm', ?string $evaluatedAt = null): ExtracurricularEvaluation
    {
        return ExtracurricularEvaluation::create([
            'student_id' => $s->id,
            'registration_id' => $reg->id,
            'extracurricular_id' => $e->id,
            'academic_year_id' => $this->year->id,
            'evaluator_id' => $this->coachUser->id,
            'evaluation_period' => $period,
            'attendance_score' => 80,
            'activity_score' => 80,
            'skill_score' => 80,
            'discipline_score' => 80,
            'final_score' => $final,
            'status' => $status,
            'evaluated_at' => $status === 'published' ? ($evaluatedAt ?? now()->toDateTimeString()) : null,
        ]);
    }

    // 1. admin KPI structure
    public function test_admin_dashboard_structure(): void
    {
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard');
        $res->assertStatus(200)->assertJson(['success' => true]);
        $d = $res->json('data');
        $this->assertArrayHasKey('academic_year', $d);
        $this->assertArrayHasKey('students', $d);
        $this->assertArrayHasKey('membership', $d);
        $this->assertArrayHasKey('payments', $d);
        $this->assertArrayHasKey('attendance', $d);
        $this->assertArrayHasKey('evaluations', $d);
        $this->assertArrayHasKey('attendance_percentage', $d['attendance']);
        $this->assertArrayHasKey('billed', $d['payments']);
        $this->assertArrayHasKey('outstanding', $d['payments']);
    }

    // 2. super_admin ok
    public function test_super_admin_can_access_admin_dashboard(): void
    {
        $this->actingAs($this->superAdminUser, 'sanctum')->getJson('/api/v1/admin/dashboard')->assertStatus(200);
    }

    // 3. coach denied admin
    public function test_coach_denied_admin_dashboard(): void
    {
        $this->actingAs($this->coachUser, 'sanctum')->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    }

    // 4. student denied admin
    public function test_student_denied_admin_dashboard(): void
    {
        $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    }

    // 5. year filter isolates
    public function test_year_filter_isolates_membership(): void
    {
        $this->makeReg($this->student, $this->ekskul, 'active');
        $this->makeReg($this->student2, $this->ekskulOtherYear, 'active', $this->year2);
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year->id);
        $res->assertStatus(200);
        $this->assertEquals(1, $res->json('data.membership.active'));
        $res2 = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year2->id);
        $this->assertEquals(1, $res2->json('data.membership.active'));
    }

    // 6. ekskul filter
    public function test_ekskul_filter(): void
    {
        $this->makeReg($this->student, $this->ekskul, 'active');
        $this->makeReg($this->student2, $this->ekskul2, 'active');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year->id.'&extracurricular_id='.$this->ekskul->id);
        $res->assertStatus(200);
        $this->assertEquals(1, $res->json('data.membership.active'));
    }

    // 7. ACTIVE count
    public function test_active_members_count(): void
    {
        $this->makeReg($this->student, $this->ekskul, 'active');
        $this->makeReg($this->student2, $this->ekskul, 'submitted');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year->id);
        $this->assertEquals(1, $res->json('data.membership.active_members'));
        $this->assertEquals(1, $res->json('data.membership.active'));
    }

    // 8. rejected/cancelled not active
    public function test_rejected_cancelled_not_active(): void
    {
        $this->makeReg($this->student, $this->ekskul, 'rejected');
        $this->makeReg($this->student2, $this->ekskul2, 'cancelled');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year->id);
        $this->assertEquals(0, $res->json('data.membership.active'));
        $this->assertEquals(1, $res->json('data.membership.rejected'));
        $this->assertEquals(1, $res->json('data.membership.cancelled'));
    }

    // 9. breakdown correct
    public function test_membership_breakdown(): void
    {
        $this->makeReg($this->student, $this->ekskul, 'submitted');
        $this->makeReg($this->student2, $this->ekskul2, 'approved');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/reports/membership?academic_year_id='.$this->year->id);
        $res->assertStatus(200);
        $s = $res->json('data.summary');
        $this->assertEquals(1, $s['submitted']);
        $this->assertEquals(1, $s['approved']);
        $this->assertEquals(2, $s['total']);
    }

    // 10. billed/paid/outstanding defs
    public function test_payment_billed_paid_outstanding(): void
    {
        $r1 = $this->makeReg($this->student, $this->ekskul, 'active');
        $r2 = $this->makeReg($this->student2, $this->ekskul2, 'active');
        $this->makeInvoice($r1, 'paid', 250000);
        $this->makeInvoice($r2, 'unpaid', 100000);
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year->id);
        $p = $res->json('data.payments');
        $this->assertEquals(350000, $p['billed']);
        $this->assertEquals(250000, $p['paid']);
        $this->assertEquals(100000, $p['outstanding']);
    }

    // 11. rejected proofs not paid
    public function test_rejected_invoice_not_paid(): void
    {
        $r = $this->makeReg($this->student, $this->ekskul, 'active');
        $this->makeInvoice($r, 'rejected', 250000);
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year->id);
        $p = $res->json('data.payments');
        $this->assertEquals(250000, $p['billed']);
        $this->assertEquals(0, $p['paid']);
        $this->assertEquals(250000, $p['outstanding']);
        $this->assertEquals(1, $p['rejected']);
    }

    // 12. payment filters by issued_at
    public function test_payment_date_filter(): void
    {
        $r = $this->makeReg($this->student, $this->ekskul, 'active');
        $this->makeInvoice($r, 'paid', 250000, '2026-08-01 10:00:00');
        $this->makeInvoice($r, 'paid', 250000, '2026-10-15 10:00:00');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/reports/payments?academic_year_id='.$this->year->id.'&date_from=2026-10-01&date_to=2026-10-31');
        $this->assertEquals(1, $res->json('meta.total'));
        $this->assertEquals(250000, $res->json('data.summary.billed'));
    }

    // 13. session counts
    public function test_session_counts(): void
    {
        $this->makeSession($this->ekskul, 'scheduled', '2026-09-10');
        $this->makeSession($this->ekskul, 'completed', '2026-09-11');
        $this->makeSession($this->ekskul, 'cancelled', '2026-09-12');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year->id);
        $s = $res->json('data.attendance.sessions');
        $this->assertEquals(1, $s['scheduled']);
        $this->assertEquals(1, $s['completed']);
        $this->assertEquals(1, $s['cancelled']);
    }

    // 14. attendance counts
    public function test_attendance_counts(): void
    {
        $r = $this->makeReg($this->student, $this->ekskul, 'active');
        $r2 = $this->makeReg($this->student2, $this->ekskul, 'active');
        $sess = $this->makeSession($this->ekskul, 'completed', '2026-09-10');
        $this->makeAttendance($sess, $this->student, $r, 'hadir');
        $this->makeAttendance($sess, $this->student2, $r2, 'izin');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year->id);
        $rec = $res->json('data.attendance.records');
        $this->assertEquals(1, $rec['hadir']);
        $this->assertEquals(1, $rec['izin']);
        $this->assertEquals(2, $rec['total']);
    }

    // 15. cancelled excluded
    public function test_cancelled_session_attendance_excluded(): void
    {
        $r = $this->makeReg($this->student, $this->ekskul, 'active');
        $sess = $this->makeSession($this->ekskul, 'cancelled', '2026-09-10');
        $this->makeAttendance($sess, $this->student, $r, 'hadir');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year->id);
        $this->assertEquals(0, $res->json('data.attendance.records.total'));
    }

    // 16. percentage formula
    public function test_attendance_percentage_formula(): void
    {
        $r = $this->makeReg($this->student, $this->ekskul, 'active');
        $r2 = $this->makeReg($this->student2, $this->ekskul, 'active');
        $r3 = $this->makeReg($this->student3, $this->ekskul2, 'active');
        $sess = $this->makeSession($this->ekskul, 'completed', '2026-09-10');
        $this->makeAttendance($sess, $this->student, $r, 'hadir');
        $this->makeAttendance($sess, $this->student2, $r2, 'alpa');
        $this->makeAttendance($sess, $this->student3, $r3, 'sakit');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year->id);
        // 1 hadir / 3 total = 33.33
        $this->assertEquals(33.33, $res->json('data.attendance.attendance_percentage'));
    }

    // 17. student dashboard own only
    public function test_student_dashboard_own_only(): void
    {
        $r = $this->makeReg($this->student, $this->ekskul, 'active');
        $r2 = $this->makeReg($this->student2, $this->ekskul2, 'active');
        $this->makeInvoice($r, 'unpaid', 250000);
        $this->makeInvoice($r2, 'paid', 100000);
        $res = $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/student/dashboard?academic_year_id='.$this->year->id);
        $res->assertStatus(200);
        $this->assertEquals((int) $this->student->id, $res->json('data.student_id'));
        $this->assertEquals(250000, $res->json('data.payments.billed'));
        $this->assertEquals(1, $res->json('data.membership.active_count'));
    }

    // 18. coach assigned only
    public function test_coach_dashboard_assigned_only(): void
    {
        $this->makeReg($this->student, $this->ekskul, 'active');
        $this->makeReg($this->student2, $this->ekskul2, 'active');
        $res = $this->actingAs($this->coachUser, 'sanctum')->getJson('/api/v1/coach/dashboard?academic_year_id='.$this->year->id);
        $res->assertStatus(200);
        $this->assertEquals(1, $res->json('data.assigned_count'));
        $this->assertEquals(1, $res->json('data.membership.active'));
    }

    // 19. published count
    public function test_published_eval_count(): void
    {
        $r = $this->makeReg($this->student, $this->ekskul, 'active');
        $this->makeEval($this->student, $r, $this->ekskul, 'published', 85.0);
        $this->makeEval($this->student, $r, $this->ekskul, 'draft', 70.0, 'final');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year->id);
        $this->assertEquals(1, $res->json('data.evaluations.published'));
        $this->assertEquals(1, $res->json('data.evaluations.draft'));
    }

    // 20. drafts excluded student dashboard
    public function test_student_dashboard_excludes_drafts(): void
    {
        $r = $this->makeReg($this->student, $this->ekskul, 'active');
        $this->makeEval($this->student, $r, $this->ekskul, 'published', 85.0);
        $this->makeEval($this->student, $r, $this->ekskul, 'draft', 70.0, 'final');
        $res = $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/student/dashboard?academic_year_id='.$this->year->id);
        $this->assertEquals(1, $res->json('data.evaluations.published_count'));
    }

    // 21. avg final published
    public function test_avg_final_published_only(): void
    {
        $r = $this->makeReg($this->student, $this->ekskul, 'active');
        $r2 = $this->makeReg($this->student2, $this->ekskul, 'active');
        $this->makeEval($this->student, $r, $this->ekskul, 'published', 80.0);
        $this->makeEval($this->student2, $r2, $this->ekskul, 'published', 90.0, 'final');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year->id);
        $this->assertEquals(85.0, $res->json('data.evaluations.avg_final'));
    }

    // 22. eval report breakdown + pagination
    public function test_eval_report_pagination(): void
    {
        $r = $this->makeReg($this->student, $this->ekskul, 'active');
        $this->makeEval($this->student, $r, $this->ekskul, 'published', 80.0);
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/reports/evaluations?academic_year_id='.$this->year->id.'&per_page=15');
        $res->assertStatus(200);
        $this->assertEquals(15, $res->json('meta.per_page'));
        $this->assertEquals(1, $res->json('meta.total'));
    }

    // 23. student cannot query other (no student_id param honored)
    public function test_student_dashboard_ignores_student_id_param(): void
    {
        $this->makeReg($this->student, $this->ekskul, 'active');
        $this->makeReg($this->student2, $this->ekskul2, 'active');
        $res = $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/student/dashboard?academic_year_id='.$this->year->id.'&student_id='.$this->student2->id);
        $res->assertStatus(200);
        $this->assertEquals((int) $this->student->id, $res->json('data.student_id'));
        $this->assertEquals(1, $res->json('data.membership.active_count'));
    }

    // 24. coach unauthorized ekskul filter 404
    public function test_coach_unauthorized_ekskul_filter_404(): void
    {
        $res = $this->actingAs($this->coachUser, 'sanctum')->getJson('/api/v1/coach/dashboard?academic_year_id='.$this->year->id.'&extracurricular_id='.$this->ekskul2->id);
        $this->assertTrue(in_array($res->status(), [403, 404]));
    }

    // 25. empty safe zeros
    public function test_empty_safe_zeros(): void
    {
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/dashboard?academic_year_id='.$this->year->id);
        $res->assertStatus(200);
        $d = $res->json('data');
        $this->assertEquals(0, $d['payments']['billed']);
        $this->assertEquals(0, $d['payments']['outstanding']);
        $this->assertEquals(0.0, (float) $d['attendance']['attendance_percentage']);
        $this->assertNull($d['evaluations']['avg_final']);
    }

    // 26. no div0 percentage
    public function test_no_div0_attendance(): void
    {
        $res = $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/student/dashboard?academic_year_id='.$this->year->id);
        $res->assertStatus(200);
        $p = $res->json('data.attendance.attendance_percentage');
        $this->assertFalse(is_nan((float) $p));
        $this->assertEquals(0.0, (float) $p);
    }

    // 27. membership report pagination default 15 max 100
    public function test_membership_pagination_limits(): void
    {
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/reports/membership?per_page=200');
        $this->assertLessThanOrEqual(100, $res->json('meta.per_page'));
        $res2 = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/reports/membership');
        $this->assertEquals(15, $res2->json('meta.per_page'));
    }

    // 28. extracurriculars report
    public function test_extracurriculars_report(): void
    {
        $this->makeReg($this->student, $this->ekskul, 'active');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/reports/extracurriculars?academic_year_id='.$this->year->id);
        $res->assertStatus(200);
        $items = $res->json('data.items');
        $this->assertNotEmpty($items);
        $this->assertArrayHasKey('report', $items[0]);
    }

    // 29. unauthenticated denied
    public function test_unauthenticated_denied_reporting(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/coach/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/student/dashboard')->assertStatus(401);
    }

    // 30. student denied coach dashboard + coach denied student dashboard
    public function test_cross_role_denied(): void
    {
        $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/coach/dashboard')->assertStatus(403);
        $this->actingAs($this->coachUser, 'sanctum')->getJson('/api/v1/student/dashboard')->assertStatus(403);
    }

    // 31. admin denied coach/student dashboards
    public function test_admin_denied_scoped_dashboards(): void
    {
        $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/coach/dashboard')->assertStatus(403);
        $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/student/dashboard')->assertStatus(403);
    }

    // 32. attendance date filter uses session_date
    public function test_attendance_date_filter_session_date(): void
    {
        $r = $this->makeReg($this->student, $this->ekskul, 'active');
        $s1 = $this->makeSession($this->ekskul, 'completed', '2026-08-01');
        $s2 = $this->makeSession($this->ekskul, 'completed', '2026-10-15');
        $this->makeAttendance($s1, $this->student, $r, 'hadir');
        $this->makeAttendance($s2, $this->student, $r, 'hadir');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/reports/attendance?academic_year_id='.$this->year->id.'&date_from=2026-10-01&date_to=2026-10-31');
        $this->assertEquals(1, $res->json('meta.total'));
    }

    // 33. eval date filter uses evaluated_at
    public function test_eval_date_filter(): void
    {
        $r = $this->makeReg($this->student, $this->ekskul, 'active');
        $this->makeEval($this->student, $r, $this->ekskul, 'published', 80.0, 'midterm', '2026-08-01 10:00:00');
        $this->makeEval($this->student, $r, $this->ekskul, 'published', 90.0, 'final', '2026-10-15 10:00:00');
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/reports/evaluations?academic_year_id='.$this->year->id.'&date_from=2026-10-01&date_to=2026-10-31');
        $this->assertEquals(1, $res->json('meta.total'));
    }

    // 34. coach dashboard empty safe
    public function test_coach_dashboard_no_assignment_safe(): void
    {
        $coach3 = Coach::create(['employee_number' => 'EMP3', 'name' => 'Coach C', 'is_active' => true]);
        $cu3 = User::factory()->create(['role' => UserRole::COACH, 'is_active' => true, 'coach_id' => $coach3->id]);
        $res = $this->actingAs($cu3, 'sanctum')->getJson('/api/v1/coach/dashboard?academic_year_id='.$this->year->id);
        $res->assertStatus(200);
        $this->assertEquals(0, $res->json('data.assigned_count'));
    }
}
