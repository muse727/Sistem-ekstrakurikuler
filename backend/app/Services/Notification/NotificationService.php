<?php

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\ExtracurricularEvaluation;
use App\Models\ExtracurricularInvoice;
use App\Models\ExtracurricularRegistration;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function create(int $userId, NotificationType|string $type, string $title, string $message, ?string $referenceType = null, ?int $referenceId = null): Notification
    {
        $typeValue = $type instanceof NotificationType ? $type->value : (string) $type;

        return DB::transaction(function () use ($userId, $typeValue, $title, $message, $referenceType, $referenceId) {
            if ($referenceType !== null && $referenceId !== null) {
                return Notification::firstOrCreate(
                    [
                        'user_id' => $userId,
                        'type' => $typeValue,
                        'reference_type' => $referenceType,
                        'reference_id' => $referenceId,
                    ],
                    [
                        'title' => $title,
                        'message' => $message,
                    ]
                );
            }

            return Notification::create([
                'user_id' => $userId,
                'type' => $typeValue,
                'title' => $title,
                'message' => $message,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }

    public function notifyUser(int $userId, NotificationType|string $type, string $title, string $message, ?string $referenceType = null, ?int $referenceId = null): Notification
    {
        return $this->create($userId, $type, $title, $message, $referenceType, $referenceId);
    }

    /**
     * @return int[]
     */
    public function adminRecipientIds(): array
    {
        return User::query()
            ->whereIn('role', [UserRole::ADMIN->value, UserRole::SUPER_ADMIN->value])
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function notifyAdmins(NotificationType $type, string $title, string $message, ?string $referenceType = null, ?int $referenceId = null): void
    {
        foreach ($this->adminRecipientIds() as $adminId) {
            $this->create($adminId, $type, $title, $message, $referenceType, $referenceId);
        }
    }

    protected function studentUserId(int $studentId): ?int
    {
        $id = User::query()->where('student_id', $studentId)->where('is_active', true)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function notifyRegistrationSubmitted(ExtracurricularRegistration $registration): void
    {
        $registration->loadMissing(['student', 'extracurricular']);
        $studentName = $registration->student?->name ?? ('#'.$registration->student_id);
        $ekskulName = $registration->extracurricular?->name ?? ('#'.$registration->extracurricular_id);
        $this->notifyAdmins(
            NotificationType::REGISTRATION_SUBMITTED,
            'Pendaftaran baru submitted',
            "Siswa {$studentName} mendaftar {$ekskulName} (registrasi #{$registration->id}).",
            'registration',
            (int) $registration->id
        );
    }

    public function notifyRegistrationApproved(ExtracurricularRegistration $registration): void
    {
        $userId = $this->studentUserId((int) $registration->student_id);
        if ($userId === null) {
            return;
        }
        $registration->loadMissing(['extracurricular']);
        $ekskulName = $registration->extracurricular?->name ?? ('#'.$registration->extracurricular_id);
        $this->create(
            $userId,
            NotificationType::REGISTRATION_APPROVED,
            'Pendaftaran disetujui',
            "Pendaftaran kamu di {$ekskulName} telah disetujui.",
            'registration',
            (int) $registration->id
        );
    }

    public function notifyRegistrationRejected(ExtracurricularRegistration $registration): void
    {
        $userId = $this->studentUserId((int) $registration->student_id);
        if ($userId === null) {
            return;
        }
        $registration->loadMissing(['extracurricular']);
        $ekskulName = $registration->extracurricular?->name ?? ('#'.$registration->extracurricular_id);
        $this->create(
            $userId,
            NotificationType::REGISTRATION_REJECTED,
            'Pendaftaran ditolak',
            "Pendaftaran kamu di {$ekskulName} ditolak.",
            'registration',
            (int) $registration->id
        );
    }

    public function notifyPaymentProofSubmitted(ExtracurricularInvoice $invoice): void
    {
        $invoice->loadMissing(['registration.student', 'registration.extracurricular']);
        $studentName = $invoice->registration?->student?->name ?? ('#'.($invoice->registration?->student_id ?? '-'));
        $this->notifyAdmins(
            NotificationType::PAYMENT_PROOF_SUBMITTED,
            'Bukti bayar baru',
            "Bukti pembayaran invoice {$invoice->invoice_number} dari {$studentName} perlu verifikasi.",
            'invoice',
            (int) $invoice->id
        );
    }

    public function notifyPaymentProofApproved(ExtracurricularInvoice $invoice): void
    {
        $invoice->loadMissing(['registration']);
        $studentId = $invoice->registration ? (int) $invoice->registration->student_id : null;
        if ($studentId === null) {
            return;
        }
        $userId = $this->studentUserId($studentId);
        if ($userId === null) {
            return;
        }
        $this->create(
            $userId,
            NotificationType::PAYMENT_PROOF_APPROVED,
            'Pembayaran terverifikasi',
            "Pembayaran invoice {$invoice->invoice_number} telah disetujui.",
            'invoice',
            (int) $invoice->id
        );
    }

    public function notifyPaymentProofRejected(ExtracurricularInvoice $invoice): void
    {
        $invoice->loadMissing(['registration']);
        $studentId = $invoice->registration ? (int) $invoice->registration->student_id : null;
        if ($studentId === null) {
            return;
        }
        $userId = $this->studentUserId($studentId);
        if ($userId === null) {
            return;
        }
        $this->create(
            $userId,
            NotificationType::PAYMENT_PROOF_REJECTED,
            'Bukti bayar ditolak',
            "Bukti pembayaran invoice {$invoice->invoice_number} ditolak. Silakan unggah ulang.",
            'invoice',
            (int) $invoice->id
        );
    }

    public function notifyEvaluationPublished(ExtracurricularEvaluation $evaluation): void
    {
        $userId = $this->studentUserId((int) $evaluation->student_id);
        if ($userId === null) {
            return;
        }
        $evaluation->loadMissing(['extracurricular']);
        $ekskulName = $evaluation->extracurricular?->name ?? ('#'.$evaluation->extracurricular_id);
        $period = $evaluation->evaluation_period instanceof \BackedEnum ? $evaluation->evaluation_period->value : (string) $evaluation->evaluation_period;
        $this->create(
            $userId,
            NotificationType::EVALUATION_PUBLISHED,
            'Nilai evaluasi terbit',
            "Hasil evaluasi {$ekskulName} periode {$period} telah diterbitkan.",
            'evaluation',
            (int) $evaluation->id
        );
    }

    public function listForUser(int $userId, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = Notification::query()->where('user_id', $userId)->orderByDesc('id');
        if (!empty($filters['unread'])) {
            $query->whereNull('read_at');
        }

        return $query->paginate($perPage);
    }

    public function unreadCount(int $userId): int
    {
        return Notification::query()->where('user_id', $userId)->whereNull('read_at')->count();
    }

    public function markAsRead(Notification $notification): Notification
    {
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $notification->fresh();
    }

    public function markAllAsRead(int $userId): int
    {
        return Notification::query()->where('user_id', $userId)->whereNull('read_at')->update(['read_at' => now()]);
    }
}
