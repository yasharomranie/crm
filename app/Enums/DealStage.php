<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DealStage: string implements HasColor, HasLabel
{
    case NewLead = 'new_lead';
    case NeedsAssessment = 'needs_assessment';
    case ProposalSent = 'proposal_sent';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::NewLead => 'سرنخ جدید',
            self::NeedsAssessment => 'ارزیابی نیاز',
            self::ProposalSent => 'صدور پیش‌فاکتور',
            self::Negotiation => 'مذاکره نهایی',
            self::Won => 'موفق / قطعی',
            self::Lost => 'لغو شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NewLead => 'gray',
            self::NeedsAssessment => 'info',
            self::ProposalSent => 'warning',
            self::Negotiation => 'warning',
            self::Won => 'success',
            self::Lost => 'danger',
        };
    }

    /**
     * A standard, editor-free estimate of how likely a deal sitting in this
     * stage is to eventually close as "won" - used to turn raw open-pipeline
     * value into a weighted forecast on the sales dashboard. Not stored
     * per-deal; every deal in a stage shares the stage's estimate.
     */
    public function probability(): int
    {
        return match ($this) {
            self::NewLead => 10,
            self::NeedsAssessment => 30,
            self::ProposalSent => 60,
            self::Negotiation => 80,
            self::Won => 100,
            self::Lost => 0,
        };
    }

    /**
     * The pipeline's fixed left-to-right order, for the Kanban board -
     * Won/Lost are terminal columns at the end.
     */
    public static function pipelineOrder(): array
    {
        return [
            self::NewLead,
            self::NeedsAssessment,
            self::ProposalSent,
            self::Negotiation,
            self::Won,
            self::Lost,
        ];
    }
}
