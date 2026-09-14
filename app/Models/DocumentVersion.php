<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use HasFactory;

    protected $fillable = ['document_id', 'version_number', 'file_path', 'mime_type', 'size_kb', 'uploaded_by'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Same reasoning as Document::downloadFilename() - the document's title
     * plus the version number, with the real file extension appended.
     */
    public function downloadFilename(): string
    {
        $extension = pathinfo($this->file_path, PATHINFO_EXTENSION);
        $name = "{$this->document->title} (نسخه {$this->version_number})";

        return $extension ? "{$name}.{$extension}" : $name;
    }
}
