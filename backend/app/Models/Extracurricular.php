<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Extracurricular extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'name',
        'code',
        'description',
        'fee_amount',
        'quota',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'academic_year_id' => 'integer',
            'fee_amount' => 'integer',
            'quota' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function coaches(): BelongsToMany
    {
        return $this->belongsToMany(Coach::class, 'extracurricular_coach')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ExtracurricularSchedule::class);
    }
}
