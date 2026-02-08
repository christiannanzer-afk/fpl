<?php

namespace FPL;

class GameweekCalculator
{
    private array $events;

    public function __construct(array $events)
    {
        $this->events = $events;
    }

    public function getCurrentGameweek(): int
    {
        foreach ($this->events as $event) {
            if ($event['is_current']) {
                return $event['id'];
            }
        }

        // Fallback: höchste finished GW
        $finishedGws = array_filter($this->events, fn($e) => $e['finished']);
        if (!empty($finishedGws)) {
            return max(array_column($finishedGws, 'id'));
        }

        return 1;
    }

    public function getNextGameweek(): int
    {
        foreach ($this->events as $event) {
            if (isset($event['is_next']) && $event['is_next']) {
                return $event['id'];
            }
        }

        return $this->getCurrentGameweek() + 1;
    }
}