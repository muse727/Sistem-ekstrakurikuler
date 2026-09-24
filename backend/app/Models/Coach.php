<?php

namespace App\Models;

use App\Enums\CoachRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Coach extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_number',
        'name',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function extracurriculars(): BelongsToMany
    {
        return $this->belongsToMany(Extracurricular::class, 'extracurricular_coach')
            ->withPivot('role')
            ->withTimestamps();
    }
}
