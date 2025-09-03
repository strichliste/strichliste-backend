<?php

namespace App\Command\Helper;

use DateInterval;
use DateTime;
use Symfony\Component\Console\Input\InputInterface;

class DateIntervalHelper {
    private readonly DateTime $dateTime;

    public function __construct() {
        $this->dateTime = new DateTime();
    }

    public static function fromCommandInput(InputInterface $input): self {
        $self = new static();

        $days = $input->getOption('days');
        if ($days) {
            $self->subDays($days);
        }

        $months = $input->getOption('months');
        if ($months) {
            $self->subMonths($months);
        }

        $years = $input->getOption('years');
        if ($years) {
            $self->subYears($years);
        }

        return $self;
    }

    public function subDays($days): self {
        $this->dateTime->sub(DateInterval::createFromDateString(\sprintf('%d days', $days)));

        return $this;
    }

    public function subMonths($month): self {
        $this->dateTime->sub(DateInterval::createFromDateString(\sprintf('%d months', $month)));

        return $this;
    }

    public function subYears($years): self {
        $this->dateTime->sub(DateInterval::createFromDateString(\sprintf('%d years', $years)));

        return $this;
    }

    public function getDateTime(): DateTime {
        return $this->dateTime;
    }
}
