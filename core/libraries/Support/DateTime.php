<?php

declare(strict_types=1);

namespace Zero\Lib\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Immutable date helper with convenience methods for day arithmetic and human diffs.
 */
final class DateTime extends DateTimeImmutable
{
    /** Create an immutable "now" instance (optional timezone). */
    public static function now(?DateTimeZone $timezone = null): self
    {
        return new self('now', $timezone);
    }

    /** Parse relative or absolute expressions into an immutable DateTime. */
    public static function parse(string $datetime, ?DateTimeZone $timezone = null): self
    {
        return new self($datetime, $timezone);
    }

    /** Return a clone with N days added (negative values subtract). */
    public function addDays(int $days): self
    {
        if ($days === 0) {
            return $this;
        }

        $interval = new DateInterval('P' . abs($days) . 'D');

        return $days >= 0 ? $this->add($interval) : $this->sub($interval);
    }

    /** Return a clone with N days subtracted (negative values add). */
    public function subDays(int $days): self
    {
        if ($days === 0) {
            return $this;
        }

        $interval = new DateInterval('P' . abs($days) . 'D');

        return $days >= 0 ? $this->sub($interval) : $this->add($interval);
    }

    /** Human readable difference (e.g. "2 hours ago" / "3 days from now"). */
    public function diffForHumans(DateTimeInterface $other): string
    {
        $interval = $this->diff($other);

        if ($interval->y !== 0) {
            $value = abs($interval->y);
            $unit = $value === 1 ? 'year' : 'years';
        } elseif ($interval->m !== 0) {
            $value = abs($interval->m);
            $unit = $value === 1 ? 'month' : 'months';
        } elseif ($interval->d !== 0) {
            $value = abs($interval->d);
            $unit = $value === 1 ? 'day' : 'days';
        } elseif ($interval->h !== 0) {
            $value = abs($interval->h);
            $unit = $value === 1 ? 'hour' : 'hours';
        } elseif ($interval->i !== 0) {
            $value = abs($interval->i);
            $unit = $value === 1 ? 'minute' : 'minutes';
        } else {
            $value = abs($interval->s);
            $unit = $value === 1 ? 'second' : 'seconds';
        }

        $direction = $interval->invert === 1 ? 'ago' : 'from now';

        return sprintf('%d %s %s', $value, $unit, $direction);
    }

    /** Return a clone converted to the given timezone. */
    public function inTimeZone(string|DateTimeZone $timezone): self
    {
        $zone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;

        return $this->setTimezone($zone);
    }
}
