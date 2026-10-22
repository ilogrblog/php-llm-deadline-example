# PHP deadline helper for LLM API calls

A small PHP class that turns one shared deadline into cURL timeouts for each attempt, plus offline checks for it. It is the code behind the [iLogr](https://ilogr.com/) article **[Controlling LLM API Cost and Latency in PHP](https://ilogr.com/controlling-llm-api-cost-latency-php/)**, where the class is used to keep an LLM-backed feature inside one time budget across retries.

> **Before you use it:** this is example code, provided as is, with no warranty of any kind. Try it on a development machine or a staging copy first, never on a live site, store or database, and take a backup before you adapt it. You use it entirely at your own risk, and nobody who wrote or published it accepts any responsibility for loss, damage or downtime. The checks here make no network calls and write no files, but the class is only a teaching component, not a finished HTTP client (see "What it does not do").

## What it does

`Deadline` starts one monotonic clock (`hrtime()`) when a user action begins. Before each attempt you ask `curlOptions()` for the cURL options that fit what is left:

- it returns `CURLOPT_TIMEOUT_MS` and `CURLOPT_CONNECTTIMEOUT_MS` for the attempt, never more than the per-attempt cap and never more than the time left minus a reserve for finishing the response;
- it returns `null` when too little time remains, so you can decline the attempt instead of sending it with a zero timeout (which cURL treats as "no timeout");
- it throws `InvalidArgumentException` for impossible settings, and `RuntimeException` when the cURL build cannot do asynchronous DNS.

The connection timeout is capped at half of the attempt allowance. That is a teaching policy, not a rule: tune it against measured connection and generation times.

## Requirements

- 64-bit PHP 8.0 or later with the `curl` extension. The cURL build must report asynchronous DNS (the class checks this and refuses to run otherwise).
- `bash`, only for `bin/run-checks.sh`. Without it, run the three `php` commands under "Run it" yourself.

PHP 8.0 is the minimum because the class uses a trailing comma in a parameter list, which PHP 8.0 added. Nothing newer is needed. PHP 8.0 and 8.1 no longer receive security fixes, so run a branch that still does; [php.net/supported-versions.php](https://www.php.net/supported-versions.php) lists them.

## Run it

```bash
./bin/run-checks.sh
```

The script lints both PHP files and runs the checks. If PHP is missing, too old, 32-bit or without the curl extension, it says so and stops. Without PHP installed, use Docker instead:

```bash
docker run --rm -v "$PWD":/app:ro -w /app php:8.5-cli bash bin/run-checks.sh
```

Or run the PHP commands directly, from the `worked-example` folder as in the article:

```bash
php -l Deadline.php
php -l check.php
php check.php
```

Expected results:

| Situation | Result |
| --- | --- |
| Normal run | `No syntax errors detected` twice, then 27 `PASS:` lines and `27 checks passed. No network calls made.`, exit code 0 |
| A check fails | `FAIL: <what failed>` on standard error, exit code 1 |
| PHP missing, below 8.0, 32-bit, or no curl extension | one line saying which, exit code 2 |

One check waits a quarter of a second so that the default clock is tested against real time. Every other check uses an injected clock and does not sleep.

## What is in this repository

| Path | What it is |
| --- | --- |
| `worked-example/Deadline.php` | The helper class. |
| `worked-example/check.php` | The checks: attempt sharing across one deadline, rounding, the minimum and expired cases, every rejected setting, the cost arithmetic from the article (in integer microdollars, with hypothetical prices). |
| `bin/run-checks.sh` | Runs lint and checks, with friendly errors. |
| `LICENSE` | MIT licence. |

The folder name `worked-example` is kept because the article refers to it.

## What it does not do

It is not an HTTP client. It does not cancel requests, schedule retries, keep a spending ledger, cache results, take locks, store anything, admit concurrent requests or evaluate models. It only works out an allowance at the moment you call it, so call it again immediately before each dispatch, bound your other work (database, cache, lock waits, response size), and check the time after the call returns. You still need to configure a fixed endpoint, credentials, certificate verification, response size limits, output validation and error handling yourself.

The optional clock argument exists for deterministic tests. It must return monotonic integer nanoseconds; the class does not defend against a clock that moves backwards.

## Tested

PHP 8.0.30, 8.1.34, 8.2.35, 8.3.36, 8.4.27 and 8.5.12 (the official Docker `php` images, libcurl 7.74.0 to 8.14.1) and the 8.6.0 release candidate 3, each run twice through `bin/run-checks.sh` and once more with all warnings and deprecations enabled, with none reported. PHP 8.3.6 from Ubuntu 24.04 (`php-cli` plus `php-curl`) and 64-bit PHP 8.1.34 on macOS, the version the article mentions, also pass. Later versions may differ.

## Licence

MIT. See `LICENSE`. This repository is not a WordPress plugin or theme, so the GPL-2.0-or-later rule for WordPress code does not apply.

More code that accompanies iLogr guides: [github.com/ilogrblog](https://github.com/ilogrblog).
