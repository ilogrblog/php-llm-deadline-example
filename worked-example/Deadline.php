<?php
declare(strict_types=1);

/** Teaching helper. Needs 64-bit PHP 8.0 or later and ext-curl with asynchronous DNS. */
final class Deadline
{
    private Closure $now;
    private int $endNs;

    public function __construct(int $totalMs, ?Closure $now = null)
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('ext-curl is required.');
        }
        self::requireAsyncDns(curl_version()['features']);
        if (PHP_INT_SIZE !== 8 || $totalMs < 1 || $totalMs > 60_000) {
            throw new InvalidArgumentException('Use 64-bit PHP and a 1..60000 ms deadline.');
        }
        $this->now = $now ?? static fn(): int => hrtime(true);
        $this->endNs = ($this->now)() + $totalMs * 1_000_000;
    }

    /** Also callable by deployment checks; flags must come from curl_version(). */
    public static function requireAsyncDns(int $features): void
    {
        if (($features & CURL_VERSION_ASYNCHDNS) === 0) {
            throw new RuntimeException('This deadline policy requires asynchronous DNS.');
        }
    }

    public function curlOptions(
        int $perAttemptMs,
        int $connectMs,
        int $finishReserveMs,
        int $minimumAttemptMs,
    ): ?array {
        if ($perAttemptMs > 60_000
            || $connectMs < 1 || $connectMs > 60_000 || $finishReserveMs < 0
            || $finishReserveMs > 60_000 || $minimumAttemptMs < 2
            || $minimumAttemptMs > $perAttemptMs) {
            throw new InvalidArgumentException('Invalid time allowance.');
        }
        // Negative once the deadline has passed; that falls below the minimum and returns null.
        $remainingMs = intdiv($this->endNs - ($this->now)(), 1_000_000);
        $allowedMs = min($perAttemptMs, $remainingMs - $finishReserveMs);
        if ($allowedMs < $minimumAttemptMs) {
            return null;
        }
        return [
            CURLOPT_TIMEOUT_MS => $allowedMs,
            // A chosen connection policy, not a promise of response completion.
            CURLOPT_CONNECTTIMEOUT_MS => min($connectMs, intdiv($allowedMs, 2)),
        ];
    }
}
