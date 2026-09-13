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
     * DepartmentOnly requires a matching department, and Restricted
     * requires being the uploader or an explicitly shared user.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return match ($this->access_level) {
            DocumentAccessLevel::PublicInternal => true,
            DocumentAccessLevel::DepartmentOnly => $this->department_id !== null
                && $this->department_id === $user->department_id,
            DocumentAccessLevel::Restricted => $this->uploaded_by === $user->id
                || $this->shares()->where('user_id', $user->id)->exists(),
        };
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user) {
            $query->where('access_level', DocumentAccessLevel::PublicInternal)
                ->orWhere(function (Builder $query) use ($user) {
                    $query->where('access_level', DocumentAccessLevel::DepartmentOnly)
                        ->whereNotNull('department_id')
                        ->where('department_id', $user->department_id);
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
