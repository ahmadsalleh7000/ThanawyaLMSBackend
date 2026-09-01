<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function download(Request $request, string $publicId): Response
    {
        $order = $request->user()->orders()->where('public_id', $publicId)->with('invoice')->firstOrFail();
        Gate::forUser($request->user())->authorize('view', $order);
        abort_unless($order->status === 'completed' && $order->invoice, 404);
        $invoice = $order->invoice;
        $rows = collect($invoice->line_items)->map(fn ($line) => '<tr><td>'.e($line['name_ar'] ?: $line['name']).'</td><td>'.e($line['course_id']).'</td></tr>')->join('');
        $html = '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>'.e($invoice->number).'</title><style>body{font-family:Arial,sans-serif;max-width:760px;margin:40px auto;color:#0f172a}header{border-bottom:2px solid #2563eb;padding-bottom:20px}table{width:100%;border-collapse:collapse;margin:24px 0}td,th{padding:12px;border-bottom:1px solid #ddd;text-align:right}.total{font-size:20px;font-weight:700}</style><header><h1>Ahmed LMS</h1><p>'.e($invoice->number).'</p></header><h2>'.e($invoice->buyer_name).'</h2><p>'.e($invoice->buyer_email).'</p><table><thead><tr><th>البند</th><th>المرجع</th></tr></thead><tbody>'.$rows.'</tbody></table><p>قبل الخصم: '.e($invoice->subtotal).' '.e($invoice->currency).'</p><p>الخصم: '.e($invoice->discount).' '.e($invoice->currency).'</p><p class="total">المدفوع: '.e($invoice->total).' '.e($invoice->currency).'</p></html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$invoice->number.'.html"', 'Cache-Control' => 'private, no-store']);
    }
}
