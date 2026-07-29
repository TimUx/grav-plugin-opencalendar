<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Events;

use RocketTheme\Toolbox\Event\Event;

/**
 * Dispatches pipeline events through Grav's fireEvent().
 */
final class GravEventDispatcher implements EventDispatcherInterface
{
    /**
     * @param object{fireEvent?: callable} $grav
     */
    public function __construct(private readonly object $grav)
    {
    }

    public function dispatch(string $eventName, array $arguments = []): object
    {
        $event = new Event($arguments);

        if (method_exists($this->grav, 'fireEvent')) {
            $this->grav->fireEvent($eventName, $event);
        }

        return $event;
    }
}
