<?php

namespace App\Exceptions;

/**
 * A channel's allotment of a room type is full for the requested nights. It
 * is a RoomNotAvailableException so every place that already turns "cannot
 * book this" into a message (panel, external API 409, customer app) handles
 * it without change.
 */
class ChannelAllotmentExceededException extends RoomNotAvailableException
{
    public static function forChannel(string $channelName, string $roomTypeName, int $limit): self
    {
        return new self($limit === 0
            ? "کانال «{$channelName}» سهمیه‌ای برای «{$roomTypeName}» ندارد (سهمیه صفر است)."
            : "سهمیه‌ی کانال «{$channelName}» برای «{$roomTypeName}» در این بازه پر است (حداکثر {$limit} واحد).");
    }
}
