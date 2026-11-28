<?php

namespace App\Services;

use App\Exports\ReportExportBuilder;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ReportExportService
{
    public const DISK = 'private';

    public const SUPPORTED_TYPES = ['sales', 'purchases', 'returns', 'expenses', 'inventory', 'profit-loss', 'cash-flow', 'stock-opnames', 'product-performance'];

    public const FORMATS = ['pdf', 'xlsx'];

    public function request(User $user, string $type, string $format, array $filters): ReportExport
    {
        if (! in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException('Jenis laporan tidak mendukung ekspor.');
        }
        if (! in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException('Format harus pdf atau xlsx.');
        }
        $from = $this->validDate($filters['from'] ?? null);
        $to = $this->validDate($filters['to'] ?? null);
        $filters = array_filter([
            'from' => $from,
            'to' => $to,
            'sort' => $filters['sort'] ?? null,
            'dir' => $filters['dir'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $export = ReportExport::create([
            'user_id' => $user->id,
            'report_type' => $type,
            'format' => $format,
            'filters' => $filters,
            'status' => 'requested',
            'requested_at' => Carbon::now('Asia/Jakarta'),
        ]);

        AuditLogger::log(action: 'report.export_requested', module: 'reports', reference: $export, newValues: ['report_type' => $type, 'format' => $format, 'filters' => $filters]);

        if (app()->runningUnitTests() || config('queue.default') === 'sync' || config('reports.sync_threshold', 0) > 0) {
            $this->process($export);
        } else {
            GenerateReportExport::dispatch($export->id);
        }

        return $export->fresh();
    }

    public function process(ReportExport|int $export): ReportExport
    {
        $id = $export instanceof ReportExport ? $export->id : $export;

        return DB::transaction(function () use ($id) {
            $export = ReportExport::whereKey($id)->lockForUpdate()->firstOrFail();
            if (! in_array($export->status, ['requested', 'failed', 'processing'], true)) {
                return $export;
            }
            $export->status = 'processing';
            $export->processing_at = Carbon::now('Asia/Jakarta');
            $export->attempts += 1;
            $export->error_code = null;
            $export->error_message = null;
            $export->save();

            try {
                $user = User::whereKey($export->user_id)->firstOrFail();
                if (! $this->canExport($user)) {
                    throw new InvalidArgumentException('Pengguna tidak lagi memiliki izin ekspor.');
                }
                $builder = app(ReportExportBuilder::class);
                $document = $builder->build($user, $export->report_type, $export->format, $export->filters ?? []);
                $path = 'exports/'.$export->uuid.'.'.$export->format;
                Storage::disk(self::DISK)->put($path, $document['contents']);
                $export->status = 'completed';
                $export->file_path = $path;
                $export->file_name = $document['file_name'];
                $export->mime_type = $document['mime_type'];
                $export->file_size = strlen($document['contents']);
                $export->completed_at = Carbon::now('Asia/Jakarta');
                $export->expires_at = Carbon::now('Asia/Jakarta')->addHours((int) config('reports.expiry_hours', 24));
                $export->save();
                AuditLogger::log(action: 'report.export_completed', module: 'reports', reference: $export, newValues: ['file_name' => $export->file_name, 'file_size' => $export->file_size, 'attempts' => $export->attempts]);
            } catch (\Throwable $exception) {
                $export->status = 'failed';
                $export->failed_at = Carbon::now('Asia/Jakarta');
                $export->error_code = 'EXPORT_FAILED';
                $export->error_message = mb_substr($exception->getMessage(), 0, 1000);
                $export->save();
                AuditLogger::log(action: 'report.export_failed', module: 'reports', reference: $export, newValues: ['error' => $export->error_message, 'attempts' => $export->attempts]);
                throw $exception;
            }

            return $export;
        });
    }

    public function download(ReportExport $export, User $user): array
    {
        if (! $this->canExport($user)) {
            abort(403, 'Tidak memiliki izin mengunduh ekspor.');
        }
        if ((int) $export->user_id !== (int) $user->id && ! $user->hasRole('admin')) {
            abort(403, 'File ekspor ini bukan milik Anda.');
        }
        if ($export->status !== 'completed') {
            abort(409, 'Ekspor belum selesai atau gagal.');
        }
        if ($export->isExpired()) {
            abort(410, 'File ekspor telah kedaluwarsa.');
        }
        if (! $export->file_path || ! Storage::disk(self::DISK)->exists($export->file_path)) {
            abort(404, 'File ekspor tidak ditemukan.');
        }

        return [
            'path' => Storage::disk(self::DISK)->path($export->file_path),
            'file_name' => $export->file_name ?? basename($export->file_path),
            'mime_type' => $export->mime_type ?? 'application/octet-stream',
        ];
    }

    public function expireOverdue(): int
    {
        $exports = ReportExport::where('status', 'completed')->whereNotNull('expires_at')->where('expires_at', '<=', Carbon::now('Asia/Jakarta'))->get();
        $count = 0;
        foreach ($exports as $export) {
            if ($export->file_path) {
                Storage::disk(self::DISK)->delete($export->file_path);
            }
            $export->status = 'expired';
            $export->save();
            $count++;
        }

        return $count;
    }

    public function canExport(User $user): bool
    {
        return $user->hasPermission('reports.export') && ($user->hasPermission('reports.view_all') || $user->hasPermission('reports.view_limited'));
    }

    private function validDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            throw new InvalidArgumentException('Format tanggal filter harus YYYY-MM-DD.');
        }

        return $date;
    }
}
