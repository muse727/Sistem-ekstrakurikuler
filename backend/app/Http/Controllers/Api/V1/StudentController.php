<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    public function index(): JsonResponse
    {
        $students = Student::query()
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            StudentResource::collection($students),
            'Students retrieved successfully'
        );
    }
}
