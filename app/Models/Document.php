<?php

namespace App\Models;

use App\Enums\DocumentAccessLevel;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use HasFactory, HasTags;

    protected $fillable = [
        'folder_id', 'title', 'file_path', 'mime_type', 'size_kb',
        'owner_id', 'owner_type', 'access_level', 'department_id', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            // 'uploaded_by' cast to int: without it, some MySQL/PDO driver
            // configurations return this foreign key as a string, which
            // silently breaks the strict === check in isVisibleTo() below
            // (see FormSubmissionValue for the same bug, confirmed live).
            'uploaded_by' => 'integer',
            'access_level' => DocumentAccessLevel::class,
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Resolved through the document's department rather than a dedicated
     * column - the holding company registry is the single source of
     * truth for "which company", and a department already knows its own.
     */
    public function company(): ?Company
    {
        return $this->department?->company;
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->latest('version_number');
    }

    /**
     * The download's suggested filename: the document's own title, not the
     * randomly-generated storage name - but with the real file extension
     * appended, since the title alone has none. Without this, every
     * download in the app was silently saved by the browser with no
     * extension at all (e.g. "گزارش" instead of "گزارش.pdf"), which is why
     * a downloaded file could look "broken" or unopenable.
     */
    public function downloadFilename(): string
    {
        $extension = pathinfo($this->file_path, PATHINFO_EXTENSION);

        return $extension ? "{$this->title}.{$extension}" : $this->title;
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DocumentShare::class);
    }

    /**
     * Archives the current file as a new DocumentVersion, then makes the
     * given file the document's new "live" file. The document row always
     * holds the current file; document_versions holds history.
     */
    public function replaceFile(string $filePath, ?string $mimeType, ?int $sizeKb, User $uploadedBy): void
    {
        $this->versions()->create([
            'version_number' => $this->nextVersionNumber(),
            'file_path' => $this->file_path,
            'mime_type' => $this->mime_type,
            'size_kb' => $this->size_kb,
            'uploaded_by' => $this->uploaded_by,
        ]);

        $this->update([
            'file_path' => $filePath,
            'mime_type' => $mimeType,
            'size_kb' => $sizeKb,
            'uploaded_by' => $uploadedBy->id,
        ]);
    }

    public function nextVersionNumber(): int
    {
        return ($this->versions()->max('version_number') ?? 0) + 1;
    }

    /**
     * Access control (doc2 "مدیریت اسناد" - سطح دسترسی): an "admin" role
     * bypasses everything; otherwise PublicInternal is open to all staff,
     * DepartmentOnly requires the document's department to be the user's
     * own department or an ancestor of it (so a document restricted to a
     * holding-level department like "گنج‌نامه" is visible to everyone in
     * any department beneath it, e.g. "پذیرش گنج‌نامه" - not just an exact
     * department_id match), and Restricted requires being the uploader or
     * an explicitly shared user.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return match ($this->access_level) {
            DocumentAccessLevel::PublicInternal => true,
            DocumentAccessLevel::DepartmentOnly => $this->department_id !== null
                && $user->department !== null
                && in_array($this->department_id, $user->department->selfAndAncestorIds(), true),
            DocumentAccessLevel::Restricted => (int) $this->uploaded_by === (int) $user->id
                || $this->shares()->where('user_id', $user->id)->exists(),
        };
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        $departmentAndAncestorIds = $user->department?->selfAndAncestorIds() ?? [];

        return $query->where(function (Builder $query) use ($user, $departmentAndAncestorIds) {
            $query->where('access_level', DocumentAccessLevel::PublicInternal)
                ->orWhere(function (Builder $query) use ($departmentAndAncestorIds) {
                    $query->where('access_level', DocumentAccessLevel::DepartmentOnly)
                        ->whereNotNull('department_id')
                        ->whereIn('department_id', $departmentAndAncestorIds);
                })
                ->orWhere(function (Builder $query) use ($user) {
                    $query->where('access_level', DocumentAccessLevel::Restricted)
                        ->where(function (Builder $query) use ($user) {
                            $query->where('uploaded_by', $user->id)
                                ->orWhereHas('shares', fn (Builder $query) => $query->where('user_id', $user->id));
                        });
                });
        });
    }
}
