<?php

use App\Services\DocumentSequenceService;
use Carbon\Carbon;

beforeEach(function () {
    $this->sequenceService = app(DocumentSequenceService::class);
});

test('document sequences generate formatted sequential numbers with zero padding', function () {
    $period = '20261117';

    $first = $this->sequenceService->generate('TRX', $period);
    $second = $this->sequenceService->generate('TRX', $period);
    $third = $this->sequenceService->generate('TRX', $period);

    expect($first)->toBe('TRX-20261117-00001')
        ->and($second)->toBe('TRX-20261117-00002')
        ->and($third)->toBe('TRX-20261117-00003');
});

test('different document types maintain independent sequences', function () {
    $period = '20261117';

    $po1 = $this->sequenceService->generate('PO', $period);
    $rcv1 = $this->sequenceService->generate('RCV', $period);
    $ret1 = $this->sequenceService->generate('RET', $period);
    $exp1 = $this->sequenceService->generate('EXP', $period);
    $so1 = $this->sequenceService->generate('SO', $period);

    expect($po1)->toBe('PO-20261117-00001')
        ->and($rcv1)->toBe('RCV-20261117-00001')
        ->and($ret1)->toBe('RET-20261117-00001')
        ->and($exp1)->toBe('EXP-20261117-00001')
        ->and($so1)->toBe('SO-20261117-00001');

    $po2 = $this->sequenceService->generate('PO', $period);
    expect($po2)->toBe('PO-20261117-00002');
});

test('default period key uses current Asia Jakarta date', function () {
    $today = Carbon::now('Asia/Jakarta')->format('Ymd');
    $doc = $this->sequenceService->generate('TRX');

    expect($doc)->toStartWith("TRX-{$today}-");
});

test('generating rapid sequence produces strictly unique sequential numbers with no duplicates', function () {
    $period = '20261117';
    $generated = [];

    for ($i = 0; $i < 50; $i++) {
        $num = $this->sequenceService->generate('RAPID', $period);
        $generated[] = $num;
    }

    expect(count($generated))->toBe(50);
    // Unique check
    expect(count(array_unique($generated)))->toBe(50);
    expect($generated[0])->toBe('RAPID-20261117-00001');
    expect($generated[49])->toBe('RAPID-20261117-00050');
});
