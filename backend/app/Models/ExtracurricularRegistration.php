<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtracurricularRegistration extends Model
{
    use HasFactory;

    protected $table = 'extracurricular_registrations';

    protected $fillable = [
        'student_id',
        'extracurricular_id',
        'academic_year_id',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'rejection_reason',
        'approved_by',
        'rejected_by',
        'cancelled_by',
        'active_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            $status = $model->status instanceof RegistrationStatus
                ? $model->status->value
                : (string) $model->status;

            if (in_array($status, [
                RegistrationStatus::DRAFT->value,
                RegistrationStatus::SUBMITTED->value,
                RegistrationStatus::APPROVED->value,
                RegistrationStatus::ACTIVE->value,
            ], true)) {
                $model->active_key = implode(':', [
                    $model->student_id,
                    $model->extracurricular_id,
                    $model->academic_year_id,
                ]);
            } else {
                $model->active_key = null;
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function extracurricular(): BelongsTo
    {
        return $this->belongsTo(Extracurricular::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
