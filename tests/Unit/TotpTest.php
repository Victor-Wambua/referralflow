<?php

use App\Security\Totp;

// RFC 6238 Appendix B test vectors (SHA-1, secret "12345678901234567890"), 8-digit codes truncated to 6 here.
$secret = Totp::base32Encode('12345678901234567890');

it('matches the RFC 6238 SHA-1 test vectors', function (int $time, string $eight) use ($secret) {
    expect(Totp::code($secret, $time, 8))->toBe($eight);
    expect(Totp::code($secret, $time, 6))->toBe(substr($eight, 2));
})->with([
    [59, '94287082'], [1111111109, '07081804'], [1111111111, '14050471'],
    [1234567890, '89005924'], [2000000000, '69279037'], [20000000000, '65353130'],
]);

it('round-trips base32', function () {
    $raw = random_bytes(20);
    expect(Totp::base32Decode(Totp::base32Encode($raw)))->toBe($raw);
});

it('accepts codes within one step and rejects far-off, malformed and replayed codes', function () use ($secret) {
    $now = 1_700_000_000;
    $code = Totp::code($secret, $now);
    expect(Totp::verify($secret, $code, null, 1, $now))->not->toBeNull();
    expect(Totp::verify($secret, Totp::code($secret, $now - 30), null, 1, $now))->not->toBeNull();
    expect(Totp::verify($secret, Totp::code($secret, $now - 120), null, 1, $now))->toBeNull();
    expect(Totp::verify($secret, '12345', null, 1, $now))->toBeNull();
    expect(Totp::verify($secret, 'abcdef', null, 1, $now))->toBeNull();
    $step = Totp::verify($secret, $code, null, 1, $now);
    expect(Totp::verify($secret, $code, $step, 1, $now))->toBeNull(); // replay
});
