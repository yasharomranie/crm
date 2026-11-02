<?php

namespace App\Domain\Ai;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * What the assistant chat does when something other than the AI provider
 * fails mid-turn (a database error, a host time limit, a bug). Without this
 * the request ends as Filament's generic "page load error" and nobody can
 * tell what went wrong; here the cause is logged and, for admins, shown.
 */
class AssistantFailureReporter
{
    public static function report(Throwable $exception): void
    {
        report($exception);

        activity('ai_assistant')
            ->causedBy(Auth::user())
            ->event('unexpected_error')
            ->withProperties([
                'class' => $exception::class,
                'message' => Str::limit($exception->getMessage(), 300),
                'where' => basename($exception->getFile()).':'.$exception->getLine(),
            ])
            ->log('خطای غیرمنتظره در دستیار هوشمند');

        $notification = Notification::make()
            ->title('دستیار هوشمند به این پیام پاسخ نداد')
            ->body('خطایی در پردازش رخ داد. دوباره تلاش کنید؛ اگر تکرار شد به مدیر سیستم خبر بدهید.')
            ->danger();

        if (Auth::user()?->hasRole('admin')) {
            $notification->body(
                'جزئیات برای مدیر: '.class_basename($exception).' — '.Str::limit($exception->getMessage(), 200).' ('.basename($exception->getFile()).':'.$exception->getLine().')',
            );
        }

        $notification->send();
    }
}
