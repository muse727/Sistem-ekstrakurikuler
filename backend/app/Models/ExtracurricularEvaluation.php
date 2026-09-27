<?php

namespace App\Models;

use App\Enums\EvaluationPeriod;
use App\Enums\EvaluationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtracurricularEvaluation extends Model
{
    use HasFactory;

    protected $table = 'extracurricular_evaluations';

    protected $fillable = [
        'student_id',
        'registration_id',
        'extracurricular_id',
        'academic_year_id',
        'evaluator_id',
        'evaluation_period',
        'attendance_score',
        'activity_score',
        'skill_score',
        'discipline_score',
        'final_score',
        'notes',
        'status',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_period' => EvaluationPeriod::class,
            'status' => EvaluationStatus::class,
            'attendance_score' => 'decimal:2',
            'activity_score' => 'decimal:2',
            'skill_score' => 'decimal:2',
            'discipline_score' => 'decimal:2',
            'final_score' => 'decimal:2',
            'evaluated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ExtracurricularRegistration::class, 'registration_id');
    }

    public function extracurricular(): BelongsTo
    {
        return $this->belongsTo(Extracurricular::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
