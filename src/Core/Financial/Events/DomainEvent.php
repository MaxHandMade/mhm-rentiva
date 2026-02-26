<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Events;

/**
 * Empty interface for domain events.
 * 
 * @since 4.21.0
 */
interface DomainEvent
{
    /**
     * Get the descriptive name of the event.
     */
    public function get_event_name(): string;
}
