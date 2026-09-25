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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
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

        $this->ekskul = Extracurricular::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Basket', 'code' => 'BSK', 'description' => 'Basket club',
            'fee_amount' => 250000, 'quota' => 30, 'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->coachUser = User::factory()->create(['role' => UserRole::COACH, 'is_active' => true]);
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

    protected function approveRegistration(ExtracurricularRegistration $reg): array
    {
        $res = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/registrations/{$reg->id}/approve");
        $res->assertStatus(200);
        return $res->json('data');
    }

    protected function invoiceIdFor(ExtracurricularRegistration $reg): int
    {
        $invoice = \App\Models\ExtracurricularInvoice::where('registration_id', $reg->id)->firstOrFail();
        return $invoice->id;
    }

    // 1. invoice created on approved
    public function test_invoice_created_on_approved(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $this->assertDatabaseHas('extracurricular_invoices', ['registration_id' => $reg->id, 'status' => 'unpaid', 'amount' => 250000]);
    }

    // 2. not on rejected
    public function test_no_invoice_on_rejected(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/registrations/{$reg->id}/reject", ['rejection_reason' => 'Kuota penuh ya'])
            ->assertStatus(200);
        $this->assertDatabaseCount('extracurricular_invoices', 0);
    }

    // 3. not on cancelled (cancel submitted before approve => no invoice)
    public function test_no_invoice_on_cancelled(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/student/registrations/{$reg->id}/cancel")->assertStatus(200);
        $this->assertDatabaseCount('extracurricular_invoices', 0);
    }

    // 4. snapshot amount
    public function test_snapshot_amount(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $inv = \App\Models\ExtracurricularInvoice::where('registration_id', $reg->id)->first();
        $this->assertEquals(250000, (int) $inv->amount);
    }

    // 5. fee change preserves old invoice
    public function test_fee_change_preserves_snapshot(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $this->ekskul->update(['fee_amount' => 300000]);
        $inv = \App\Models\ExtracurricularInvoice::where('registration_id', $reg->id)->first();
        $this->assertEquals(250000, (int) $inv->fresh()->amount);
    }

    // 6. unique invoice numbers
    public function test_invoice_numbers_unique(): void
    {
        $r1 = $this->makeSubmittedRegistration($this->student);
        $r2 = $this->makeSubmittedRegistration($this->student2);
        $this->approveRegistration($r1);
        $this->approveRegistration($r2);
        $nums = \App\Models\ExtracurricularInvoice::pluck('invoice_number');
        $this->assertCount(2, $nums->unique());
        foreach ($nums as $n) {
            $this->assertMatchesRegularExpression('/^INV-\d{8}-\d{6}$/', $n);
        }
    }

    // 7. duplicate prevention idempotent
    public function test_duplicate_invoice_prevented(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $svc = app(\App\Services\PaymentService::class);
        $fresh = ExtracurricularRegistration::find($reg->id);
        $a = $svc->createInvoiceForRegistration($fresh);
        $b = $svc->createInvoiceForRegistration($fresh);
        $this->assertEquals($a->id, $b->id);
        $this->assertEquals(1, \App\Models\ExtracurricularInvoice::where('registration_id', $reg->id)->whereIn('status', ['unpaid', 'pending_verification'])->count());
    }

    // 8. student list own
    public function test_student_list_own(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $res = $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/student/payments');
        $res->assertStatus(200)->assertJsonPath('data.0.registration_id', $reg->id);
        $res2 = $this->actingAs($this->studentUser2, 'sanctum')->getJson('/api/v1/student/payments');
        $res2->assertStatus(200);
        $this->assertCount(0, $res2->json('data'));
    }

    // 9. cannot view other
    public function test_cannot_view_other_invoice(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $this->actingAs($this->studentUser2, 'sanctum')->getJson("/api/v1/student/payments/{$id}")->assertStatus(403);
    }

    // 10. upload valid
    public function test_upload_valid_proof(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $file = UploadedFile::fake()->create('bukti.jpg', 100, 'image/jpeg');
        $res = $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => $file]);
        $res->assertStatus(200)->assertJsonPath('data.status', 'pending_verification');
    }

    // 11. invalid type 422
    public function test_upload_invalid_type_422(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $file = UploadedFile::fake()->create('bukti.txt', 10, 'text/plain');
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => $file])
            ->assertStatus(422);
    }

    // 12. oversize 422
    public function test_upload_oversize_422(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $file = UploadedFile::fake()->create('big.jpg', 100, 'image/jpeg');
        $file = new UploadedFile($file->path(), 'big.jpg', 'image/jpeg', 6 * 1024 * 1024, true);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => $file])
            ->assertStatus(422);
    }

    // 13. cannot upload to other
    public function test_cannot_upload_to_other_invoice(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $file = UploadedFile::fake()->create('b.jpg', 100, 'image/jpeg');
        $this->actingAs($this->studentUser2, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => $file])
            ->assertStatus(403);
    }

    // 14. upload sets pending
    public function test_upload_sets_pending(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])
            ->assertStatus(200);
        $this->assertDatabaseHas('extracurricular_invoices', ['id' => $id, 'status' => 'pending_verification']);
    }

    // 15. history preserved on resubmit
    public function test_history_preserved_on_resubmit(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $svc = app(\App\Services\PaymentService::class);
        // first rejected to allow resubmit flow
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])->assertStatus(200);
        $inv = \App\Models\ExtracurricularInvoice::find($id);
        $svc->rejectProof($inv, $this->adminUser->id, 'Bukti buram sekali tolong ulangi ya');
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('b.jpg', 100, 'image/jpeg')])->assertStatus(200);
        $this->assertEquals(2, \App\Models\PaymentProof::where('invoice_id', $id)->count());
    }

    // 16. admin view
    public function test_admin_can_view(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/payments')->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')->getJson("/api/v1/admin/payments/{$id}")->assertStatus(200);
    }

    // 17. approve -> paid
    public function test_approve_sets_paid(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])->assertStatus(200);
        $res = $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/payments/{$id}/verify");
        $res->assertStatus(200)->assertJsonPath('data.status', 'paid');
        $this->assertNotNull($res->json('data.paid_at'));
        $this->assertDatabaseHas('payment_verifications', ['status' => 'approved']);
    }

    // 18. double approve 409
    public function test_double_approve_409(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/payments/{$id}/verify")->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/payments/{$id}/verify")->assertStatus(409);
    }

    // 19. reject requires reason
    public function test_reject_requires_reason(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/payments/{$id}/reject", [])->assertStatus(422);
    }

    // 20. reject + reason
    public function test_reject_with_reason(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])->assertStatus(200);
        $res = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/payments/{$id}/reject", ['rejection_reason' => 'Bukti tidak jelas, mohon foto ulang ya']);
        $res->assertStatus(200)->assertJsonPath('data.status', 'rejected');
        $this->assertDatabaseHas('payment_verifications', ['status' => 'rejected']);
    }

    // 21. resubmit new row + pending again
    public function test_resubmit_new_row_pending(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $svc = app(\App\Services\PaymentService::class);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])->assertStatus(200);
        $svc->rejectProof(\App\Models\ExtracurricularInvoice::find($id), $this->adminUser->id, 'Bukti buram sekali tolong ulangi ya');
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('b.jpg', 100, 'image/jpeg')])->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_verification');
        $this->assertEquals(2, \App\Models\PaymentProof::where('invoice_id', $id)->count());
    }

    // 22. cannot upload after paid
    public function test_cannot_upload_after_paid(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/payments/{$id}/verify")->assertStatus(200);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('c.jpg', 100, 'image/jpeg')])->assertStatus(422);
    }

    // 23. coach denied
    public function test_coach_denied(): void
    {
        $this->actingAs($this->coachUser, 'sanctum')->getJson('/api/v1/student/payments')->assertStatus(403);
        $this->actingAs($this->coachUser, 'sanctum')->getJson('/api/v1/admin/payments')->assertStatus(403);
    }

    // 24. 401 unauth
    public function test_unauth_401(): void
    {
        $this->getJson('/api/v1/student/payments')->assertStatus(401);
        $this->getJson('/api/v1/admin/payments')->assertStatus(401);
    }

    // 25. student cannot access admin, admin cannot use student route as student-only
    public function test_role_403(): void
    {
        $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/v1/admin/payments')->assertStatus(403);
        $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/student/payments')->assertStatus(403);
    }

    // 26. concurrent verify safe (second 409)
    public function test_concurrent_verify_safe(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])->assertStatus(200);
        $svc = app(\App\Services\PaymentService::class);
        $inv = \App\Models\ExtracurricularInvoice::find($id);
        $svc->verifyProof($inv, $this->adminUser->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);
        $svc->verifyProof(\App\Models\ExtracurricularInvoice::find($id), $this->adminUser->id);
    }

    // 27. paid activates approved
    public function test_paid_activates_approved(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/payments/{$id}/verify")->assertStatus(200);
        $this->assertDatabaseHas('extracurricular_registrations', ['id' => $reg->id, 'status' => 'active']);
    }

    // 28. transactional invalid state safe
    public function test_invalid_state_safe(): void
    {
        $reg = $this->makeSubmittedRegistration(); // still submitted, no invoice
        $svc = app(\App\Services\PaymentService::class);
        try {
            $svc->createInvoiceForRegistration($reg);
            $this->fail('Should throw 422');
        } catch (\Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException $e) {
            $this->assertTrue(true);
        }
        $this->assertDatabaseCount('extracurricular_invoices', 0);
    }

    // 29. no payment columns on registrations table
    public function test_no_payment_columns_on_registrations(): void
    {
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('extracurricular_registrations');
        foreach (['payment_status', 'invoice_id', 'paid_at', 'payment_proof', 'payment_verified_by', 'payment_rejection_reason'] as $c) {
            $this->assertNotContains($c, $cols);
        }
    }

    // 30. registration stays approved not auto active
    public function test_approval_does_not_auto_active(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $this->assertDatabaseHas('extracurricular_registrations', ['id' => $reg->id, 'status' => 'approved']);
    }

    // 31. admin filters
    public function test_admin_filters(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/payments?status=unpaid')->assertStatus(200);
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/payments?search=INV')->assertStatus(200);
    }

    // 32. student detail includes snapshot fields, no internal paths
    public function test_student_resource_shape(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $res = $this->actingAs($this->studentUser, 'sanctum')->getJson("/api/v1/student/payments/{$id}");
        $res->assertStatus(200)
            ->assertJsonStructure(['data' => ['invoice_number', 'amount', 'status', 'issued_at', 'registration']]);
        $this->assertStringNotContainsString('file_path', $res->getContent());
    }

    // 33. file download authorized
    public function test_file_download_authz(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/student/payments/{$id}/proof", ['proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg')])->assertStatus(200);
        $proof = \App\Models\PaymentProof::where('invoice_id', $id)->first();
        $this->actingAs($this->studentUser2, 'sanctum')
            ->get("/api/v1/student/payments/{$id}/proofs/{$proof->id}/file")->assertStatus(403);
        $this->actingAs($this->coachUser, 'sanctum')
            ->get("/api/v1/admin/payments/{$id}/proofs/{$proof->id}/file")->assertStatus(403);
    }

    // 34. cancel registration cancels invoice
    public function test_cancel_cancels_invoice(): void
    {
        $reg = $this->makeSubmittedRegistration();
        $this->approveRegistration($reg);
        $id = $this->invoiceIdFor($reg);
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/registrations/{$reg->id}/cancel")->assertStatus(200);
        $this->assertDatabaseHas('extracurricular_invoices', ['id' => $id, 'status' => 'cancelled']);
    }
}
