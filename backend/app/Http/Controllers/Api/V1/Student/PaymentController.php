<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UploadPaymentProofRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\ExtracurricularInvoice;
use App\Models\PaymentProof;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $studentId = $request->user()->student_id;

        if (! $studentId) {
            return ApiResponse::error('Student profile not linked to this account.', null, 403);
        }

        $paginator = $this->service->studentInvoices((int) $studentId, $request->all());

        return ApiResponse::paginate(
            $paginator,
            InvoiceResource::collection($paginator->items()),
            'Invoices retrieved successfully'
        );
    }

    public function show(Request $request, ExtracurricularInvoice $invoice): JsonResponse
    {
        $studentId = $request->user()->student_id;

        if (! $studentId) {
            return ApiResponse::error('Student profile not linked to this account.', null, 403);
        }

        try {
            $invoice = $this->service->findStudentInvoice((int) $studentId, (int) $invoice->id);
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        }

        return ApiResponse::success(
            new InvoiceResource($invoice),
            'Invoice retrieved successfully'
        );
    }

    public function uploadProof(UploadPaymentProofRequest $request, ExtracurricularInvoice $invoice): JsonResponse
    {
        $studentId = $request->user()->student_id;

        if (! $studentId) {
            return ApiResponse::error('Student profile not linked to this account.', null, 403);
        }

        $invoice->loadMissing('registration');

        if ((int) $invoice->registration->student_id !== (int) $studentId) {
            return ApiResponse::error('Forbidden: invoice does not belong to this student.', null, 403);
        }

        $proof = $this->service->uploadProof(
            $invoice,
            $request->file('proof'),
            (int) $studentId
        );

        $fresh = $this->service->findStudentInvoice((int) $studentId, (int) $invoice->id);

        return ApiResponse::success(
            new InvoiceResource($fresh),
            'Payment proof uploaded successfully'
        );
    }

    public function downloadProof(Request $request, ExtracurricularInvoice $invoice, PaymentProof $proof): BinaryFileResponse|JsonResponse
    {
        $studentId = $request->user()->student_id;

        if (! $studentId) {
            return ApiResponse::error('Student profile not linked to this account.', null, 403);
        }

        $invoice->loadMissing('registration');

        if ((int) $invoice->registration->student_id !== (int) $studentId) {
            return ApiResponse::error('Forbidden: invoice does not belong to this student.', null, 403);
        }

        if ((int) $proof->invoice_id !== (int) $invoice->id) {
            return ApiResponse::error('Not found', null, 404);
        }

        if (! Storage::disk('local')->exists($proof->file_path)) {
            return ApiResponse::error('File not found', null, 404);
        }

        $absolute = Storage::disk('local')->path($proof->file_path);

        return response()->download($absolute, $proof->original_filename, [
            'Content-Type' => $proof->mime_type,
        ]);
    }
}
