<?php

namespace App\Enums;

/**
 * Lifecycle of one App\Models\AiAssistantOcrUpload row, which exists
 * purely so the AI Assistants Hub panel can show the owner that a
 * scanned-PDF upload is still being OCR'd in the background instead of
 * going quiet for several minutes with no visible state at all.
 */
enum AiAssistantOcrUploadStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
