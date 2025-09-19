<?php
namespace Zero\Lib;

use DateTime;
use DateTimeZone;

class Date
{
    private DateTime $dateTime;

    public function __construct(string $time = "now", ?DateTimeZone $timezone = null)
    {
        $this->dateTime = new DateTime($time, $timezone);
    }

    public static function now(?DateTimeZone $timezone = null): self
    {
        return new self("now", $timezone);
    }

    public static function parse(string $time, ?DateTimeZone $timezone = null): self
    {
        return new self($time, $timezone);
    }

    public function addDays(int $days): self
    {
        $this->dateTime->modify("+{$days} days");
        return $this;
    }

    public function subtractDays(int $days): self
    {
        $this->dateTime->modify("-{$days} days");
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

    public function diffForHumans(Date $other): string
    {
        $interval = $this->dateTime->diff($other->toDateTime());

        if ($interval->y > 0) {
            return $interval->y . " year" . ($interval->y > 1 ? "s" : "") . " ago";
        } elseif ($interval->m > 0) {
            return $interval->m . " month" . ($interval->m > 1 ? "s" : "") . " ago";
        } elseif ($interval->d > 0) {
            return $interval->d . " day" . ($interval->d > 1 ? "s" : "") . " ago";
        } elseif ($interval->h > 0) {
            return $interval->h . " hour" . ($interval->h > 1 ? "s" : "") . " ago";
        } elseif ($interval->i > 0) {
            return $interval->i . " minute" . ($interval->i > 1 ? "s" : "") . " ago";
        } else {
            return $interval->s . " second" . ($interval->s > 1 ? "s" : "") . " ago";
        }
    }

    public function setTimeZone(string $timezone): self
    {
        $this->dateTime->setTimezone(new DateTimeZone($timezone));
        return $this;
    }
}
