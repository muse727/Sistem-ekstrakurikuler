<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Coach;
use App\Models\Extracurricular;
use App\Models\ExtracurricularRegistration;
use App\Models\ExtracurricularSession;
use App\Models\Student;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

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

        $this->adminUser = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->superAdminUser = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $this->coachUser = User::factory()->create(['role' => UserRole::COACH, 'is_active' => true, 'coach_id' => $this->coach->id]);
        $this->coachUser2 = User::factory()->create(['role' => UserRole::COACH, 'is_active' => true, 'coach_id' => $this->coach2->id]);
        $this->studentUser = User::factory()->create(['role' => UserRole::STUDENT, 'is_active' => true, 'student_id' => $this->student->id]);
        $this->studentUser2 = User::factory()->create(['role' => UserRole::STUDENT, 'is_active' => true, 'student_id' => $this->student2->id]);
    }

    protected function makeRegistration(Student $student, Extracurricular $ekskul, string $status = 'active'): ExtracurricularRegistration
    {
        return ExtracurricularRegistration::create([
            'student_id' => $student->id,
            'extracurricular_id' => $ekskul->id,
            'academic_year_id' => $ekskul->academic_year_id,
            'status' => $status,
            'submitted_at' => now()->subDays(5),
            'approved_at' => now()->subDays(4),
        ]);
    }

    protected function makeSession(string $status = 'scheduled', ?Venue $venue = null): ExtracurricularSession
    {
        return ExtracurricularSession::create([
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'venue_id' => ($venue ?? $this->venue)->id,
            'session_date' => now()->toDateString(),
            'start_time' => '15:00:00',
            'end_time' => '17:00:00',
            'status' => $status,
            'topic' => 'Latihan',
            'created_by' => $this->adminUser->id,
        ]);
    }

    protected function checkInPayload(float $lat, float $lng, int $accuracy = 10): array
    {
        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy_meters' => $accuracy,
            'device_captured_at' => now()->toISOString(),
            'photo' => UploadedFile::fake()->create('selfie.jpg', 100, 'image/jpeg'),
        ];
    }

    // 1-6 session lifecycle
    public function test_admin_can_create_session(): void
    {
        $res = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/v1/admin/sessions', [
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'venue_id' => $this->venue->id,
            'session_date' => now()->toDateString(),
            'start_time' => '15:00',
            'end_time' => '17:00',
            'topic' => 'Latihan',
        ]);
        $res->assertStatus(201)->assertJsonPath('data.status', 'scheduled');
    }

    public function test_invalid_session_time_rejected(): void
    {
        $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/v1/admin/sessions', [
            'extracurricular_id' => $this->ekskul->id,
            'venue_id' => $this->venue->id,
            'session_date' => now()->toDateString(),
            'start_time' => '17:00',
            'end_time' => '15:00',
        ])->assertStatus(422);
    }

    public function test_admin_can_open_complete_cancel(): void
    {
        $s = $this->makeSession('scheduled');
        $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/sessions/{$s->id}/open")->assertStatus(200)->assertJsonPath('data.status', 'open');
        $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/sessions/{$s->id}/complete")->assertStatus(200)->assertJsonPath('data.status', 'completed');
        $s2 = $this->makeSession('scheduled');
        $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/sessions/{$s2->id}/cancel")->assertStatus(200)->assertJsonPath('data.status', 'cancelled');
    }

    public function test_invalid_session_transitions_rejected(): void
    {
        $s = $this->makeSession('scheduled');
        $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/sessions/{$s->id}/complete")->assertStatus(422);
        $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/sessions/{$s->id}/open")->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/sessions/{$s->id}/open")->assertStatus(422);
    }

    // 7-11 authz
    public function test_assigned_coach_can_view_session(): void
    {
        $s = $this->makeSession('open');
        $this->actingAs($this->coachUser, 'sanctum')->getJson("/api/v1/coach/sessions/{$s->id}")->assertStatus(200);
    }

    public function test_unassigned_coach_cannot_check_in(): void
    {
        $s = $this->makeSession('open');
        $this->actingAs($this->coachUser2, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))
            ->assertStatus(403);
    }

    public function test_unassigned_coach_cannot_record_attendance(): void
    {
        $s = $this->makeSession('open');
        $this->makeRegistration($this->student, $this->ekskul);
        $this->actingAs($this->coachUser, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))
            ->assertStatus(201);
        $this->actingAs($this->coachUser2, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/attendance", ['attendance' => [['student_id' => $this->student->id, 'status' => 'hadir']]])
            ->assertStatus(403);
    }

    public function test_student_cannot_check_in(): void
    {
        $s = $this->makeSession('open');
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))
            ->assertStatus(403);
    }

    public function test_admin_can_inspect_attendance(): void
    {
        $s = $this->makeSession('open');
        $this->actingAs($this->adminUser, 'sanctum')->getJson("/api/v1/admin/sessions/{$s->id}/attendance")->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')->getJson("/api/v1/admin/sessions/{$s->id}/check-ins")->assertStatus(200);
    }

    // 12-27 check-in
    public function test_check_in_requires_open(): void
    {
        $s = $this->makeSession('scheduled');
        $this->actingAs($this->coachUser, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))
            ->assertStatus(422);
    }

    public function test_check_in_requires_venue(): void
    {
        $s = ExtracurricularSession::create([
            'extracurricular_id' => $this->ekskul->id, 'academic_year_id' => $this->academicYear->id,
            'venue_id' => null, 'session_date' => now()->toDateString(),
            'start_time' => '15:00:00', 'status' => 'open', 'created_by' => $this->adminUser->id,
        ]);
        $this->actingAs($this->coachUser, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))
            ->assertStatus(422);
    }

    public function test_invalid_lat_lng_accuracy_rejected(): void
    {
        $s = $this->makeSession('open');
        $this->actingAs($this->coachUser, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(200, 106.8456))
            ->assertStatus(422);
        $this->actingAs($this->coachUser, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 500))
            ->assertStatus(422);
        $payload = $this->checkInPayload(-6.2088, 106.8456);
        unset($payload['accuracy_meters']);
        $this->actingAs($this->coachUser, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $payload)
            ->assertStatus(422);
    }

    public function test_outside_radius_rejected_and_inside_succeeds(): void
    {
        $s = $this->makeSession('open');
        $this->actingAs($this->coachUser, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(0, 0))
            ->assertStatus(422);
        $s2 = $this->makeSession('open');
        $res = $this->actingAs($this->coachUser, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s2->id}/check-in", $this->checkInPayload(-6.2088, 106.8456));
        $res->assertStatus(201)->assertJsonPath('data.status', 'present');
        $this->assertNotNull($res->json('data.distance_from_venue_meters'));
        $this->assertNotNull($res->json('data.server_received_at'));
    }

    public function test_client_distance_and_timestamp_ignored(): void
    {
        $s = $this->makeSession('open');
        $payload = $this->checkInPayload(-6.2088, 106.8456);
        $payload['distance_from_venue_meters'] = 0;
        $payload['server_received_at'] = '2000-01-01T00:00:00Z';
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $payload);
        $res->assertStatus(201);
        $this->assertNotEquals('2000-01-01T00:00:00Z', $res->json('data.server_received_at'));
        $this->assertDatabaseHas('coach_check_ins', ['session_id' => $s->id, 'coach_id' => $this->coach->id]);
    }

    public function test_photo_required_and_validated(): void
    {
        $s = $this->makeSession('open');
        $payload = $this->checkInPayload(-6.2088, 106.8456);
        unset($payload['photo']);
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $payload)->assertStatus(422);
        $bad = $this->checkInPayload(-6.2088, 106.8456);
        $bad['photo'] = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $bad)->assertStatus(422);
    }

    public function test_duplicate_check_in_rejected(): void
    {
        $s = $this->makeSession('open');
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))->assertStatus(201);
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))->assertStatus(409);
    }

    public function test_check_in_photo_authz(): void
    {
        $s = $this->makeSession('open');
        $res = $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))->assertStatus(201);
        $checkInId = $res->json('data.id');
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/check-ins/{$checkInId}/photo")->assertStatus(401);
        $this->actingAs($this->studentUser, 'sanctum')->getJson("/api/v1/check-ins/{$checkInId}/photo")->assertStatus(403);
        $this->actingAs($this->coachUser2, 'sanctum')->getJson("/api/v1/check-ins/{$checkInId}/photo")->assertStatus(403);
        $this->actingAs($this->adminUser, 'sanctum')->getJson("/api/v1/check-ins/{$checkInId}/photo")->assertStatus(200);
        $this->actingAs($this->coachUser, 'sanctum')->getJson("/api/v1/check-ins/{$checkInId}/photo")->assertStatus(200);
    }

    // 28-40 attendance
    public function test_attendance_blocked_before_check_in(): void
    {
        $s = $this->makeSession('open');
        $this->makeRegistration($this->student, $this->ekskul);
        $this->actingAs($this->coachUser, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/attendance", ['attendance' => [['student_id' => $this->student->id, 'status' => 'hadir']]])
            ->assertStatus(422);
    }

    public function test_eligible_student_can_receive_attendance(): void
    {
        $s = $this->makeSession('open');
        $this->makeRegistration($this->student, $this->ekskul);
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))->assertStatus(201);
        $this->actingAs($this->coachUser, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/attendance", ['attendance' => [['student_id' => $this->student->id, 'status' => 'hadir']]])
            ->assertStatus(201);
        $this->assertDatabaseHas('student_attendances', ['session_id' => $s->id, 'student_id' => $this->student->id, 'status' => 'hadir']);
    }

    public function test_ineligible_registration_cannot_receive_attendance(): void
    {
        $s = $this->makeSession('open');
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))->assertStatus(201);
        $this->makeRegistration($this->student, $this->ekskul, 'rejected');
        $this->actingAs($this->coachUser, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/attendance", ['attendance' => [['student_id' => $this->student->id, 'status' => 'hadir']]])
            ->assertStatus(404);
    }

    public function test_other_ekskul_or_year_denied(): void
    {
        $s = $this->makeSession('open');
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))->assertStatus(201);
        $this->makeRegistration($this->student, $this->ekskul2);
        $this->actingAs($this->coachUser, 'sanctum')
            ->postJson("/api/v1/coach/sessions/{$s->id}/attendance", ['attendance' => [['student_id' => $this->student->id, 'status' => 'hadir']]])
            ->assertStatus(404);
    }

    public function test_bulk_validates_every_student_and_no_duplicates(): void
    {
        $s = $this->makeSession('open');
        $this->makeRegistration($this->student, $this->ekskul);
        $this->makeRegistration($this->student2, $this->ekskul);
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))->assertStatus(201);
        $payload = ['attendance' => [
            ['student_id' => $this->student->id, 'status' => 'hadir'],
            ['student_id' => $this->student2->id, 'status' => 'izin', 'notes' => 'Keluarga'],
        ]];
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/attendance", $payload)->assertStatus(201);
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/attendance", $payload)->assertStatus(201);
        $this->assertDatabaseCount('student_attendances', 2);
        $bad = ['attendance' => [
            ['student_id' => $this->student->id, 'status' => 'hadir'],
            ['student_id' => 999999, 'status' => 'hadir'],
        ]];
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/attendance", $bad)->assertStatus(422);
    }

    public function test_student_cannot_modify_and_can_view_own(): void
    {
        $s = $this->makeSession('open');
        $this->makeRegistration($this->student, $this->ekskul);
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))->assertStatus(201);
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/attendance", ['attendance' => [['student_id' => $this->student->id, 'status' => 'hadir']]])->assertStatus(201);
        $att = \App\Models\StudentAttendance::first();
        $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/student/attendance')->assertStatus(200);
        $this->actingAs($this->studentUser, 'sanctum')->getJson("/api/v1/student/attendance/{$att->id}")->assertStatus(200);
        $this->actingAs($this->studentUser2, 'sanctum')->getJson("/api/v1/student/attendance/{$att->id}")->assertStatus(403);
        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/attendance", ['attendance' => [['student_id' => $this->student->id, 'status' => 'hadir']]])->assertStatus(403);
    }

    public function test_admin_can_correct_and_tracks_updater(): void
    {
        $s = $this->makeSession('open');
        $this->makeRegistration($this->student, $this->ekskul);
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/check-in", $this->checkInPayload(-6.2088, 106.8456))->assertStatus(201);
        $this->actingAs($this->coachUser, 'sanctum')->postJson("/api/v1/coach/sessions/{$s->id}/attendance", ['attendance' => [['student_id' => $this->student->id, 'status' => 'hadir']]])->assertStatus(201);
        $att = \App\Models\StudentAttendance::first();
        $res = $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/sessions/{$s->id}/attendance/{$att->id}", ['status' => 'izin', 'notes' => 'Koreksi']);
        $res->assertStatus(200)->assertJsonPath('data.status', 'izin');
        $this->assertDatabaseHas('student_attendances', ['id' => $att->id, 'updated_by' => $this->adminUser->id]);
    }

    public function test_unauth_and_wrong_role(): void
    {
        $s = $this->makeSession('open');
        $this->getJson('/api/v1/admin/sessions')->assertStatus(401);
        $this->getJson('/api/v1/coach/sessions')->assertStatus(401);
        $this->getJson('/api/v1/student/attendance')->assertStatus(401);
        $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/admin/sessions')->assertStatus(403);
        $this->actingAs($this->coachUser, 'sanctum')->getJson('/api/v1/admin/sessions')->assertStatus(403);
        $this->actingAs($this->coachUser, 'sanctum')->getJson('/api/v1/student/attendance')->assertStatus(403);
    }
}
