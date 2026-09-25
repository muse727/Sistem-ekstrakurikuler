<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\Extracurricular;
use App\Models\ExtracurricularRegistration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class RegistrationService
{
    /** @var string[] */
    public const ACTIVE_LIKE = ['draft', 'submitted', 'approved', 'active'];

    /** @var string[] */
    public const COUNT_AGAINST_QUOTA = ['approved', 'active'];

    public function availableExtracurriculars(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = Extracurricular::query()->with(['academicYear', 'coaches', 'schedules.venue'])
            ->where('is_active', true);

        if (!empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function studentList(int $studentId, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = ExtracurricularRegistration::query()
            ->with(['student', 'extracurricular', 'academicYear'])
            ->where('student_id', $studentId)
            ->orderByDesc('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }
        if (!empty($filters['extracurricular_id'])) {
            $query->where('extracurricular_id', $filters['extracurricular_id']);
        }

        return $query->paginate($perPage);
    }

    public function adminList(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = ExtracurricularRegistration::query()
            ->with(['student', 'extracurricular', 'academicYear', 'approver', 'rejector', 'canceller']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }
        if (!empty($filters['extracurricular_id'])) {
            $query->where('extracurricular_id', $filters['extracurricular_id']);
        }
        if (!empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%");
                })->orWhereHas('extracurricular', function ($eq) use ($search) {
                    $eq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })->orWhere('rejection_reason', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function submit(int $studentId, int $extracurricularId): ExtracurricularRegistration
    {
        return DB::transaction(function () use ($studentId, $extracurricularId) {
            /** @var Extracurricular $ekskul */
            $ekskul = Extracurricular::query()->lockForUpdate()->find($extracurricularId);

            if (!$ekskul) {
                throw new UnprocessableEntityHttpException('Extracurricular not found.');
            }
            if (!$ekskul->is_active) {
                throw new UnprocessableEntityHttpException('Extracurricular is not active.');
            }

            $duplicate = ExtracurricularRegistration::query()
                ->where('student_id', $studentId)
                ->where('extracurricular_id', $extracurricularId)
                ->where('academic_year_id', $ekskul->academic_year_id)
                ->whereIn('status', self::ACTIVE_LIKE)
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw new ConflictHttpException('You already have an active registration for this extracurricular.');
            }

            $registration = ExtracurricularRegistration::create([
                'student_id' => $studentId,
                'extracurricular_id' => $ekskul->id,
                'academic_year_id' => $ekskul->academic_year_id,
                'status' => RegistrationStatus::SUBMITTED,
                'submitted_at' => now(),
            ]);

            return $registration->fresh()->load(['student', 'extracurricular', 'academicYear']);
        });
    }

    public function approve(ExtracurricularRegistration $registration, int $actorId): ExtracurricularRegistration
    {
        return DB::transaction(function () use ($registration, $actorId) {
            $registration = ExtracurricularRegistration::query()->lockForUpdate()->findOrFail($registration->id);

            $this->assertStatus($registration, [RegistrationStatus::SUBMITTED]);

            $ekskul = Extracurricular::query()->lockForUpdate()->findOrFail($registration->extracurricular_id);
            $this->assertQuotaAvailable($ekskul, $registration);

            $registration->update([
                'status' => RegistrationStatus::APPROVED,
                'approved_at' => now(),
                'approved_by' => $actorId,
            ]);

            $fresh = $registration->fresh();
            app(\App\Services\PaymentService::class)->createInvoiceForRegistration($fresh);

            return $fresh->fresh()->load(['student', 'extracurricular', 'academicYear', 'approver', 'rejector', 'canceller', 'invoices']);
        });
    }

    public function reject(ExtracurricularRegistration $registration, int $actorId, string $reason): ExtracurricularRegistration
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['rejection_reason' => ['Rejection reason is required.']]);
        }

        return DB::transaction(function () use ($registration, $actorId, $reason) {
            $registration = ExtracurricularRegistration::query()->lockForUpdate()->findOrFail($registration->id);

            $this->assertStatus($registration, [RegistrationStatus::SUBMITTED]);

            $registration->update([
                'status' => RegistrationStatus::REJECTED,
                'rejected_at' => now(),
                'rejected_by' => $actorId,
                'rejection_reason' => $reason,
            ]);

            return $registration->fresh()->load(['student', 'extracurricular', 'academicYear', 'approver', 'rejector', 'canceller']);
        });
    }

    public function activate(ExtracurricularRegistration $registration, int $actorId): ExtracurricularRegistration
    {
        return DB::transaction(function () use ($registration, $actorId) {
            $registration = ExtracurricularRegistration::query()->lockForUpdate()->findOrFail($registration->id);

            $this->assertStatus($registration, [RegistrationStatus::APPROVED]);

            $ekskul = Extracurricular::query()->lockForUpdate()->findOrFail($registration->extracurricular_id);
            $this->assertQuotaAvailable($ekskul, $registration, true);

            $registration->update(['status' => RegistrationStatus::ACTIVE]);

            return $registration->fresh()->load(['student', 'extracurricular', 'academicYear', 'approver', 'rejector', 'canceller']);
        });
    }

    public function cancel(ExtracurricularRegistration $registration, int $actorId): ExtracurricularRegistration
    {
        return DB::transaction(function () use ($registration, $actorId) {
            $registration = ExtracurricularRegistration::query()->lockForUpdate()->findOrFail($registration->id);

            $this->assertStatus($registration, [
                RegistrationStatus::DRAFT,
                RegistrationStatus::SUBMITTED,
                RegistrationStatus::APPROVED,
                RegistrationStatus::ACTIVE,
            ]);

            $registration->update([
                'status' => RegistrationStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actorId,
            ]);

            app(\App\Services\PaymentService::class)->cancelActiveInvoicesForRegistration((int) $registration->id);

            return $registration->fresh()->load(['student', 'extracurricular', 'academicYear', 'approver', 'rejector', 'canceller']);
        });
    }

    /**
     * @param RegistrationStatus[] $allowed
     */
    protected function assertStatus(ExtracurricularRegistration $registration, array $allowed): void
    {
        $current = $registration->status instanceof RegistrationStatus
            ? $registration->status
            : RegistrationStatus::from((string) $registration->status);

        foreach ($allowed as $status) {
            if ($current === $status) {
                return;
            }
        }

        $allowedValues = implode(', ', array_map(fn ($s) => $s->value, $allowed));
        throw new UnprocessableEntityHttpException(
            "Invalid status transition from '{$current->value}'. Allowed: {$allowedValues}."
        );
    }

    protected function assertQuotaAvailable(Extracurricular $ekskul, ?ExtracurricularRegistration $excluding = null, bool $forActivation = false): void
    {
        if ($ekskul->quota === null) {
            return;
        }

        $query = ExtracurricularRegistration::query()
            ->where('extracurricular_id', $ekskul->id)
            ->where('academic_year_id', $ekskul->academic_year_id)
            ->whereIn('status', self::COUNT_AGAINST_QUOTA);

        if ($excluding) {
            $query->where('id', '!=', $excluding->id);
        }

        $used = $query->count();

        // Approving/activating consumes one more slot.
        if ($used >= (int) $ekskul->quota) {
            throw new ConflictHttpException('Extracurricular quota is full.');
        }
    }
}
