<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SeoProjectGoal: string implements HasLabel
{
    case Sales = 'sales';
    case LeadGeneration = 'lead_generation';
    case Booking = 'booking';
    case Branding = 'branding';
    case ContentPublishing = 'content_publishing';
    case Affiliate = 'affiliate';
    case CallGeneration = 'call_generation';
    case FormSubmission = 'form_submission';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sales => 'فروش',
            self::LeadGeneration => 'جذب سرنخ (Lead)',
            self::Booking => 'رزرو',
            self::Branding => 'برندینگ',
            self::ContentPublishing => 'انتشار محتوا',
            self::Affiliate => 'افیلیت',
            self::CallGeneration => 'جذب تماس',
            self::FormSubmission => 'جذب فرم',
        };
    }
}
