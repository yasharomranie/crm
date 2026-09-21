<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FormSubmissionValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_submission_id', 'form_field_id',
        'value_text', 'value_number', 'value_date', 'value_json', 'file_path',
    ];

    protected function casts(): array
    {
        return [
            // Without these, some MySQL/PDO driver configurations return
            // foreign key columns as strings instead of ints - harmless for
            // Eloquent relations (which use loose comparison internally),
            // but deadly for any ===/!== check against another model's id
            // (FormSubmissionFileController::show() compares this against
            // FormSubmission::$id with ===, which silently 404s a real,
            // correctly-stored file the moment this column comes back as a
            // string on a server where it did).
            'form_submission_id' => 'integer',
            'form_field_id' => 'integer',
            'value_number' => 'decimal:4',
            'value_date' => 'date',
            'value_json' => 'array',
        ];
    }

    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class);
    }

    /**
     * Resolve the value regardless of which typed column it lives in,
     * based on the parent field's type.
     */
    public function getValueAttribute(): mixed
    {
        return match (true) {
            $this->value_json !== null => $this->value_json,
            $this->value_number !== null => $this->value_number,
            $this->value_date !== null => $this->value_date,
            // A File field's answer lives in file_path, never value_text -
            // without this branch it fell through to value_text (always
            // null for a File row), so this accessor always looked blank
            // for a File field even with a real attachment stored, and
            // every caller that checks blank($value) before rendering
            // (FormSubmission::renderDocumentPlaceholder() foremost) never
            // even reached its File-specific branch.
            $this->file_path !== null => $this->file_path,
            default => $this->value_text,
        };
    }

    /**
     * Whether this File field's attachment is an image, judged by
     * extension - the only signal available, since a submission value's
     * mime type is never stored (unlike Document, whose upload form does
     * capture one).
     */
    public function fileIsImage(): bool
    {
        if (! $this->file_path) {
            return false;
        }

        $extension = strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }

    /**
     * A self-contained `data:image/...;base64,...` URI for an image
     * attachment - used wherever the attachment needs to render as an
     * inline preview without a live, authenticated download link (the
     * merged "متن سند" print/preview and the Word export, both of which
     * may be viewed offline or outside this app entirely). Null for a
     * non-image attachment, or one whose file has since gone missing.
     */
    public function fileAsDataUri(): ?string
    {
        if (! $this->fileIsImage()) {
            return null;
        }

        $disk = Storage::disk();

        if (! $disk->exists($this->file_path)) {
            return null;
        }

        $mimeType = $disk->mimeType($this->file_path) ?: 'image/png';

        return "data:{$mimeType};base64,".base64_encode($disk->get($this->file_path));
    }
}
