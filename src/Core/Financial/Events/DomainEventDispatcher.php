<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Events;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A transaction-aware event dispatcher.
 * Buffers events in memory until they are explicitly flushed.
 * Used to ensure domain events (like forensic logs) only execute
 * if their parent database transaction is successfully committing.
 *
 * @since 4.21.0
 */
final class DomainEventDispatcher {

    /** @var array<string, callable[]> */
    private array $listeners = array();

    /** @var DomainEvent[] */
    private array $buffer = array();

    /**
     * Register a listener callback for a specific event name.
     */
    public function listen(string $event_name, callable $callback): void
    {
        if (! isset($this->listeners[ $event_name ])) {
            $this->listeners[ $event_name ] = array();
        }
        $this->listeners[ $event_name ][] = $callback;
    }

    /**
     * Buffer an event. It will NOT fire listeners until flush() is called.
     */
    public function dispatch(DomainEvent $event): void
    {
        $this->buffer[] = $event;
    }

    /**
     * Iterate through all buffered events and execute their registered listeners.
     * Clears the buffer after execution.
     * MUST be called immediately *before* a database COMMIT.
     */
    public function flush(): void
    {
        $events_to_flush = $this->buffer;
        $this->discard(); // Clear buffer immediately to prevent double-flushing if a listener throws

        foreach ($events_to_flush as $event) {
            $name = $event->get_event_name();
            if (isset($this->listeners[ $name ])) {
                foreach ($this->listeners[ $name ] as $listener) {
                    $listener($event);
                }
            }
        }
    }

    /**
     * Clear all buffered events from memory without firing them.
     * MUST be called inside a catch block during a ROLLBACK.
     */
    public function discard(): void
    {
        $this->buffer = array();
    }
}
