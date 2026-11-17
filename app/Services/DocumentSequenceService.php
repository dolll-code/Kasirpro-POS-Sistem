<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentSequenceService
{
    /**
     * Generate the next document number atomically with row-level locking.
     *
     * Example outputs:
     * - TRX-20261117-00001
     * - PO-20261117-00001
     * - RCV-20261117-00001
     * - RET-20261117-00001
     * - EXP-20261117-00001
     * - SO-20261117-00001
     * - ADJ-20261117-00001
     *
     * @param  string  $type  Document type prefix (e.g. 'TRX', 'PO', 'RCV', 'RET', 'EXP', 'SO', 'ADJ')
     * @param  string|null  $periodKey  Period key (defaults to 'Ymd' in Asia/Jakarta)
     * @param  int  $digits  Zero-padding width (default 5 digits)
     * @return string Formatted unique document code
     */
    public function generate(string $type, ?string $periodKey = null, int $digits = 5): string
    {
        $type = strtoupper(trim($type));
        $periodKey = $periodKey ?? Carbon::now('Asia/Jakarta')->format('Ymd');

        $nextNumber = DB::transaction(function () use ($type, $periodKey) {
            // First attempt to find and lock the row
            $sequence = DocumentSequence::where('document_type', $type)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                // Insert or ignore / catch duplicate key race condition
                try {
                    $sequence = DocumentSequence::create([
                        'document_type' => $type,
                        'period_key' => $periodKey,
                        'last_number' => 0,
                    ]);

                    // Re-lock the newly created row
                    $sequence = DocumentSequence::where('id', $sequence->id)
                        ->lockForUpdate()
                        ->first();
                } catch (\Throwable $e) {
                    // In case another concurrent thread inserted it first
                    $sequence = DocumentSequence::where('document_type', $type)
                        ->where('period_key', $periodKey)
                        ->lockForUpdate()
                        ->firstOrFail();
                }
            }

            $sequence->last_number += 1;
            $sequence->save();

            return $sequence->last_number;
        });

        $padded = str_pad((string) $nextNumber, $digits, '0', STR_PAD_LEFT);

        return "{$type}-{$periodKey}-{$padded}";
    }
}
