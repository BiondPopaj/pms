<?php

namespace App\Http\Controllers\Api\V1\Properties;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $property = $request->get('current_property');
        $invoices = Invoice::where('property_id', $property->id)
            ->with(['guest:id,first_name,last_name,email', 'folio:id,folio_number'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->from, fn ($q, $d) => $q->where('issue_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('issue_date', '<=', $d))
            ->latest('issue_date')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data'    => $invoices->items(),
            'meta'    => [
                'total'        => $invoices->total(),
                'per_page'     => $invoices->perPage(),
                'current_page' => $invoices->currentPage(),
                'last_page'    => $invoices->lastPage(),
            ],
        ]);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['guest', 'folio.items', 'issuedBy:id,name']);

        return response()->json([
            'success' => true,
            'data'    => $invoice,
        ]);
    }

    public function issue(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Invoice is already issued.'], 422);
        }

        $invoice->issue($request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Invoice issued.',
            'data'    => $invoice->fresh(),
        ]);
    }

    public function send(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->status === Invoice::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Issue the invoice before sending.'], 422);
        }

        // TODO: Queue email job
        // Mail::to($invoice->guest->email)->send(new InvoiceMail($invoice));

        return response()->json([
            'success' => true,
            'message' => 'Invoice sent to ' . $invoice->guest->email . '.',
        ]);
    }

    public function downloadPdf(Invoice $invoice): Response
    {
        $invoice->load(['guest', 'folio.items', 'folio.reservation']);
        $property = $invoice->property;

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice', 'property'));

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }
}
