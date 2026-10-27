<?php

declare(strict_types=1);

namespace Tests\Support\Extension;

use Codeception\Event\FailEvent;
use Codeception\Events;
use Codeception\Extension;

/**
 * Diagnostic helper: prints the ORIGINAL failure of a test before other listeners
 * (such as the Yii2 module's _failed() hook, which can crash and hide it) run.
 * Remove it from codeception.yml once the tests run normally.
 */
class ShowFailure extends Extension
{
    public static array $events = [
        Events::TEST_FAIL => ['onFailure', 1000],
        Events::TEST_ERROR => ['onFailure', 1000],
    ];

    public function onFailure(FailEvent $event): void
    {
        $e = $event->getFail();

        $this->writeln('');
        $this->writeln('=== ORIGINAL FAILURE ===');
        $this->writeln(get_class($e) . ': ' . $e->getMessage());
        $this->writeln('at ' . $e->getFile() . ':' . $e->getLine());

        foreach (array_slice(explode("\n", $e->getTraceAsString()), 0, 12) as $line) {
            $this->writeln('  ' . $line);
        }

        if ($previous = $e->getPrevious()) {
            $this->writeln('caused by ' . get_class($previous) . ': ' . $previous->getMessage());
            $this->writeln('at ' . $previous->getFile() . ':' . $previous->getLine());
        }

        $this->writeln('========================');
    }
}