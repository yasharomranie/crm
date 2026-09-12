<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a booking would double-book a room: another active
 * (non-cancelled) booking already overlaps the requested date range.
 * See Booking::assertRoomAvailable() and CreateBooking::handleRecordCreation().
 */
class RoomNotAvailableException extends RuntimeException
{
    public static function forOverlap(string $roomName): self
    {
        return new self("اتاق «{$roomName}» برای این بازه تاریخ قبلاً رزرو شده است.");
    }
}
