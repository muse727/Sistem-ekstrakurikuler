<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Gender;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Coach;
use App\Models\Extracurricular;
use App\Models\ExtracurricularRegistration;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use App\Services\EvaluationService;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;
    protected User $coachUser;
    protected User $studentUser;
    protected User $studentUser2;
    protected Student $student;
    protected Student $student2;
    protected Coach $coach;
    protected AcademicYear $academicYear;
    protected Extracurricular $ekskul;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'student_number' => '2026001', 'nisn' => '0081234567',
            'name' => 'Ahmad Rizky', 'gender' => Gender::MALE,
            'class_name' => 'X-IPA-1', 'is_active' => true,
        ]);
        $this->student2 = Student::create([
            'student_number' => '2026002', 'nisn' => '0087654321',
            'name' => 'Siti Nurhaliza', 'gender' => Gender::FEMALE,
            'class_name' => 'X-IPA-2', 'is_active' => true,
        ]);

        $this->coach = Coach::create(['employee_number' => 'EMP1', 'name' => 'Coach A', 'is_active' => true]);

        $this->ekskul = Extracurricular::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Basket', 'code' => 'BSK', 'description' => 'Basket club',
            'fee_amount' => 250000, 'quota' => 30, 'is_active' => true,
        ]);
        $this->ekskul->coaches()->attach([$this->coach->id => ['role' => 'primary']]);

        $this->adminUser = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->superAdminUser = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $this->coachUser = User::factory()->create(['role' => UserRole::COACH, 'is_active' => true, 'coach_id' => $this->coach->id]);
        $this->studentUser = User::factory()->create(['role' => UserRole::STUDENT, 'is_active' => true, 'student_id' => $this->student->id]);
        $this->studentUser2 = User::factory()->create(['role' => UserRole::STUDENT, 'is_active' => true, 'student_id' => $this->student2->id]);
    }

    protected function makeSubmittedRegistration(?Student $student = null): ExtracurricularRegistration
    {
        return ExtracurricularRegistration::create([
            'student_id' => ($student ?? $this->student)->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
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

    protected function makeActiveRegistration(?Student $student = null): ExtracurricularRegistration
    {
        return ExtracurricularRegistration::create([
            'student_id' => ($student ?? $this->student)->id,
            'extracurricular_id' => $this->ekskul->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'active',
            'submitted_at' => now()->subDays(5),
            'approved_at' => now()->subDays(4),
        ]);
    }

    protected function approveAndGetInvoice(ExtracurricularRegistration $reg): \App\Models\ExtracurricularInvoice
    {
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/registrations/{$reg->id}/approve")->assertStatus(200);

        return \App\Models\ExtracurricularInvoice::where('registration_id', $reg->id)->firstOrFail();
    }

    // ===== 8 creation =====

    public function test_registration_submit_notifies_admins(): void
    {
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/registrations', ['extracurricular_id' => $this->ekskul->id])
            ->assertStatus(201);

        $reg = ExtracurricularRegistration::firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminUser->id,
            'type' => NotificationType::REGISTRATION_SUBMITTED->value,
            'reference_type' => 'registration',
            'reference_id' => $reg->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->superAdminUser->id,
            'type' => NotificationType::REGISTRATION_SUBMITTED->value,
            'reference_type' => 'registration',
            'reference_id' => $reg->id,
        ]);
    }

    public function test_registration_approve_notifies_student(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/registrations/{$reg->id}/approve")->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->studentUser->id,
            'type' => NotificationType::REGISTRATION_APPROVED->value,
            'reference_type' => 'registration',
            'reference_id' => $reg->id,
        ]);
    }

    public function test_registration_reject_notifies_student(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/registrations/{$reg->id}/reject", ['rejection_reason' => 'Kuota penuh'])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->studentUser->id,
            'type' => NotificationType::REGISTRATION_REJECTED->value,
            'reference_type' => 'registration',
            'reference_id' => $reg->id,
        ]);
    }

    public function test_payment_proof_submit_notifies_admins(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $invoice = $this->approveAndGetInvoice($reg);

        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$invoice->id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminUser->id,
            'type' => NotificationType::PAYMENT_PROOF_SUBMITTED->value,
            'reference_type' => 'invoice',
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_payment_proof_approve_notifies_student(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $invoice = $this->approveAndGetInvoice($reg);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$invoice->id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])
            ->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/payments/{$invoice->id}/verify")->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->studentUser->id,
            'type' => NotificationType::PAYMENT_PROOF_APPROVED->value,
            'reference_type' => 'invoice',
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_payment_proof_reject_notifies_student(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $invoice = $this->approveAndGetInvoice($reg);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$invoice->id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])
            ->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/payments/{$invoice->id}/reject", ['rejection_reason' => 'Bukti buram'])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->studentUser->id,
            'type' => NotificationType::PAYMENT_PROOF_REJECTED->value,
            'reference_type' => 'invoice',
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_evaluation_publish_notifies_student(): void
    {
        $reg = $this->makeActiveRegistration();
        $svc = app(EvaluationService::class);
        $draft = $svc->createDraft($this->coachUser, $reg->id, 'midterm', $this->fullScores());
        $svc->publish($this->coachUser, $draft);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->studentUser->id,
            'type' => NotificationType::EVALUATION_PUBLISHED->value,
            'reference_type' => 'evaluation',
            'reference_id' => $draft->id,
        ]);
    }

    public function test_draft_evaluation_creates_no_notification(): void
    {
        $reg = $this->makeActiveRegistration();
        $svc = app(EvaluationService::class);
        $svc->createDraft($this->coachUser, $reg->id, 'midterm', []);
        $this->assertDatabaseCount('notifications', 0);
    }

    // ===== 4 ownership =====

    public function test_list_only_own_notifications(): void
    {
        $svc = app(NotificationService::class);
        $svc->notifyUser($this->studentUser->id, NotificationType::REGISTRATION_APPROVED, 'A', 'msg A', 'registration', 1);
        $svc->notifyUser($this->studentUser2->id, NotificationType::REGISTRATION_APPROVED, 'B', 'msg B', 'registration', 1);

        $res = $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/notifications');
        $res->assertStatus(200);
        $titles = collect($res->json('data'))->pluck('title')->all();
        $this->assertContains('A', $titles);
        $this->assertNotContains('B', $titles);
    }

    public function test_unread_filter_only_unread(): void
    {
        $svc = app(NotificationService::class);
        $svc->notifyUser($this->studentUser->id, NotificationType::REGISTRATION_APPROVED, 'A', 'msg', 'registration', 10);
        $svc->notifyUser($this->studentUser->id, NotificationType::REGISTRATION_APPROVED, 'B', 'msg', 'registration', 11);
        $first = Notification::where('user_id', $this->studentUser->id)->where('reference_id', 10)->firstOrFail();
        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/v1/notifications/{$first->id}/read")->assertStatus(200);

        $res = $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/notifications?unread=1');
        $res->assertStatus(200);
        $titles = collect($res->json('data'))->pluck('title')->all();
        $this->assertContains('B', $titles);
        $this->assertNotContains('A', $titles);
    }

    public function test_mark_read_other_user_returns_404(): void
    {
        $svc = app(NotificationService::class);
        $svc->notifyUser($this->studentUser2->id, NotificationType::REGISTRATION_APPROVED, 'X', 'msg', 'registration', 99);
        $other = Notification::where('user_id', $this->studentUser2->id)->firstOrFail();

        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/notifications/{$other->id}/read")->assertStatus(404);
    }

    public function test_unread_count_only_own(): void
    {
        $svc = app(NotificationService::class);
        $svc->notifyUser($this->studentUser->id, NotificationType::REGISTRATION_APPROVED, 'A', 'msg', 'registration', 20);
        $svc->notifyUser($this->studentUser2->id, NotificationType::REGISTRATION_APPROVED, 'B', 'msg', 'registration', 21);

        $res = $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/notifications/unread-count');
        $res->assertStatus(200)->assertJsonPath('data.unread_count', 1);
    }

    // ===== 4 lifecycle =====

    public function test_index_paginate_shape(): void
    {
        $svc = app(NotificationService::class);
        $svc->notifyUser($this->studentUser->id, NotificationType::REGISTRATION_APPROVED, 'A', 'msg', 'registration', 30);

        $res = $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/notifications');
        $res->assertStatus(200)->assertJsonStructure(['success', 'message', 'data', 'meta', 'links']);
        $item = $res->json('data.0');
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('type', $item);
        $this->assertArrayHasKey('title', $item);
        $this->assertArrayHasKey('message', $item);
        $this->assertArrayHasKey('reference_type', $item);
        $this->assertArrayHasKey('reference_id', $item);
        $this->assertArrayHasKey('read_at', $item);
        $this->assertArrayHasKey('created_at', $item);
    }

    public function test_unread_count_decrements_after_read(): void
    {
        $svc = app(NotificationService::class);
        $svc->notifyUser($this->studentUser->id, NotificationType::REGISTRATION_APPROVED, 'A', 'msg', 'registration', 40);
        $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 1);

        $n = Notification::where('user_id', $this->studentUser->id)->firstOrFail();
        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/v1/notifications/{$n->id}/read")->assertStatus(200);
        $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
    }

    public function test_mark_all_read(): void
    {
        $svc = app(NotificationService::class);
        $svc->notifyUser($this->studentUser->id, NotificationType::REGISTRATION_APPROVED, 'A', 'msg', 'registration', 50);
        $svc->notifyUser($this->studentUser->id, NotificationType::REGISTRATION_APPROVED, 'B', 'msg', 'registration', 51);

        $this->actingAs($this->studentUser, 'sanctum')->postJson('/api/v1/notifications/read-all')->assertStatus(200);
        $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
    }

    public function test_mark_read_idempotent(): void
    {
        $svc = app(NotificationService::class);
        $svc->notifyUser($this->studentUser->id, NotificationType::REGISTRATION_APPROVED, 'A', 'msg', 'registration', 60);
        $n = Notification::where('user_id', $this->studentUser->id)->firstOrFail();

        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/v1/notifications/{$n->id}/read")->assertStatus(200);
        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/v1/notifications/{$n->id}/read")->assertStatus(200);
        $this->assertEquals(1, Notification::where('user_id', $this->studentUser->id)->count());
    }

    // ===== 3 security =====

    public function test_guest_cannot_access_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
        $this->getJson('/api/v1/notifications/unread-count')->assertStatus(401);
        $this->postJson('/api/v1/notifications/1/read')->assertStatus(401);
        $this->postJson('/api/v1/notifications/read-all')->assertStatus(401);
    }

    public function test_no_user_id_param_leak(): void
    {
        $svc = app(NotificationService::class);
        $svc->notifyUser($this->studentUser2->id, NotificationType::REGISTRATION_APPROVED, 'B', 'msg', 'registration', 70);

        $res = $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/notifications?user_id='.$this->studentUser2->id);
        $res->assertStatus(200);
        $titles = collect($res->json('data'))->pluck('title')->all();
        $this->assertNotContains('B', $titles);
    }

    public function test_no_post_create_route(): void
    {
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/notifications', ['title' => 'x'])->assertStatus(405);
    }

    // ===== duplicate protection =====

    public function test_duplicate_notify_does_not_duplicate(): void
    {
        $svc = app(NotificationService::class);
        $svc->notifyUser($this->studentUser->id, NotificationType::REGISTRATION_APPROVED, 'A', 'msg', 'registration', 80);
        $svc->notifyUser($this->studentUser->id, NotificationType::REGISTRATION_APPROVED, 'A', 'msg', 'registration', 80);

        $this->assertEquals(1, Notification::where('user_id', $this->studentUser->id)->where('reference_id', 80)->count());
    }

    public function test_double_approve_flow_does_not_duplicate_admin_notif(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $svc = app(NotificationService::class);
        $reg->loadMissing(['student', 'extracurricular']);
        $svc->notifyRegistrationSubmitted($reg);
        $svc->notifyRegistrationSubmitted($reg);

        $this->assertEquals(1, Notification::where('user_id', $this->adminUser->id)
            ->where('type', NotificationType::REGISTRATION_SUBMITTED->value)
            ->where('reference_id', $reg->id)->count());
    }
}
