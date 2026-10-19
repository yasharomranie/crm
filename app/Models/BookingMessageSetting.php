<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A singleton settings row (always id=1): which automatic SMS a guest gets
 * around a stay (confirmation, arrival reminder, feedback request) and the
 * wording of each. Everything is off until the manager turns it on, so
 * deploying never starts texting guests by surprise.
 */
class BookingMessageSetting extends Model
{
    public const DEFAULT_CONFIRMATION = '{guest} عزیز، رزرو {code} شما ({room}) از {check_in} تا {check_out} قطعی شد. منتظر دیدار شما هستیم.';

    public const DEFAULT_REMINDER = '{guest} عزیز، یادآوری ورود: {check_in} در {room} میزبان شما هستیم. کد رزرو: {code}';

    public const DEFAULT_FEEDBACK = '{guest} عزیز، از اقامت شما سپاسگزاریم. لطفاً نظرتان را در یک دقیقه با ما در میان بگذارید: {link}';

    /** @var list<string> */
    public const PLACEHOLDERS = ['{guest}', '{code}', '{room}', '{check_in}', '{check_out}', '{nights}', '{link}'];

    protected $fillable = [
        'confirmation_enabled', 'reminder_enabled', 'reminder_days_before', 'feedback_enabled',
        'confirmation_template', 'reminder_template', 'feedback_template',
    ];

    protected function casts(): array
    {
        return [
            'confirmation_enabled' => 'boolean',
            'reminder_enabled' => 'boolean',
            'reminder_days_before' => 'integer',
            'feedback_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'confirmation_enabled' => false,
            'reminder_enabled' => false,
            'reminder_days_before' => 1,
            'feedback_enabled' => false,
        ]);
    }

    public function confirmationText(): string
    {
        return filled($this->confirmation_template) ? $this->confirmation_template : self::DEFAULT_CONFIRMATION;
    }

    public function reminderText(): string
    {
        return filled($this->reminder_template) ? $this->reminder_template : self::DEFAULT_REMINDER;
    }

    public function feedbackText(): string
    {
        return filled($this->feedback_template) ? $this->feedback_template : self::DEFAULT_FEEDBACK;
    }
}
