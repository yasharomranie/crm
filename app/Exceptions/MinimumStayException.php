<?php

namespace App\Exceptions;

/**
 * The stay is shorter than the room type (or a price rule on those dates)
 * requires. A RoomNotAvailableException, so the panel, the external API and
 * the customer app already turn it into a message.
 */
class MinimumStayException extends RoomNotAvailableException
{
    public static function forStay(string $roomTypeName, int $minNights): self
    {
        return new self("حداقل مدت اقامت «{$roomTypeName}» در این تاریخ‌ها {$minNights} شب است.");
    }
}
