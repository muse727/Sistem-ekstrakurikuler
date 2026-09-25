<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentVerificationStatus;
use App\Enums\RegistrationStatus;
use App\Models\Extracurricular;
use App\Models\ExtracurricularInvoice;
use App\Models\ExtracurricularRegistration;
use App\Models\PaymentProof;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PaymentService
{
    public const PROOF_ALLOWED_MIMES = ['image/jpeg', 'image/png', 'application/pdf'];

    public const PROOF_MAX_BYTES = 5 * 1024 * 1024;

    public function createInvoiceForRegistration(ExtracurricularRegistration $registration): ExtracurricularInvoice
    {
        return DB::transaction(function () use ($registration) {
            $registration = ExtracurricularRegistration::query()
                ->lockForUpdate()
                ->findOrFail($registration->id);

            $status = $registration->status instanceof RegistrationStatus
                ? $registration->status
                : RegistrationStatus::from((string) $registration->status);

            if ($status !== RegistrationStatus::APPROVED) {
                throw new UnprocessableEntityHttpException('Invoice can only be created for approved registrations.');
            }

            $existing = ExtracurricularInvoice::query()
                ->where('registration_id', $registration->id)
                ->whereIn('status', [InvoiceStatus::UNPAID->value, InvoiceStatus::PENDING_VERIFICATION->value])
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                return $existing->fresh()->load(['registration.student', 'registration.extracurricular', 'registration.academicYear', 'proofs.latestVerification']);
            }

            $ekskul = Extracurricular::query()->lockForUpdate()->findOrFail($registration->extracurricular_id);
            $amount = (int) $ekskul->fee_amount;

            $attempts = 0;
            do {
                $attempts++;
                $invoiceNumber = $this->generateInvoiceNumber();
                try {
                    $invoice = ExtracurricularInvoice::create([
                        'registration_id' => $registration->id,
                        'invoice_number' => $invoiceNumber,
                        'amount' => $amount,
                        'issued_at' => now(),
                        'due_at' => now()->addDays(7),
                        'status' => InvoiceStatus::UNPAID,
                    ]);
                    break;
                } catch (QueryException $e) {
                    if ($attempts >= 5 || ! $this->isUniqueViolation($e)) {
                        throw $e;
                    }
                    usleep(50000);
                    $invoice = null;
                }
            } while ($invoice === null && $attempts < 5);

            /** @var ExtracurricularInvoice $invoice */
            return $invoice->fresh()->load(['registration.student', 'registration.extracurricular', 'registration.academicYear', 'proofs.latestVerification']);
        });
    }

    public function studentInvoices(int $studentId, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = ExtracurricularInvoice::query()
            ->with(['registration.student', 'registration.extracurricular', 'registration.academicYear', 'proofs.latestVerification'])
            ->whereHas('registration', fn ($q) => $q->where('student_id', $studentId))
            ->orderByDesc('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function findStudentInvoice(int $studentId, int $invoiceId): ExtracurricularInvoice
    {
        $invoice = ExtracurricularInvoice::query()
            ->with(['registration.student', 'registration.extracurricular', 'registration.academicYear', 'proofs.verifications', 'proofs.latestVerification'])
            ->findOrFail($invoiceId);

        if ((int) $invoice->registration->student_id !== (int) $studentId) {
            throw new ConflictHttpException('Forbidden: invoice does not belong to this student.');
        }

        return $invoice;
    }

    public function uploadProof(ExtracurricularInvoice $invoice, UploadedFile $file, ?int $studentId = null): PaymentProof
    {
        return DB::transaction(function () use ($invoice, $file, $studentId) {
            $invoice = ExtracurricularInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $invoice->loadMissing('registration');

            if ($studentId !== null && (int) $invoice->registration->student_id !== (int) $studentId) {
                throw new ConflictHttpException('Forbidden: invoice does not belong to this student.');
            }

            $status = $invoice->status instanceof InvoiceStatus
                ? $invoice->status->value
                : (string) $invoice->status;

            if (in_array($status, [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value], true)) {
                throw new UnprocessableEntityHttpException('Cannot upload proof for a paid or cancelled invoice.');
            }

            $mime = $file->getMimeType() ?: '';
            if (! in_array($mime, self::PROOF_ALLOWED_MIMES, true)) {
                throw new UnprocessableEntityHttpException('Invalid file type. Only JPG, PNG, or PDF are allowed.');
            }

            if ($file->getSize() !== false && (int) $file->getSize() > self::PROOF_MAX_BYTES) {
                throw new UnprocessableEntityHttpException('File too large. Maximum 5MB.');
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: '');
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
                $extension = $mime === 'application/pdf' ? 'pdf' : ($mime === 'image/png' ? 'png' : 'jpg');
            }

            $filename = 'inv-'.$invoice->id.'-'.Str::uuid()->toString().'.'.$extension;
            $path = $file->storeAs('payment-proofs', $filename, 'local');

            $proof = PaymentProof::create([
                'invoice_id' => $invoice->id,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'file_size' => $file->getSize() ?? Storage::disk('local')->size($path),
                'uploaded_at' => now(),
                'submitted_at' => now(),
            ]);

            $invoice->update(['status' => InvoiceStatus::PENDING_VERIFICATION]);

            return $proof->fresh()->load(['latestVerification']);
        });
    }

    public function verifyProof(ExtracurricularInvoice $invoice, int $verifierId): ExtracurricularInvoice
    {
        return DB::transaction(function () use ($invoice, $verifierId) {
            $invoice = ExtracurricularInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $status = $invoice->status instanceof InvoiceStatus
                ? $invoice->status->value
                : (string) $invoice->status;

            if ($status === InvoiceStatus::PAID->value) {
                throw new ConflictHttpException('Invoice is already paid.');
            }

            $proof = PaymentProof::query()
                ->where('invoice_id', $invoice->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $proof) {
                throw new UnprocessableEntityHttpException('No payment proof to verify.');
            }

            $alreadyVerified = $proof->verifications()->lockForUpdate()->exists();
            if ($alreadyVerified) {
                throw new ConflictHttpException('This payment proof has already been verified.');
            }

            $proof->verifications()->create([
                'status' => PaymentVerificationStatus::APPROVED,
                'verified_by' => $verifierId,
                'verified_at' => now(),
            ]);

            $invoice->update([
                'status' => InvoiceStatus::PAID,
                'paid_at' => now(),
            ]);

            $this->activateAfterPayment($invoice->fresh());

            return $invoice->fresh()->load(['registration.student', 'registration.extracurricular', 'registration.academicYear', 'proofs.verifications', 'proofs.latestVerification']);
        });
    }

    public function rejectProof(ExtracurricularInvoice $invoice, int $verifierId, string $reason): ExtracurricularInvoice
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new UnprocessableEntityHttpException('Rejection reason is required.');
        }

        return DB::transaction(function () use ($invoice, $verifierId, $reason) {
            $invoice = ExtracurricularInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $status = $invoice->status instanceof InvoiceStatus
                ? $invoice->status->value
                : (string) $invoice->status;

            if ($status === InvoiceStatus::PAID->value) {
                throw new ConflictHttpException('Cannot reject a paid invoice.');
            }
            if ($status === InvoiceStatus::CANCELLED->value) {
                throw new UnprocessableEntityHttpException('Cannot reject a cancelled invoice.');
            }

            $proof = PaymentProof::query()
                ->where('invoice_id', $invoice->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $proof) {
                throw new UnprocessableEntityHttpException('No payment proof to reject.');
            }

            $alreadyVerified = $proof->verifications()->lockForUpdate()->exists();
            if ($alreadyVerified) {
                throw new ConflictHttpException('This payment proof has already been verified.');
            }

            $proof->verifications()->create([
                'status' => PaymentVerificationStatus::REJECTED,
                'verified_by' => $verifierId,
                'verified_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $invoice->update(['status' => InvoiceStatus::REJECTED]);

            return $invoice->fresh()->load(['registration.student', 'registration.extracurricular', 'registration.academicYear', 'proofs.verifications', 'proofs.latestVerification']);
        });
    }

    public function activateAfterPayment(ExtracurricularInvoice $invoice): ExtracurricularRegistration
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = ExtracurricularInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $status = $invoice->status instanceof InvoiceStatus
                ? $invoice->status->value
                : (string) $invoice->status;

            if ($status !== InvoiceStatus::PAID->value) {
                throw new UnprocessableEntityHttpException('Registration can only be activated after payment.');
            }

            $registration = ExtracurricularRegistration::query()
                ->lockForUpdate()
                ->findOrFail($invoice->registration_id);

            $regStatus = $registration->status instanceof RegistrationStatus
                ? $registration->status
                : RegistrationStatus::from((string) $registration->status);

            if ($regStatus !== RegistrationStatus::APPROVED) {
                throw new UnprocessableEntityHttpException('Only approved registrations can be activated after payment.');
            }

            $registration->update(['status' => RegistrationStatus::ACTIVE]);

            return $registration->fresh()->load(['student', 'extracurricular', 'academicYear']);
        });
    }

    public function adminList(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = ExtracurricularInvoice::query()
            ->with(['registration.student', 'registration.extracurricular', 'registration.academicYear', 'proofs.latestVerification']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['academic_year_id'])) {
            $query->whereHas('registration', fn ($q) => $q->where('academic_year_id', $filters['academic_year_id']));
        }
        if (!empty($filters['extracurricular_id'])) {
            $query->whereHas('registration', fn ($q) => $q->where('extracurricular_id', $filters['extracurricular_id']));
        }
        if (!empty($filters['student_id'])) {
            $query->whereHas('registration', fn ($q) => $q->where('student_id', $filters['student_id']));
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('registration.student', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function cancelActiveInvoicesForRegistration(int $registrationId): void
    {
        ExtracurricularInvoice::query()
            ->where('registration_id', $registrationId)
            ->whereIn('status', [InvoiceStatus::UNPAID->value, InvoiceStatus::PENDING_VERIFICATION->value, InvoiceStatus::REJECTED->value])
            ->update(['status' => InvoiceStatus::CANCELLED->value]);
    }

    protected function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "INV-{$date}-";
        $last = ExtracurricularInvoice::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->first();

        $next = 1;
        if ($last) {
            $suffix = substr($last->invoice_number, strlen($prefix));
            if (ctype_digit($suffix)) {
                $next = ((int) $suffix) + 1;
            }
        }

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'duplicate') || str_contains($message, 'unique');
    }
}
