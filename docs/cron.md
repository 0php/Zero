# Scheduler & Cron

Zero Framework ships with a lightweight scheduler that mirrors Laravel's ergonomics while staying dependency-free. All scheduled work is funnelled through a single CLI entry point:

```bash
php zero schedule:run
```

Run this command every minute (or at the cadence you prefer) and the framework evaluates the schedule, runs due tasks, and skips everything else.

## Example Schedule (`routes/cron.php`)

```php
<?php

declare(strict_types=1);

use Zero\Lib\Console\Scheduling\Schedule;

return static function (Schedule $schedule): void {
    // Dispatch queued emails every five minutes without overlap.
    $schedule->command('emails:dispatch')
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->description('Dispatch queued transactional emails');

    // Sync analytics dashboards five past the hour on weekdays.
    $schedule->command('reports:sync')
        ->hourlyAt(5)
        ->daysOfWeek(1, 2, 3, 4, 5)
        ->description('Refresh BI dashboards');

    // Generate invoices nightly at 00:30.
    $schedule->command('billing:invoice')
        ->dailyAt('00:30')
        ->description('Generate customer invoices');

    // Rotate database backups every six hours at the 10th minute.
    $schedule->command('backups:rotate')
        ->everySixHours(10)
        ->description('Rotate database backups');

    // Run Sunday maintenance at 03:00.
    $schedule->command('maintenance:weekly')
        ->sunday()
        ->hours(3)
        ->minutes(0)
        ->description('Weekly maintenance window');

    // Quarterly statement export on the 1st and 15th at 02:00 (Jan/Apr/Jul/Oct).
    $schedule->command('finance:statements')
        ->months(1, 4, 7, 10)
        ->datesOfMonth(1, 15)
        ->hours(2)
        ->minutes(0)
        ->description('Quarterly financial statements');

    // Closure helper for cache refresh at 02:00 daily.
    $schedule->call(fn () => cache_clear())
        ->dailyAt('02:00')
        ->description('Refresh application cache');
};
```


The repository already ships with `routes/cron.php`; edit that file directly (or copy the example above) to tailor the schedule to your project.

## Local Test Harness

The repository includes an `app:test` command that appends entries to `storage/logs/cron-sample.log` and emits a debug message via the `internal` log channel. Use it to verify cadence helpers without wiring real workloads.

```bash
php zero app:test --type=manual-check
```

For automated smoke tests, append a block like this to your schedule and watch the log file:

```php
$schedule->command('app:test', ['--type=every-minute'])
    ->everyMinute()
    ->description('Cron sanity check: every minute');

$schedule->command('app:test', ['--type=every-three-minutes'])
    ->everyThreeMinutes()
    ->description('Cron sanity check: every three minutes');
```

Chain additional helpers (`everySixHours()`, `daysOfWeek()`, etc.) to exercise the combinations you rely on. Remove the block once you finish testing.

## Building Your Schedule

- Keep tasks idempotent so a run that fires twice in the same window does not produce duplicate side effects.
- Use descriptive command signatures (`reports:sync`, `emails:dispatch`) so scheduler logs stay readable.
- Delegate heavy work to services or dedicated commands—helpers should remain thin.
- Chain `withoutOverlapping()` (optionally `withoutOverlapping(900)`) to guard long-running jobs.
- Provide `description()` text when you want human-friendly log entries.
- Use `mutexName()` when multiple helpers must share a locking key.

## Frequency Helper Reference

| Cadence     | Helper(s)                                                                                                                                                                  |
| ----------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Minute      | `everyMinute()`, `everyTwoMinutes()`, `everyThreeMinutes()`, `everyFiveMinutes()`, `everyTenMinutes()`, `everyFifteenMinutes()`, `everyThirtyMinutes()`, `everyMinutes(n)` |
| Hour        | `hourly()`, `hourlyAt(minute)`, `twiceDaily(firstHour, secondHour)`, `everyHours(n, minute = 0)`, `everySixHours()`, `everyTwelveHours()`, `everyThirtySixHours()`         |
| Day         | `daily()`, `dailyAt('HH:MM')`, `weekdays('HH:MM')`, `weekends('HH:MM')`                                                                                                    |
| Week        | `weekly()` (defaults to Monday 00:00), `weeklyOn(dayOfWeek, 'HH:MM')` where Sunday = 0                                                                                     |
| Month       | `monthly(day = 1, 'HH:MM')`, `monthlyOn(day, 'HH:MM')`, `quarterly('HH:MM')`                                                                                               |
| Year        | `yearly('HH:MM')`                                                                                                                                                          |
| Custom work | `call(callable, description?)`                                                                                                                                             |

Each helper returns the underlying event so you can keep chaining (`->withoutOverlapping()->description('...')`).

### Additional Constraints

- `daysOfWeek(…)` or shorthand helpers (`monday()`, `tuesday()`, …) filter execution to specific weekdays.
- `datesOfMonth(…)` restricts runs to specific calendar dates.
- `months(…)` limits runs to the supplied months (1–12).
- `hours(…)` and `minutes(…)` accept lists of allowed clock values.

Chain these alongside cadence helpers to express complex schedules (for example, `->everySixHours(30)->daysOfWeek(1,3,5)->months(1,4,7,10)`).

## Cron Expressions

- `cron()` accepts five fields in the format `minute hour day-of-month month day-of-week`.
- Supported syntax includes lists (`1,15`), ranges (`1-5`), and steps (`*/10`).
- Month aliases (`jan`, `feb`, …) and weekday aliases (`sun`, `mon`, …) are recognised; Sunday may be `0` or `7`.
- Use `?` in day-of-month or day-of-week when the other field drives the schedule (e.g., `0 9 ? * mon`).
- Combine cron expressions with helper guards if you need multi-condition logic.

## Registering the Cron Entry

Add a single cron line so the scheduler runs every minute. Substitute your PHP binary, project root, and user:

```cron
* * * * * www-data /usr/bin/php /var/www/zero-framework/zero schedule:run >> /var/log/zero-schedule.log 2>&1
```

For full deployment steps (choosing the cron user, log destination, and verifying the install), follow the scheduler section in [`docs/deployment.md`](deployment.md#cron-and-scheduler).

For quick local smoke tests, open a second terminal and run a tiny loop that calls the scheduler every minute:

```bash
while true; do
  php zero schedule:run --path=routes/cron.php
  sleep 60
done
```

Press Ctrl+C when you are done. Adjust the `sleep` duration or inline additional logic to align with your workflow.

Container platforms (Kubernetes CronJob, ECS Scheduled Task, etc.) should invoke the same command on their own scheduler.

## Operating & Monitoring

- Manual runs: `php zero schedule:run` is safe when testing new tasks—the output is routed through the internal log channel.
- Logging: each run emits “running/completed/failed” log events; redirect cron stdout/stderr to an aggregated log file or service.
- Health checks: append a heartbeat (DB row, log ping, uptime monitor) at the end of `schedule:run` to detect missed invocations.
- Failure handling: the command exits with a non-zero status if any task throws; configure alerts on cron failures or log errors.

## Roadmap Ideas

- Introduce per-task timezone overrides (e.g. `$event->timezone('UTC')`).
- Surface lifecycle hooks (`before`/`after` callbacks, success/failure listeners).
- Support alternative mutex stores beyond the file-based lock.
- Document best practices for multi-environment schedules and feature toggles.

With the scheduler wired up, you consolidate recurring jobs behind one cron entry and keep operational visibility in a single place.
