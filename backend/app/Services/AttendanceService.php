<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Enums\StudentAttendanceStatus;
use App\Models\CoachCheckIn;
use App\Models\ExtracurricularRegistration;
use App\Models\ExtracurricularSession;
use App\Models\StudentAttendance;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AttendanceService
{
    public const PHOTO_MAX_BYTES = 5 * 1024 * 1024;

    public const PHOTO_ALLOWED_MIMES = ['image/jpeg', 'image/png'];

    public function __construct(
        protected SessionService $sessions
    ) {}

    public static function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * asin(min(1, sqrt($a)));

        return (int) round($earth * $c);
    }

    // ---- Session lifecycle (delegated to SessionService for single source) ----

    public function createSession(int $createdBy, array $data): ExtracurricularSession
    {
        return $this->sessions->createSession($createdBy, $data);
    }

    public function open(ExtracurricularSession $session): ExtracurricularSession
    {
        return $this->sessions->open($session);
    }

    public function complete(ExtracurricularSession $session): ExtracurricularSession
    {
        return $this->sessions->complete($session);
    }

    public function cancel(ExtracurricularSession $session): ExtracurricularSession
    {
        return $this->sessions->cancel($session);
    }

    public function adminSessions(array $filters = []): LengthAwarePaginator
    {
        return $this->sessions->adminSessions($filters);
    }

    public function coachSessions(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->sessions->coachSessions($user, $filters);
    }

    public function studentSessions(int $studentId, array $filters = []): LengthAwarePaginator
    {
        return $this->sessions->studentSessions($studentId, $filters);
    }

    public function sessionAttendances(ExtracurricularSession $session, array $filters = []): LengthAwarePaginator
    {
        return $this->sessions->sessionAttendances($session, $filters);
    }

    public function studentAttendances(int $studentId, array $filters = []): LengthAwarePaginator
    {
        return $this->sessions->studentAttendances($studentId, $filters);
    }

    public function coachAttendances(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->sessions->coachAttendances($user, $filters);
    }

    public function assertCoachCanAccessSession(User $user, ExtracurricularSession $session): void
    {
        $this->sessions->assertCoachCanAccessSession($user, $session);
    }

    public function assertCoachAssigned(ExtracurricularSession $session, int $coachId): void
    {
        $this->sessions->assertCoachAssigned($session, $coachId);
    }

    // ---- Coach check-in ----

    /**
     * @param  User|int  $coach  Authenticated coach user or coach id
     * @param  array{latitude?:mixed,longitude?:mixed,accuracy_meters?:mixed,device_captured_at?:mixed}  $data
     */
    public function checkIn(
        ExtracurricularSession $session,
        User|int $coach,
        array $data,
        ?UploadedFile $photo
    ): CoachCheckIn {
        $coachId = $coach instanceof User ? (int) ($coach->coach_id ?? 0) : (int) $coach;
        if ($coach instanceof User && $coach->isSuperAdmin()) {
            // SUPER_ADMIN bypass assignment but still needs explicit coach target;
            // without linked coach profile we cannot attribute check-in.
            if ($coachId <= 0) {
                throw new ConflictHttpException('Super admin needs a linked coach profile to check in.');
            }
        }

        $current = $session->status instanceof SessionStatus
            ? $session->status
            : SessionStatus::from((string) $session->status);

        if ($current !== SessionStatus::OPEN) {
            throw new UnprocessableEntityHttpException('Check-in is only allowed for open sessions.');
        }

        $this->sessions->assertCoachAssigned($session, $coachId);

        if (CoachCheckIn::query()->where('session_id', $session->id)->where('coach_id', $coachId)->exists()) {
            throw new ConflictHttpException('Coach has already checked in for this session.');
        }

        if (! $photo) {
            throw new UnprocessableEntityHttpException('Selfie photo is required.');
        }
        $this->assertValidPhoto($photo);

        $latitude = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $longitude = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $accuracy = isset($data['accuracy_meters']) && $data['accuracy_meters'] !== null && $data['accuracy_meters'] !== ''
            ? (int) $data['accuracy_meters']
            : null;
        $deviceCapturedAt = $data['device_captured_at'] ?? null;
        if (is_string($deviceCapturedAt) && trim($deviceCapturedAt) === '') {
            $deviceCapturedAt = null;
        }

        if ($latitude === null || $longitude === null) {
            throw new UnprocessableEntityHttpException('Latitude and longitude are required.');
        }
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new UnprocessableEntityHttpException('Invalid GPS coordinates.');
        }
        if ($accuracy === null) {
            throw new UnprocessableEntityHttpException('GPS accuracy is required.');
        }
        if ($accuracy < 0 || $accuracy > 100000) {
            throw new UnprocessableEntityHttpException('Invalid GPS accuracy.');
        }

        // Server-side venue + radius validation. Client distance is never trusted.
        $session->loadMissing('venue');
        /** @var Venue|null $venue */
        $venue = $session->venue;
        if (! $venue) {
            throw new UnprocessableEntityHttpException('Session venue is not set.');
        }
        if ($venue->latitude === null || $venue->longitude === null || $venue->radius_meters === null) {
            throw new UnprocessableEntityHttpException('Venue GPS data is incomplete.');
        }
        $distance = self::haversineMeters(
            (float) $venue->latitude,
            (float) $venue->longitude,
            $latitude,
            $longitude
        );
        $radius = (int) $venue->radius_meters;
        if ($distance > $radius) {
            throw new UnprocessableEntityHttpException(
                "Outside venue radius. Distance {$distance}m exceeds allowed {$radius}m."
            );
        }

        $mime = $photo->getMimeType() ?: '';
        $ext = strtolower($photo->getClientOriginalExtension() ?: '');
        if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $ext = $mime === 'image/png' ? 'png' : 'jpg';
        }
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $path = "attendance/coach/{$coachId}/{$session->id}/".((string) Str::uuid()).'.'.$ext;
        Storage::disk('local')->putFileAs(dirname($path), $photo, basename($path));

        try {
            return DB::transaction(function () use ($session, $coachId, $latitude, $longitude, $accuracy, $deviceCapturedAt, $path, $distance) {
                return CoachCheckIn::create([
                    'session_id' => $session->id,
                    'coach_id' => $coachId,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'accuracy_meters' => $accuracy,
                    'distance_from_venue_meters' => $distance,
                    'device_captured_at' => $deviceCapturedAt,
                    'server_received_at' => now(),
                    'photo_path' => $path,
                    'status' => 'present',
                ]);
            });
        } catch (QueryException $e) {
            Storage::disk('local')->delete($path);
            if ($this->isUniqueViolation($e)) {
                throw new ConflictHttpException('Coach has already checked in for this session.');
            }
            throw $e;
        }
    }

    public function listCheckIns(ExtracurricularSession $session): \Illuminate\Database\Eloquent\Collection
    {
        return CoachCheckIn::query()->with('coach')->where('session_id', $session->id)->orderBy('id')->get();
    }

    public function listAttendances(ExtracurricularSession $session, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = StudentAttendance::query()->with(['student', 'registration', 'recorder', 'updater'])
            ->where('session_id', $session->id);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('id')->get();
    }

    // ---- Student attendance ----

    /**
     * @param  User|int  $recorder
     */
    public function bulkRecord(
        ExtracurricularSession $session,
        User|int $recorder,
        array $items,
        ?int $recorderCoachId = null
    ): \Illuminate\Database\Eloquent\Collection {
        $current = $session->status instanceof SessionStatus
            ? $session->status
            : SessionStatus::from((string) $session->status);

        if ($current !== SessionStatus::OPEN) {
            throw new UnprocessableEntityHttpException('Attendance can only be recorded for open sessions.');
        }

        $recordedBy = $recorder instanceof User ? (int) $recorder->id : (int) $recorder;
        $coachId = $recorderCoachId;
        if ($recorder instanceof User && $recorder->isCoach()) {
            $coachId = (int) ($recorder->coach_id ?? 0);
            $this->sessions->assertCoachAssigned($session, $coachId);
        }

        $this->assertSessionHasCheckIn($session, $coachId);

        if (empty($items)) {
            throw new UnprocessableEntityHttpException('Attendance items are required.');
        }

        return DB::transaction(function () use ($session, $items, $recordedBy) {
            $ids = [];
            foreach ($items as $item) {
                $studentId = (int) ($item['student_id'] ?? 0);
                $status = (string) ($item['status'] ?? '');
                $notes = $item['notes'] ?? null;

                if ($studentId <= 0) {
                    throw new UnprocessableEntityHttpException('student_id is required.');
                }
                try {
                    $attendanceStatus = StudentAttendanceStatus::from($status);
                } catch (\ValueError) {
                    throw new UnprocessableEntityHttpException('Invalid attendance status.');
                }

                $registration = $this->resolveEligibleRegistration($session, $studentId);

                $existing = StudentAttendance::query()
                    ->where('session_id', $session->id)
                    ->where('student_id', $studentId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $existing->registration_id = $registration->id;
                    $existing->status = $attendanceStatus->value;
                    $existing->notes = $notes;
                    $existing->updated_by = $recordedBy;
                    $existing->save();
                    $ids[] = $existing->id;
                } else {
                    $created = StudentAttendance::create([
                        'session_id' => $session->id,
                        'student_id' => $studentId,
                        'registration_id' => $registration->id,
                        'status' => $attendanceStatus->value,
                        'notes' => $notes,
                        'recorded_by' => $recordedBy,
                        'recorded_at' => now(),
                        'updated_by' => null,
                    ]);
                    $ids[] = $created->id;
                }
            }

            return StudentAttendance::query()->with(['student', 'registration'])
                ->whereIn('id', $ids)->orderBy('id')->get();
        });
    }

    /**
     * @param  User|int  $recorder
     */
    public function recordOne(
        ExtracurricularSession $session,
        User|int $recorder,
        int $studentId,
        string $status,
        ?string $notes = null
    ): StudentAttendance {
        $result = $this->bulkRecord($session, $recorder, [[
            'student_id' => $studentId,
            'status' => $status,
            'notes' => $notes,
        ]]);

        return $result->firstOrFail()->fresh()->load(['student', 'registration', 'recorder', 'updater', 'session']);
    }

    /**
     * @param  User|int  $updater
     */
    public function correct(
        StudentAttendance $attendance,
        User|int $updater,
        string $status,
        ?string $notes = null
    ): StudentAttendance {
        $updatedBy = $updater instanceof User ? (int) $updater->id : (int) $updater;
        /** @var ExtracurricularSession $session */
        $session = $attendance->session;
        $current = $session->status instanceof SessionStatus
            ? $session->status
            : SessionStatus::from((string) $session->status);

        if (! in_array($current, [SessionStatus::OPEN, SessionStatus::COMPLETED], true)) {
            throw new UnprocessableEntityHttpException('Attendance correction is only allowed for open or completed sessions.');
        }

        try {
            $attendanceStatus = StudentAttendanceStatus::from($status);
        } catch (\ValueError) {
            throw new UnprocessableEntityHttpException('Invalid attendance status.');
        }

        $registration = $this->resolveEligibleRegistration($session, (int) $attendance->student_id);

        $attendance->registration_id = $registration->id;
        $attendance->status = $attendanceStatus->value;
        $attendance->notes = $notes;
        $attendance->updated_by = $updatedBy;
        $attendance->save();

        return $attendance->fresh()->load(['student', 'registration', 'recorder', 'updater']);
    }

    public function resolveEligibleRegistration(ExtracurricularSession $session, int $studentId): ExtracurricularRegistration
    {
        $eligible = [RegistrationStatus::APPROVED->value, RegistrationStatus::ACTIVE->value];
        $registration = ExtracurricularRegistration::query()
            ->where('student_id', $studentId)
            ->where('extracurricular_id', $session->extracurricular_id)
            ->where('academic_year_id', $session->academic_year_id)
            ->whereIn('status', $eligible)
            ->orderByDesc('id')
            ->first();

        if (! $registration) {
            throw new NotFoundHttpException('Student is not eligible for this session.');
        }

        return $registration;
    }

    protected function assertSessionHasCheckIn(ExtracurricularSession $session, ?int $coachId = null): void
    {
        $query = CoachCheckIn::query()->where('session_id', $session->id);
        if ($coachId !== null && $coachId > 0) {
            $query->where('coach_id', $coachId);
        }
        if (! $query->exists()) {
            throw new UnprocessableEntityHttpException('Attendance requires a coach check-in first.');
        }
    }

    protected function assertValidPhoto(UploadedFile $photo): void
    {
        $mime = $photo->getMimeType() ?: '';
        if (! in_array($mime, self::PHOTO_ALLOWED_MIMES, true)) {
            throw new UnprocessableEntityHttpException('Only JPG or PNG photos are allowed.');
        }
        if ($photo->getSize() !== false && (int) $photo->getSize() > self::PHOTO_MAX_BYTES) {
            throw new UnprocessableEntityHttpException('Photo too large. Maximum 5MB.');
        }
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'duplicate') || str_contains($message, 'unique');
    }
}
