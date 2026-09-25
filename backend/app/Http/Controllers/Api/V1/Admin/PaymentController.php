<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectPaymentProofRequest;
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
        $paginator = $this->service->adminList($request->all());

        return ApiResponse::paginate(
            $paginator,
            InvoiceResource::collection($paginator->items()),
            'Invoices retrieved successfully'
        );
    }

    public function show(ExtracurricularInvoice $invoice): JsonResponse
    {
        $invoice->load(['registration.student', 'registration.extracurricular', 'registration.academicYear', 'proofs.verifications.verifier', 'proofs.latestVerification']);

        return ApiResponse::success(
            new InvoiceResource($invoice),
            'Invoice retrieved successfully'
        );
    }

    public function verify(Request $request, ExtracurricularInvoice $invoice): JsonResponse
    {
        $invoice = $this->service->verifyProof($invoice, (int) $request->user()->id);

        return ApiResponse::success(
            new InvoiceResource($invoice),
            'Payment proof approved successfully'
        );
    }

    public function reject(RejectPaymentProofRequest $request, ExtracurricularInvoice $invoice): JsonResponse
    {
        $invoice = $this->service->rejectProof(
            $invoice,
            (int) $request->user()->id,
            (string) $request->validated()['rejection_reason']
        );

        return ApiResponse::success(
            new InvoiceResource($invoice),
            'Payment proof rejected successfully'
        );
    }

    public function downloadProof(ExtracurricularInvoice $invoice, PaymentProof $proof): BinaryFileResponse|JsonResponse
    {
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
