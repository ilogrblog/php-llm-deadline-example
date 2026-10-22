<?php
declare(strict_types=1);

if (!extension_loaded('curl')) {
    fwrite(STDERR, "The PHP curl extension is not loaded. Enable ext-curl and run again.\n");
    exit(2);
}
require __DIR__ . '/Deadline.php';

$checks = 0;
function check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) {
        fwrite(STDERR, "FAIL: $label\n");
        exit(1);
    }
    ++$checks;
    echo "PASS: $label\n";
}
/** Runs $fn and returns the exception class and message it throws, or null if it does not throw. */
function thrown(callable $fn): ?array {
    try {
        $fn();
    } catch (Throwable $e) {
        return [get_class($e), $e->getMessage()];
    }
    return null;
}

$time = 0;
$clock = static function () use (&$time): int { return $time; };
$deadline = new Deadline(3500, $clock);
$options = $deadline->curlOptions(2500, 500, 250, 200);
check($options[CURLOPT_TIMEOUT_MS] === 2500, 'initial attempt cap');
check($options[CURLOPT_CONNECTTIMEOUT_MS] === 500, 'configured connection cap binds below half of total');
$time = 2_500_000_000;
$options = $deadline->curlOptions(2500, 500, 250, 200);
check($options[CURLOPT_TIMEOUT_MS] === 750, 'second attempt shares original deadline');
$time = 2_950_000_000;
$options = $deadline->curlOptions(2500, 500, 250, 200);
check($options[CURLOPT_CONNECTTIMEOUT_MS] === 150, 'connection leaves half the transfer allowance');
$time = 3_050_000_000;
check($deadline->curlOptions(2500, 500, 250, 200)[CURLOPT_TIMEOUT_MS] === 200, 'minimum boundary accepted');
$time += 1;
check($deadline->curlOptions(2500, 500, 250, 200) === null, 'fractional milliseconds rounded down');
$time = 3_500_000_000;
check($deadline->curlOptions(2500, 500, 250, 200) === null, 'expired deadline never creates zero timeout');
$time = 9_000_000_000;
check($deadline->curlOptions(2500, 500, 250, 200) === null, 'long expired deadline still declines dispatch');
$time = 0;
check(thrown(fn() => $deadline->curlOptions(0, 500, 250, 200))[0] === InvalidArgumentException::class, 'invalid configuration rejected');
foreach ([
    [2500, 500, 250, 2501],
    [60001, 500, 250, 200],
    [2500, 60001, 250, 200],
    [2500, 0, 250, 200],
    [2500, 500, -1, 200],
    [2500, 500, 60001, 200],
    [2500, 500, 250, 1],
] as $values) {
    check(
        thrown(fn() => $deadline->curlOptions(...$values))[0] === InvalidArgumentException::class,
        'reject invalid allowance ' . json_encode($values)
    );
}
foreach ([0, 60001] as $invalidTotal) {
    check(
        thrown(fn() => new Deadline($invalidTotal, $clock))[0] === InvalidArgumentException::class,
        "reject invalid deadline $invalidTotal"
    );
}
check(
    thrown(fn() => Deadline::requireAsyncDns(0)) === [RuntimeException::class, 'This deadline policy requires asynchronous DNS.'],
    'reject synchronous DNS feature set'
);
check(thrown(fn() => Deadline::requireAsyncDns(CURL_VERSION_ASYNCHDNS)) === null, 'accept asynchronous DNS feature set');
$hostIsAsync = (curl_version()['features'] & CURL_VERSION_ASYNCHDNS) !== 0;
check(
    (thrown(fn() => new Deadline(3500, $clock))[0] ?? null) === ($hostIsAsync ? null : RuntimeException::class),
    'constructor agrees with this host\'s cURL feature mask'
);
$time = 0;
$fresh = new Deadline(3500, $clock);
check($fresh->curlOptions(2500, 500, 3500, 200) === null, 'finish reserve consuming all time declines dispatch');
$sweepOk = true;
for ($ms = 2; $ms <= 3500; ++$ms) {
    $o = $fresh->curlOptions($ms, 60000, 0, 2);
    $sweepOk = $sweepOk && $o[CURLOPT_TIMEOUT_MS] === $ms
        && $o[CURLOPT_CONNECTTIMEOUT_MS] >= 1
        && $o[CURLOPT_CONNECTTIMEOUT_MS] <= intdiv($ms, 2);
}
check($sweepOk, '3499 allowance cases keep connection positive and at most half of total');
// The default clock is hrtime(): the only check that sleeps, and only for a quarter of a second.
$real = new Deadline(1000);
usleep(250_000);
$spent = $real->curlOptions(1000, 500, 0, 2)[CURLOPT_TIMEOUT_MS];
check($spent <= 750 && $spent >= 2, 'default clock counts real elapsed time');
// Hypothetical rates: 1 microdollar/input token, 4/output token.
$attemptMicrodollars = 2000 * 1 + 300 * 4;
check($attemptMicrodollars === 3200, 'single attempt costs $0.0032');
check($attemptMicrodollars * 2 === 6400, 'two attempts cost $0.0064');
check($attemptMicrodollars + 8000 > 10000, 'expensive fallback exceeds one-cent budget');
echo "$checks checks passed. No network calls made.\n";
