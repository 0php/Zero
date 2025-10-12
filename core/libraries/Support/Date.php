<?php

declare(strict_types=1);

namespace Zero\Lib\Support;

use DateTimeZone;

final class Date
{
    private DateTime $dateTime;

    public function __construct(string $time = 'now', ?DateTimeZone $timezone = null)
    {
        $this->dateTime = new DateTime($time, $timezone);
    }

    public static function now(?DateTimeZone $timezone = null): self
    {
        return new self('now', $timezone);
    }

    public static function parse(string $time, ?DateTimeZone $timezone = null): self
    {
        return new self($time, $timezone);
    }

    public function addDays(int $days): self
    {
        $this->dateTime = $this->dateTime->addDays($days);

        return $this;
    }

    public function subtractDays(int $days): self
    {
        $this->dateTime = $this->dateTime->subDays($days);

        return $this;
    }

    public function format(string $format = DateTime::ATOM): string
    {
        return $this->dateTime->format($format);
    }

    public function toDateTime(): DateTime
    {
        return $this->dateTime;
    }

    public function diffForHumans(self $other): string
    {
        return $this->dateTime->diffForHumans($other->toDateTime());
    }

    public function setTimeZone(string|DateTimeZone $timezone): self
    {
        $zone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;

        $this->dateTime = $this->dateTime->setTimezone($zone);

        return $this;
    }
}
