<?php

namespace App\Domain\Ticketing;

use Exception;

/**
 * Every message thrown from this domain is written to be shown directly
 * to the cashier/API caller (Persian, no internal detail) - never a raw
 * exception message from elsewhere in the stack.
 */
class TicketingException extends Exception {}
