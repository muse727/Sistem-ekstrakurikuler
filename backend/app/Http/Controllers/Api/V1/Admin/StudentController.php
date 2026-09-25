<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Services\Admin\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(
        protected StudentService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->all());

        return ApiResponse::paginate(
            $paginator,
            StudentResource::collection($paginator->items()),
            'Students retrieved successfully'
        );
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = $this->service->create($request->validated());

        return ApiResponse::success(
            new StudentResource($student),
            'Student created successfully',
            201
        );
    }

    public function show(Student $student): JsonResponse
    {
        return ApiResponse::success(
            new StudentResource($student),
            'Student retrieved successfully'
        );
    }

    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        $student = $this->service->update($student, $request->validated());

        return ApiResponse::success(
            new StudentResource($student),
            'Student updated successfully'
        );
    }
}
