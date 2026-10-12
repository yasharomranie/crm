<?php

namespace App\Models;

use App\Enums\AiAssistantOcrUploadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks one background OCR run (App\Jobs\OcrKnowledgeUploadJob) from
 * dispatch to completion, purely so the AI Assistants Hub panel can show
 * the owner that a scanned-PDF upload is still being read instead of
 * going quiet for several minutes - the job itself still does its real
 * work via App\Domain\Ai\ScannedPdfOcrExtractor and
 * App\Domain\Ai\KnowledgeNoteExtractor; this row is only ever read to
 * render status, never to drive the OCR itself.
 */
class AiAssistantOcrUpload extends Model
{
    use HasFactory;

    protected $fillable = ['ai_assistant_profile_id', 'original_filename', 'status', 'note_count', 'error_message'];

    protected function casts(): array
    {
        return [
            'status' => AiAssistantOcrUploadStatus::class,
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'ai_assistant_profile_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [AiAssistantOcrUploadStatus::Pending, AiAssistantOcrUploadStatus::Processing]);
    }
}
