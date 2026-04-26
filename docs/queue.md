# Queue

Zero Framework ships a lightweight queue system for moving work out of the request lifecycle. Jobs are PHP classes; drivers control how they're stored and retrieved. The default driver runs jobs synchronously so there's nothing to install or configure to start.

---

## Contents

- [Quick start](#quick-start)
- [Defining a job](#defining-a-job)
  - [The `Job` interface](#the-job-interface)
  - [Per-job tuning properties](#per-job-tuning-properties)
  - [The `Dispatchable` trait](#the-dispatchable-trait)
- [Dispatching](#dispatching)
- [`PendingDispatch` API](#pendingdispatch-api)
- [Configuration](#configuration)
- [Drivers](#drivers)
- [Running workers](#running-workers)
  - [`queue:work` flags](#queuework-flags)
  - [Cron-only deployments](#cron-only-deployments)
  - [Production deployment](#production-deployment)
- [Failed jobs](#failed-jobs)
- [Job arguments and serialization](#job-arguments-and-serialization)
- [Working with models](#working-with-models)
- [Job lifecycle](#job-lifecycle)
- [Common patterns](#common-patterns)
- [Testing jobs](#testing-jobs)
- [Worker observability](#worker-observability)
- [Limitations & gotchas](#limitations--gotchas)
- [Authoring a custom driver](#authoring-a-custom-driver)
- [Tips](#tips)
- [Roadmap](#roadmap)

---

## Quick start

```bash
# 1. Generate a job class (creates app/jobs/SendOrderReceipt.php)
php zero make:job SendOrderReceipt

# 2. (Optional) Switch from sync to database-backed jobs
echo 'QUEUE_CONNECTION=database' >> .env
php zero migrate                       # creates jobs / failed_jobs tables

# 3. Dispatch from anywhere
SendOrderReceipt::dispatch($order->id);

# 4. Run the worker (only needed for the database driver)
php zero queue:work
```

That's it. With `QUEUE_CONNECTION=sync` (the default), step 4 isn't needed at all — jobs run inline.

---

## Defining a job

A job is any class implementing `Zero\Lib\Queue\Job`. The constructor takes whatever the dispatcher passes; `handle()` does the work; `failed()` (optional) runs once after the final retry.

```php
namespace App\Jobs;

use App\Models\Order;
use Zero\Lib\Queue\Dispatchable;
use Zero\Lib\Queue\Job;

class SendOrderReceipt implements Job
{
    use Dispatchable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);
        // …send the email
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('receipt failed for order ' . $this->orderId, [
            'message' => $exception->getMessage(),
        ]);
    }
}
```

Generate one with the CLI:

```bash
php zero make:job SendOrderReceipt
```

The stub already pulls in `Dispatchable` and includes default `$tries`/`$backoff` properties. Subdirectories work too: `php zero make:job Billing/SendOrderReceipt` produces `app/jobs/Billing/SendOrderReceipt.php` under namespace `App\Jobs\Billing`.

### The `Job` interface

```php
interface Zero\Lib\Queue\Job
{
    public function handle(): void;
}
```

That's the entire contract. `handle()` does the work. The framework also calls — when present — these optional methods on your class, none of which are required by the interface:

| Method | Signature | When it runs |
| --- | --- | --- |
| `failed` | `failed(\Throwable $exception): void` | Once, after the final retry exhausts and the job is moved to `failed_jobs`. Wrapped in its own try/catch, so a throw here is logged on `internal` and ignored — your worker keeps running. |

Other Laravel-style hooks (`middleware()`, `uniqueId()`, `retryUntil()`) are **not** wired up in v1 — see [Limitations](#limitations--gotchas).

### Per-job tuning properties

Public properties on your job class control retry behavior. They're optional; values fall back to the worker flags below them.

| Property | Type | Default | Effect |
| --- | --- | --- | --- |
| `$tries` | `int` | `--tries=` (CLI flag, default `1`) | Maximum attempts, including the first. After this many throws the job goes to `failed_jobs`. |
| `$backoff` | `int` | `--backoff=` (CLI flag, default `0`) | Seconds the row stays invisible after a failed attempt before re-becoming poppable. |

```php
class HeavyReport implements Job
{
    use Dispatchable;

    public int $tries = 5;
    public int $backoff = 120;   // wait 2 minutes between retries

    // ...
}
```

The job's own value always wins over the CLI flag — set `--tries=99` on the worker only as a last-resort backstop.

### The `Dispatchable` trait

Apply `use Zero\Lib\Queue\Dispatchable;` to a job to gain three static helpers. (You don't have to use the trait — `dispatch(new MyJob(...))` works regardless — but the trait makes call sites tidier.)

```php
MyJob::dispatch(...$args);                  // returns PendingDispatch
MyJob::dispatchSync(...$args);              // runs inline on the sync driver
MyJob::dispatchAfterResponse(...$args);     // returns PendingDispatch, defers until response is sent
```

See [Dispatching](#dispatching) for full signatures.

---

## Dispatching

### `dispatch(Job $job): PendingDispatch`
Push a job onto the default connection. Returns a fluent `PendingDispatch` so you can chain — and auto-flushes when it goes out of scope, so the terminator is optional.
```php
dispatch(new SendOrderReceipt($order->id));

dispatch(new SendOrderReceipt($order->id))
    ->onQueue('emails')
    ->onConnection('database')
    ->delay(60);
```

### `MyJob::dispatch(...$args): PendingDispatch`
Static shortcut provided by the `Dispatchable` trait.
```php
SendOrderReceipt::dispatch($order->id);
SendOrderReceipt::dispatch($order->id)->onQueue('emails');
```

### `MyJob::dispatchSync(...$args): void`
Run the job inline on the sync driver, regardless of the configured default. Useful in tests and inside transactions where you need the side-effects to commit/roll back together with the surrounding work.
```php
SendOrderReceipt::dispatchSync($order->id);
```

### `MyJob::dispatchAfterResponse(...$args): PendingDispatch`
Defer the actual push until *after* the HTTP response has been flushed to the client. Under PHP-FPM the framework calls `fastcgi_finish_request()` so the user sees their response immediately and the work runs in the background; under other SAPIs it falls back to a shutdown function. Combine with `->onConnection('sync')` for "do this work after returning the response" without standing up a worker.
```php
// Inline post-response work (no worker needed)
SendOrderReceipt::dispatchAfterResponse($order->id)->onConnection('sync');

// Push to a real queue, but only after the response is sent
SendOrderReceipt::dispatchAfterResponse($order->id)->onQueue('emails');
```

### `Queue::push(Job $job, ?string $queue = null, ?string $connection = null): void`
Lower-level entry point used by the helpers above. Takes the job and optional queue/connection overrides as positional args.
```php
Queue::push(new SendOrderReceipt($order->id));
Queue::push(new SendOrderReceipt($order->id), 'emails', 'database');
```

### `Queue::later(int $delaySeconds, Job $job, ?string $queue = null, ?string $connection = null): void`
Push a job for processing after a delay. Equivalent to `dispatch(...)->delay($n)` but without the fluent wrapper.
```php
Queue::later(120, new SendOrderReceipt($order->id), 'emails');
```

### `Queue::size(?string $queue = null, ?string $connection = null): int`
Approximate number of jobs waiting on a queue. Cheap on the database driver (`SELECT COUNT(*)`).
```php
Queue::size();              // default queue, default connection
Queue::size('emails');
Queue::size('emails', 'database');
```

### `Queue::driver(?string $connection = null): DriverInterface`
Get the resolved driver instance for advanced use (custom workers, diagnostics, or swapping for tests).
```php
Queue::driver('database')->size('emails');
```

---

## `PendingDispatch` API

Returned by `dispatch()`, `MyJob::dispatch()`, and `MyJob::dispatchAfterResponse()`. Chain to configure, then either let it auto-flush (it dispatches in `__destruct()`) or call `dispatch()` explicitly.

### `onQueue(string $queue): self`
Send the job to a specific queue. Workers consume queues in priority order based on the worker's `--queue=` flag.
```php
dispatch(new MyJob())->onQueue('high-priority');
```

### `onConnection(string $connection): self`
Override the default connection for this single dispatch.
```php
dispatch(new MyJob())->onConnection('database');
```

### `delay(int $seconds): self`
Make the job invisible to workers until `$seconds` from now.
```php
dispatch(new MyJob())->delay(30);                    // run no earlier than 30s
dispatch(new MyJob())->delay(60 * 60);               // run no earlier than 1 hour
```

### `afterResponse(): self`
Defer the push until after the HTTP response has been flushed. Pairs naturally with `onConnection('sync')` for inline post-response work that doesn't need a worker.
```php
dispatch(new SendAnalyticsPing($event))->afterResponse()->onConnection('sync');
```

### `dispatch(): void`
Force the push immediately. Idempotent — safe to call before `__destruct()` runs. Useful when you want the dispatch to happen at a precise point (e.g. inside a try/catch that only catches dispatch failures, not the `__destruct` ones which are logged silently).
```php
$pd = dispatch(new MyJob())->onQueue('emails');
$pd->dispatch();
```

---

## Configuration

`config/queue.php`:

```php
return [
    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        'database' => [
            'driver' => 'database',
            'connection' => null,        // null = default DB connection
            'table' => 'jobs',
            'failed_table' => 'failed_jobs',
            'queue' => 'default',
            'retry_after' => 90,         // seconds before a stuck reserved job is reclaimed
        ],
    ],

    'failed' => [
        'driver' => 'database',
        'connection' => null,
        'table' => 'failed_jobs',
    ],
];
```

Set `QUEUE_CONNECTION=database` in `.env` to switch the default away from `sync`. Each connection name maps to an entry under `connections`. You can define as many entries as you like — e.g. one `database` connection for transactional work and a second one pointed at a separate DB cluster for background analytics.

### Connection vs. queue

These two terms are easy to confuse:

- **Connection** (`onConnection`) — *which driver instance to use*. e.g. `database` (the configured DB-backed driver) vs. `sync`.
- **Queue** (`onQueue`) — *which named queue inside that driver*. The database driver stores the queue name in the `queue` column on the `jobs` table; workers filter by it.

A typical app uses a single connection (`database`) and several queues (`default`, `emails`, `reports`).

### Database setup

Run the bundled migrations (already shipped in `database/migrations/`):

```bash
php zero queue:table       # confirm the migrations are present
php zero migrate           # create the jobs / failed_jobs tables
```

`queue:table` is a sanity check — it reports whether the framework's queue migrations live in your `database/migrations/` directory and tells you what to run next.

---

## Drivers

### `sync`
Runs the job inline on push. Perfect for development, tests, and any environment where you don't want a worker.
- `handle()` runs immediately during `dispatch()`.
- An exception in `handle()` re-throws to the caller (after firing `failed()` once if defined).
- `pop()` always returns null — there's nothing to pop. Running `queue:work --connection=sync` is a no-op loop.
- Persists nothing.

### `database`
Stores jobs in the `jobs` table and failed jobs in `failed_jobs`. Workers reserve rows atomically:

| RDBMS | Reservation strategy |
| --- | --- |
| MySQL / MariaDB | `SELECT … FOR UPDATE` inside a transaction |
| PostgreSQL | `SELECT … FOR UPDATE SKIP LOCKED` for true parallel workers |
| SQLite | Transactional ordering (correct for single-writer setups; suitable for tests and small deployments) |

Stuck reserved rows (worker died mid-job) become visible again after `retry_after` seconds.

The `available_at` column controls visibility — a delayed job has a future `available_at`, an in-flight job has `reserved_at` set, and a freshly-released job has `reserved_at` cleared and `available_at` bumped by its backoff.

---

## Running workers

### `queue:work`

Pull jobs off a queue and process them.

```bash
# Long-running worker (run under supervisor/systemd)
php zero queue:work

# Specific connection and queue order
php zero queue:work --connection=database --queue=emails,default

# Process a single job and exit (cron-friendly)
php zero queue:work --once

# Override the per-job retry/backoff defaults
php zero queue:work --tries=5 --backoff=30 --sleep=3
```

#### `queue:work` flags

| Flag | Default | Purpose |
| --- | --- | --- |
| `--connection=` | `config('queue.default')` | Which queue connection to drain. |
| `--queue=` | connection's `queue` config | Comma-separated priority list. The first queue with work wins. |
| `--tries=` | `1` | Fallback when the job class doesn't define `$tries`. |
| `--backoff=` | `0` | Fallback when the job class doesn't define `$backoff`. |
| `--sleep=` | `3` | Seconds to sleep when no job is due. |
| `--once` | off | Process one job (or none if the queue is empty) and exit. |

The worker traps SIGTERM/SIGINT/SIGQUIT and exits cleanly between jobs. The current job always runs to completion.

### Cron-only deployments

No supervisor? Drain the queue from the scheduler:

```php
// routes/cron.php
$schedule->command('queue:work', ['--once', '--queue=default'])
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Drain default queue');
```

`--once` exits after a single job (or immediately if the queue is empty), so cron stays predictable. `withoutOverlapping()` prevents two minutes from running the same `queue:work` invocation in parallel. See [cron.md](cron.md) for scheduler details.

### Production deployment

Run `queue:work` under a process supervisor so that crashes restart automatically and SIGTERM gives the worker time to finish the current job.

#### supervisord

```ini
; /etc/supervisor/conf.d/zero-queue.conf
[program:zero-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/zero/zero queue:work --connection=database --queue=high,default --sleep=3 --tries=3
autostart=true
autorestart=true
stopwaitsecs=60
user=www-data
numprocs=2                        ; two parallel workers
redirect_stderr=true
stdout_logfile=/var/log/zero/queue.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopsignal=TERM
```

Reload after editing:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status zero-queue:*
```

#### systemd

```ini
; /etc/systemd/system/zero-queue@.service
[Unit]
Description=Zero queue worker (%i)
After=network.target mysql.service

[Service]
User=www-data
WorkingDirectory=/var/www/zero
ExecStart=/usr/bin/php /var/www/zero/zero queue:work --connection=database --queue=high,default --sleep=3
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
```

Then enable two instances:

```bash
sudo systemctl enable --now zero-queue@1.service zero-queue@2.service
sudo systemctl status zero-queue@*.service
```

#### Logrotate

```
# /etc/logrotate.d/zero-queue
/var/log/zero/queue.log {
    daily
    rotate 14
    compress
    missingok
    notifempty
    copytruncate
}
```

#### Restart workers after deploy

Workers cache the autoloader and class definitions in memory, so old code keeps running until the worker restarts. Trigger a graceful restart at the end of every deploy:

```bash
# supervisord
sudo supervisorctl restart zero-queue:*

# systemd
sudo systemctl restart 'zero-queue@*.service'
```

---

## Failed jobs

When a job exhausts its retries (or hits `--tries`), it lands in `failed_jobs` with the full exception trace. Operators have four commands:

### `queue:retry {id|all}`
Re-push a failed job back onto its original queue.
```bash
php zero queue:retry 42       # one job
php zero queue:retry all      # every failed job
```

### `queue:forget {id}`
Delete a single failed job row.
```bash
php zero queue:forget 42
```

### `queue:flush`
Wipe the `failed_jobs` table.
```bash
php zero queue:flush
```

### `queue:table`
Confirm the queue migrations are present in `database/migrations/`.
```bash
php zero queue:table
```

### Inspecting failed jobs by hand

```sql
SELECT id, queue, payload, exception, failed_at
FROM failed_jobs
ORDER BY failed_at DESC
LIMIT 20;
```

The `payload` column holds the JSON-encoded job (same shape as `jobs.payload`); `exception` holds the class name, message, and full stack trace.

---

## Job arguments and serialization

Constructor arguments are JSON-encoded into the job payload, so they must be representable. Allowed types:

- Scalars (`int`, `string`, `float`, `bool`, `null`)
- Arrays of allowed types (recursively)
- `Zero\Lib\Model` subclasses — stored as `{__model: Class, key: id}` and re-fetched via `Class::find($id)` on the worker side. If the model no longer exists, the job is moved straight to `failed_jobs`.

Closures and arbitrary objects throw at dispatch time:

```php
dispatch(new MyJob(fn () => doThing()));
// InvalidArgumentException: Closures cannot be queued. Wrap the work in a Job class instead.

dispatch(new MyJob(new SomeService()));
// InvalidArgumentException: Cannot serialize value of type SomeService for the queue.
```

The serializer requires every constructor parameter to map 1:1 with a public property of the same name (constructor property promotion is the cleanest way). This is what lets the worker rebuild the job from its payload.

✓ This is fine:
```php
public function __construct(public int $orderId, public string $reason) {}
```

✗ This won't serialize:
```php
public function __construct(int $orderId) {
    $this->orderRef = Order::find($orderId);
}
```

(There's no public `$orderRef` property the encoder can read, and even if there were, hydrating an `Order` from the payload would fall under the model rehydration rule below.)

---

## Working with models

The most common queue payload is "do something to row X". The serializer handles this for you — pass the model directly:

```php
namespace App\Jobs;

use App\Models\Order;
use Zero\Lib\Queue\Dispatchable;
use Zero\Lib\Queue\Job;

class FulfillOrder implements Job
{
    use Dispatchable;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        // $this->order is freshly fetched via Order::find($id)
        $this->order->markFulfilled();
    }
}
```

```php
FulfillOrder::dispatch($order);
```

What happens:

1. **At dispatch:** the encoder sees a `Model`, stores `{__model: 'App\\Models\\Order', key: 42}` in the payload.
2. **At pop:** the decoder reads the marker, calls `App\Models\Order::find(42)`, hands the fresh model to your constructor.
3. **If the row no longer exists** at pop time: the decoder throws, the driver moves the job to `failed_jobs` with a clear error. `handle()` never runs.

This means `handle()` always sees the latest state of the row, not a stale snapshot from when you dispatched. That's almost always what you want.

If you need the *original* values too (e.g. to detect "what changed since dispatch"), pass them as separate scalar args alongside the model:

```php
public function __construct(public Order $order, public int $previousStatusId) {}
```

---

## Job lifecycle

When the worker pops a job:

1. The driver atomically reserves the row, marks `reserved_at`, and increments `attempts`.
2. The worker rebuilds the job from its payload (and re-fetches any models). Hydration failure → straight to `failed_jobs`.
3. The worker calls `handle()`.
4. **Success** → driver deletes the row.
5. **Throw, attempts < tries** → driver releases the row (clears `reserved_at`, bumps `available_at` by `$backoff` seconds).
6. **Throw, attempts ≥ tries** → driver moves the payload + exception to `failed_jobs`, then calls `failed($exception)` on the job (if defined). The hook is wrapped in its own try/catch, so a misbehaving `failed()` doesn't crash the worker.

A successful job is permanently deleted from `jobs`. Failed jobs stay in `failed_jobs` forever (no automatic pruning) so you can inspect, retry, or forget them at your leisure.

---

## Common patterns

### Fan-out

Dispatch one job per item in a batch. The worker can process them in parallel (run several `queue:work` instances).

```php
$user->subscribers()->each(function ($subscriber) use ($post) {
    NotifySubscriber::dispatch($subscriber, $post);
});
```

### Chained side effects

The queue has no built-in chains. Get the same effect by dispatching the next job from inside `handle()`:

```php
class GenerateInvoice implements Job
{
    use Dispatchable;
    public function __construct(public Order $order) {}

    public function handle(): void
    {
        $invoice = Invoice::generateFor($this->order);

        // Now queue the email follow-up
        EmailInvoice::dispatch($invoice);
    }
}
```

### Delay until a specific time

```php
$delay = max(0, strtotime('tomorrow 09:00') - time());
SendDailyDigest::dispatch($user)->delay($delay);
```

### "Do it after the response is sent"

```php
class TrackPageview implements Job
{
    use Dispatchable;
    public function __construct(public string $url, public int $userId) {}
    public function handle(): void { Pageview::create([...]); }
}

// In a controller — runs *after* response is flushed, no worker required
TrackPageview::dispatchAfterResponse(request()->fullUrl(), auth()->id ?? 0)
    ->onConnection('sync');
```

### Throttle by hand

Until middleware lands, gate the work inside `handle()`:

```php
public function handle(): void
{
    if (Cache::get("rate:{$this->userId}") >= 5) {
        // bail; let the next attempt try again
        throw new \RuntimeException('rate limited');
    }
    // ...do work
}
```

Pair with a small `$backoff` and high `$tries` so retries are cheap.

---

## Testing jobs

Sync dispatch is the fastest path. It runs `handle()` inline so assertions work like any other code:

```php
public function testReceiptIsSent(): void
{
    $order = Order::factory()->create();

    SendOrderReceipt::dispatchSync($order->id);

    $this->assertSentEmailFor($order);
}
```

To verify a job was *queued* without actually running it, swap in a fake driver via `QueueManager::setDriver()`:

```php
use Zero\Lib\Queue\Drivers\DriverInterface;
use Zero\Lib\Queue\QueueManager;

class RecordingDriver implements DriverInterface
{
    public array $pushed = [];

    public function push($job, $queue = null): void { $this->pushed[] = [$job, $queue]; }
    public function later($delay, $job, $queue = null): void { $this->pushed[] = [$job, $queue, $delay]; }
    public function pop($queues): ?\Zero\Lib\Queue\ReservedJob { return null; }
    public function release($job, $delay): void {}
    public function delete($job): void {}
    public function fail($job, $exception): void {}
    public function size($queue = null): int { return count($this->pushed); }
}

$recorder = new RecordingDriver();
QueueManager::setDriver('database', $recorder);

dispatch(new SendOrderReceipt(1))->onConnection('database');

$this->assertCount(1, $recorder->pushed);
```

For end-to-end tests against the database driver, use a SQLite test database and run a worker tick manually:

```php
$worker = new \Zero\Lib\Queue\Worker(\Zero\Lib\Queue\QueueManager::driver('database'));
$worker->run(new \Zero\Lib\Queue\WorkerOptions(connection: 'database', queues: ['default'], once: true));
```

---

## Worker observability

The worker writes structured events to the **`internal`** log channel. Tail it during deploys or grep it for postmortems.

Lifecycle events you'll see per job:

| Event | Meaning |
| --- | --- |
| `Queue worker starting.` | Worker boot. Includes connection + queues + `once` flag. |
| `Processing queued job.` | Row reserved, `handle()` about to run. Includes job class, queue, attempt count. |
| `Queued job completed.` | `handle()` returned. Includes duration in ms. |
| `Queued job threw.` | Exception caught. Includes class + message + attempt vs. tries. |
| `Job failed() hook threw.` | The optional `failed()` callback itself threw — logged and ignored. |
| `after-response callback threw.` | `dispatchAfterResponse()` callback raised; logged on `internal`, doesn't bubble to the response. |

`queue:work` itself also writes a one-line stdout status per job under PHP CLI:

```
[2026-04-26 09:14:21] Processed: App\Jobs\SendOrderReceipt (123ms)
[2026-04-26 09:14:24] Retrying: App\Jobs\SyncReports (attempt 1/3 in 30s)
[2026-04-26 09:14:28] Failed: App\Jobs\SyncReports — connection refused
```

Pipe that into your supervisor's log file or any log aggregator.

### Exit codes

| Exit | Meaning |
| --- | --- |
| `0` | Worker exited cleanly (`--once` succeeded, or SIGTERM during idle). |
| `1` | Driver could not be resolved (config issue); the worker did not run. |

A failing *job* never causes the worker to exit non-zero — the failure is recorded in `failed_jobs` and the worker keeps going.

---

## Limitations & gotchas

Things v1 deliberately does **not** do:

- **Job batching** (Laravel-style `Bus::batch([...])`) — dispatch each job individually.
- **Job chains** (`->chain([...])`) — re-dispatch the next job from inside `handle()` instead.
- **Job middleware** (`uniqueId`, `rateLimited`, etc.) — gate by hand inside `handle()`.
- **Long-poll / blocking pop** — the worker polls every `--sleep=` seconds. It's fine; just don't expect single-millisecond latency.
- **Per-job timezone** — cadence/delay calculations use the system timezone.
- **Encrypted payloads** — payloads are plain JSON. Don't pass secrets as job arguments.

Things to watch out for:

- **At-least-once delivery.** If a worker crashes after `handle()` succeeds but before `delete()`, the row stays reserved until `retry_after` and then runs again. Make jobs idempotent.
- **Transactions and `dispatch()`.** If you dispatch from inside a DB transaction and the transaction rolls back, the row in `jobs` was committed by a *separate* statement and won't roll back with you. Either dispatch *after* `commit()`, or use `dispatchAfterResponse()` to delay until the request lifecycle is complete.
- **Long jobs and `retry_after`.** If a single job legitimately takes longer than `retry_after`, another worker will reclaim the row and run it in parallel. Bump `retry_after` in `config/queue.php` to a value comfortably larger than your slowest job's worst-case runtime.
- **Code reloads.** Workers hold the autoloaded classes in memory. Restart workers after every deploy.
- **Schema-less payloads.** Renaming a constructor argument or removing a property is a breaking change for any job that's already on the queue. Drain the queue (or run a payload migration) before deploying that change.

---

## Authoring a custom driver

To plug in a new backend (Redis, SQS, RabbitMQ, …) implement `Zero\Lib\Queue\Drivers\DriverInterface`:

```php
namespace App\Queue;

use Throwable;
use Zero\Lib\Queue\Drivers\DriverInterface;
use Zero\Lib\Queue\Job;
use Zero\Lib\Queue\ReservedJob;

class RedisDriver implements DriverInterface
{
    public function __construct(private array $config) {}

    public function push(Job $job, ?string $queue = null): void { /* ... */ }
    public function later(int $delaySeconds, Job $job, ?string $queue = null): void { /* ... */ }
    public function pop(array $queues): ?ReservedJob { /* ... */ }
    public function release(ReservedJob $job, int $delaySeconds): void { /* ... */ }
    public function delete(ReservedJob $job): void { /* ... */ }
    public function fail(ReservedJob $job, Throwable $exception): void { /* ... */ }
    public function size(?string $queue = null): int { /* ... */ }
}
```

Register it on the manager during bootstrap (e.g. inside a service provider or `core/bootstrap.php`):

```php
\Zero\Lib\Queue\QueueManager::setDriver('redis', new App\Queue\RedisDriver([
    'host' => env('REDIS_HOST', '127.0.0.1'),
    'port' => env('REDIS_PORT', 6379),
]));
```

Use `JobPayload::encode()` / `JobPayload::decode()` to serialize and rehydrate jobs — that way your driver gets the same closure-rejection / model-rehydration semantics as the bundled drivers for free.

---

## Tips

- Keep job constructors light. The args are serialized into JSON; treat them as IDs and lookup keys, not heavy domain objects.
- Make jobs idempotent. A worker that crashes mid-handle leaves the row reserved; after `retry_after` seconds it's visible again, so `handle()` may run twice for the same payload.
- Use `--queue=high,low` to prioritise. Workers always drain the leftmost queue with work first.
- Run `queue:work` under a supervisor (supervisord, systemd, ecs-agent) — when it exits non-zero, the supervisor restarts it.
- If you don't have a supervisor, fall back to cron + `--once`. Pair it with `withoutOverlapping()` so back-to-back invocations don't trample one another.
- Audit the `failed_jobs` table regularly. A growing pile of failures usually points to a bug, not a transient outage.
- Don't dispatch from inside a DB transaction unless you know the queue write commits separately — see [Limitations](#limitations--gotchas).

---

## Roadmap

- Background dispatch (`runInBackground()`) so a slow job doesn't block subsequent ones inside a single worker.
- Job middleware (rate-limit, unique).
- Redis driver (when an SDK ships).
- Per-job timezone overrides.
- Job batching and chains.
