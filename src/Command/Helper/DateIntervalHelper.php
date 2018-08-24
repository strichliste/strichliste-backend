<?php

namespace App\Command\Helper;

use Symfony\Component\Console\Input\InputInterface;

class DateIntervalHelper {

    /**
     * @var \DateTime
     */
    private $dateTime;

    function __construct() {
        $this->dateTime = new \DateTime();
    }

    static function fromCommandInput(InputInterface $input): self {
        $self = new static;

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

    function subDays($days): self {
        $this->dateTime->sub(\DateInterval::createFromDateString(sprintf('%d days', $days)));

        return $this;
    }

    function subMonths($month): self {
        $this->dateTime->sub(\DateInterval::createFromDateString(sprintf('%d months', $month)));

        return $this;
    }

    function subYears($years): self {
        $this->dateTime->sub(\DateInterval::createFromDateString(sprintf('%d years', $years)));

        return $this;
    }

    function getDateTime(): \DateTime {
        return $this->dateTime;
    }
}