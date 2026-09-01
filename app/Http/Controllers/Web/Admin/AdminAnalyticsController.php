<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsReportService $analytics): View
    {
        return view('admin.analytics.index', ['report' => $analytics->generate($this->days($request))]);
    }

    public function export(Request $request, AnalyticsReportService $analytics): StreamedResponse
    {
        $report = $analytics->generate($this->days($request));

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Enrollments', 'Lesson completions', 'Revenue'], ',', '"', '\\');
            foreach ($report['trend'] as $point) {
                fputcsv($output, [$point['date']->toDateString(), $point['enrollments'], $point['completions'], $point['revenue']], ',', '"', '\\');
            }
            fclose($output);
        }, 'analytics-'.$report['period']['start']->toDateString().'-'.$report['period']['end']->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function days(Request $request): int
    {
        return (int) validator(['period' => $request->integer('period', 30)], ['period' => ['required', Rule::in([7, 30, 90, 365])]])->validate()['period'];
    }
}
