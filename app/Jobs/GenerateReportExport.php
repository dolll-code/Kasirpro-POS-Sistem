<?php

namespace App\Jobs;

use App\Models\ReportExport;
use App\Services\ReportExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $exportId) {}

    public function handle(ReportExportService $service): void
    {
        $export = ReportExport::find($this->exportId);
        if (! $export || $export->status === 'completed' || $export->status === 'expired') {
            return;
        }
        $service->process($export);
    }

    public function failed(\Throwable $exception): void
    {
        ReportExport::whereKey($this->exportId)->whereNotIn('status', ['completed', 'expired'])->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_code' => 'EXPORT_JOB_FAILED',
            'error_message' => mb_substr($exception->getMessage(), 0, 1000),
        ]);
    }
}
