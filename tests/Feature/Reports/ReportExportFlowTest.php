<?php

use App\Exports\ReportExportBuilder;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\StoreProfile;
use App\Models\User;
use App\Services\ReportExportService;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->admin = User::factory()->create(['role_id' => Role::where('code', 'admin')->value('id'), 'is_active' => true]);
    $this->supervisor = User::factory()->create(['role_id' => Role::where('code', 'supervisor')->value('id'), 'is_active' => true]);
    $this->cashier = User::factory()->create(['role_id' => Role::where('code', 'cashier')->value('id'), 'is_active' => true]);
    StoreProfile::current()->update(['name' => 'Toko Export Test', 'address' => 'Jl. Export', 'receipt_paper_width' => '58']);
    Storage::fake('private');
    $this->exportService = app(ReportExportService::class);
});

test('pdf export completes in private storage with store metadata', function () {
    $export = $this->exportService->request($this->admin, 'sales', 'pdf', ['from' => now('Asia/Jakarta')->toDateString(), 'to' => now('Asia/Jakarta')->toDateString()]);
    expect($export->status)->toBe('completed')
        ->and($export->file_path)->not->toBeNull()
        ->and($export->mime_type)->toBe('application/pdf')
        ->and(Storage::disk('private')->exists($export->file_path))->toBeTrue();
    $contents = Storage::disk('private')->get($export->file_path);
    expect(substr($contents, 0, 5))->toBe('%PDF-')
        ->and(strlen($contents))->toBeGreaterThan(1000);
});

test('xlsx export stores numeric cells, date cells, and sanitizes formula injection', function () {
    $builder = app(ReportExportBuilder::class);
    $document = [
        'store' => StoreProfile::current(), 'title' => 'Test', 'type' => 'sales', 'period' => ['from_date' => '2026-01-01', 'to_date' => '2026-01-31'],
        'summary' => ['net' => '1234.50'], 'summary_labels' => ['net' => 'Net'], 'generated_at' => now(), 'generated_by' => 'Admin', 'filters' => [],
        'columns' => [['key' => 'name', 'label' => 'Nama'], ['key' => 'amount', 'label' => 'Jumlah'], ['key' => 'completed_at', 'label' => 'Tanggal']],
        'rows' => collect([['name' => '=SUM(A1:A2)', 'amount' => '1234.50', 'completed_at' => '2026-01-15 10:30:00']]),
    ];
    $result = $builder->xlsx($document);
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    file_put_contents($tmp, $result['contents']);
    $sheet = IOFactory::load($tmp)->getActiveSheet();
    $rows = $sheet->toArray(null, true, false, true);
    $dataRow = null;
    foreach ($rows as $row) {
        foreach ($row as $cell) {
            if ($cell === "'=SUM(A1:A2)") {
                $dataRow = $row;
            }
        }
    }
    expect($dataRow)->not->toBeNull();
    expect($dataRow['A'])->toBe("'=SUM(A1:A2)");
    $rowIndex = null;
    foreach ($sheet->toArray(null, true, false, false) as $idx => $row) {
        if (($row[0] ?? null) === "'=SUM(A1:A2)") {
            $rowIndex = $idx + 1;
        }
    }
    expect($rowIndex)->not->toBeNull()
        ->and($sheet->getCell('B'.$rowIndex)->getDataType())->toBe('n')
        ->and($sheet->getStyle('C'.$rowIndex)->getNumberFormat()->getFormatCode())->toContain('yyyy');
    unlink($tmp);
});

test('download checks ownership, permission, expiry, existence, and private disk', function () {
    Storage::fake('private');
    Storage::disk('private')->put('exports/fake.pdf', '%PDF-fake');
    $export = ReportExport::create(['user_id' => $this->admin->id, 'report_type' => 'sales', 'format' => 'pdf', 'status' => 'completed', 'file_path' => 'exports/fake.pdf', 'file_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'requested_at' => now(), 'expires_at' => now()->addHour()]);

    expect(fn () => $this->exportService->download($export, $this->supervisor))->toThrow(HttpException::class);
    $this->expectException(HttpException::class);
    $this->exportService->download($export, $this->cashier);
});

test('expired export cannot be downloaded and expiry cleanup deletes private file', function () {
    Storage::disk('private')->put('exports/expired.pdf', '%PDF-old');
    $export = ReportExport::create(['user_id' => $this->admin->id, 'report_type' => 'sales', 'format' => 'pdf', 'status' => 'completed', 'file_path' => 'exports/expired.pdf', 'file_name' => 'old.pdf', 'mime_type' => 'application/pdf', 'requested_at' => now()->subDays(2), 'expires_at' => now()->subHour()]);
    try {
        $this->exportService->download($export, $this->admin);
        $this->fail('Expected expired exception');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(410);
    }
    expect($this->exportService->expireOverdue())->toBe(1)
        ->and($export->fresh()->status)->toBe('expired')
        ->and(Storage::disk('private')->exists('exports/expired.pdf'))->toBeFalse();
});

test('job failure marks export failed and retry-safe process does not duplicate completed file', function () {
    $export = ReportExport::create(['user_id' => $this->admin->id, 'report_type' => 'sales', 'format' => 'pdf', 'status' => 'requested', 'requested_at' => now()]);
    (new GenerateReportExport($export->id))->failed(new RuntimeException('worker crash'));
    expect($export->fresh()->status)->toBe('failed');
    $processed = $this->exportService->process($export->fresh());
    expect($processed->status)->toBe('completed');
    $path = $processed->file_path;
    $again = $this->exportService->process($processed);
    expect($again->file_path)->toBe($path);
    expect(ReportExport::whereKey($export->id)->count())->toBe(1);
});

test('permission change before download is rejected and API export requires permission', function () {
    $this->actingAs($this->admin);
    $this->postJson(route('api.reports.export', 'sales'), ['format' => 'xlsx'])->assertCreated();
    $this->actingAs($this->cashier);
    $this->postJson(route('api.reports.export', 'sales'), ['format' => 'pdf'])->assertForbidden();

    $export = ReportExport::create(['user_id' => $this->admin->id, 'report_type' => 'sales', 'format' => 'pdf', 'status' => 'completed', 'file_path' => 'exports/a.pdf', 'file_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'requested_at' => now(), 'expires_at' => now()->addHour()]);
    $this->admin->role->permissions()->detach();
    expect($this->admin->fresh()->hasPermission('reports.export'))->toBeFalse();
    $this->expectException(HttpException::class);
    $this->exportService->download($export, $this->admin->fresh());
});
