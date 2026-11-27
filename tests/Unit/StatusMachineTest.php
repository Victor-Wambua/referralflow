<?php

use App\Domain\StatusMachine;

it('allows exactly the documented transitions among the nine statuses', function () {
    $legal = [
        'created' => ['sent', 'cancelled'],
        'sent' => ['received', 'declined', 'cancelled'],
        'received' => ['accepted', 'declined', 'cancelled'],
        'accepted' => ['scheduled', 'cancelled'],
        'scheduled' => ['attended', 'cancelled'],
        'attended' => ['completed'],
        'completed' => [], 'declined' => [], 'cancelled' => [],
    ];
    $all = config('referralflow.statuses');
    expect($all)->toHaveCount(9);
    $checked = 0;
    foreach ($all as $from) {
        foreach ($all as $to) {
            expect(StatusMachine::isLegal($from, $to))->toBe(in_array($to, $legal[$from], true), "$from -> $to");
            $checked++;
        }
    }
    expect($checked)->toBe(81);
});

it('requires a reason only for declines and cancellations', function () {
    foreach (config('referralflow.statuses') as $s) {
        expect(StatusMachine::requiresReason($s))->toBe(in_array($s, ['declined', 'cancelled'], true));
    }
});

it('treats completed, declined and cancelled as terminal', function () {
    foreach (['completed', 'declined', 'cancelled'] as $s) {
        expect(StatusMachine::targets($s))->toBe([]);
    }
});
