<?php

namespace App\Exceptions;

/**
 * Thrown when a booking is made for a guest the property no longer accepts
 * (Guest::$is_blocked). It is a RoomNotAvailableException so every booking
 * entry point (panel, customer app, external API) already shows its message.
 */
class GuestBlockedException extends RoomNotAvailableException
{
    public static function forGuest(string $guestName): self
    {
        return new self("پذیرش رزرو برای «{$guestName}» مسدود شده است؛ برای رفع مسدودی با مدیر تماس بگیرید.");
    }
}
