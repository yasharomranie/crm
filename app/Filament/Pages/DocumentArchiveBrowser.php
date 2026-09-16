<?php

namespace App\Filament\Pages;

use App\Enums\DocumentAccessLevel;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentFolder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;

/**
 * A file-manager-style view of "مدیریت اسناد" (folders and documents mixed
 * together, drill-down navigation, drag-and-drop) - added alongside the
 * existing DocumentResource/DocumentFolderResource tables rather than
 * replacing them, since those still serve sortable/filterable "all
 * documents across every folder" browsing (e.g. the company filter) that a
 * folder-at-a-time tree view doesn't.
 *
 * Deliberately does not filter *folders* by any access-control rule (only
 * the documents shown inside a folder go through Document::visibleTo()) -
 * this mirrors DocumentFolderResource's own table, which has never scoped
 * folder visibility either; folders are just navigation, not content.
 */
class DocumentArchiveBrowser extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.document-archive-browser';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'مدیریت اسناد';

    protected static ?string $navigationLabel = 'بایگانی اسناد (فایل‌منیجر)';

    protected static ?string $title = 'بایگانی اسناد';

    #[Url(as: 'folder')]
    public ?int $folderId = null;

    public static function accessControlPermission(): string
    {
        return 'document-browser.access';
    }

    public function openFolder(?int $folderId): void
    {
        $this->folderId = $folderId;
    }

    public function currentFolder(): ?DocumentFolder
    {
        return $this->folderId ? DocumentFolder::find($this->folderId) : null;
    }

    /**
     * The folder path from root down to (but not including) the current
     * folder's own "you are here" crumb, which the view renders separately.
     *
     * @return Collection<int, DocumentFolder>
     */
    public function breadcrumbs(): Collection
    {
        $trail = collect();
        $folder = $this->currentFolder();

        while ($folder) {
            $trail->prepend($folder);
            $folder = $folder->parent;
        }

        return $trail;
    }

    /**
     * @return EloquentCollection<int, DocumentFolder>
     */
    public function subfolders(): EloquentCollection
    {
        return DocumentFolder::query()
            ->where('parent_id', $this->folderId)
            ->withCount(['children', 'documents'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Document>
     */
    public function documents(): EloquentCollection
    {
        return Document::query()
            ->visibleTo(auth()->user())
            ->where('folder_id', $this->folderId)
            ->orderBy('title')
            ->get();
    }

    /**
     * Drag-and-drop target for a document card - moves it into
     * $targetFolderId (null = root) unless it's already there.
     */
    public function moveDocument(int $documentId, ?int $targetFolderId): void
    {
        $document = Document::findOrFail($documentId);

        abort_unless(auth()->user()->can('update', $document), 403, 'شما اجازهٔ جابه‌جایی این سند را ندارید.');

        if ($document->folder_id === $targetFolderId) {
            return;
        }

        $document->update(['folder_id' => $targetFolderId]);

        Notification::make()->title("سند «{$document->title}» جابه‌جا شد.")->success()->send();
    }

    /**
     * Drag-and-drop target for a folder card - moves it under
     * $targetFolderId (null = root), refusing to drop a folder into
     * itself or into one of its own descendants (which would otherwise
     * silently corrupt the tree into an unreachable loop).
     */
    public function moveFolder(int $folderId, ?int $targetFolderId): void
    {
        $folder = DocumentFolder::findOrFail($folderId);

        abort_unless(auth()->user()->can('update', $folder), 403, 'شما اجازهٔ جابه‌جایی این پوشه را ندارید.');

        if ($folder->id === $targetFolderId || $folder->parent_id === $targetFolderId) {
            return;
        }

        if ($targetFolderId !== null && $this->isSelfOrDescendant(DocumentFolder::findOrFail($targetFolderId), $folder->id)) {
            Notification::make()
                ->title('نمی‌توانید یک پوشه را داخل خودش یا زیرپوشه‌هایش جابه‌جا کنید.')
                ->danger()
                ->send();

            return;
        }

        $folder->update(['parent_id' => $targetFolderId]);

        Notification::make()->title("پوشه «{$folder->name}» جابه‌جا شد.")->success()->send();
    }

    private function isSelfOrDescendant(DocumentFolder $candidate, int $ancestorId): bool
    {
        $current = $candidate;

        while ($current) {
            if ($current->id === $ancestorId) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createFolder')
                ->label('پوشه جدید')
                ->icon(Heroicon::OutlinedFolderPlus)
                ->color('gray')
                ->visible(fn () => auth()->user()->can('create', DocumentFolder::class))
                ->schema([
                    TextInput::make('name')
                        ->label('نام پوشه')
                        ->required()
                        ->maxLength(255),
                    Select::make('company_id')
                        ->label('شرکت/هلدینگ')
                        ->options(fn () => Company::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->visible(fn () => $this->folderId === null)
                        ->helperText('فقط برای پوشه‌های سطح بالا معنا دارد.'),
                    Select::make('department_id')
                        ->label('مخصوص دپارتمان (اختیاری)')
                        ->options(fn () => Department::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    DocumentFolder::create([
                        'parent_id' => $this->folderId,
                        'name' => $data['name'],
                        'company_id' => $data['company_id'] ?? null,
                        'department_id' => $data['department_id'] ?? null,
                    ]);

                    Notification::make()->title('پوشه ساخته شد.')->success()->send();
                }),

            Action::make('uploadDocument')
                ->label('بارگذاری سند')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->visible(fn () => auth()->user()->can('create', Document::class))
                ->schema([
                    TextInput::make('title')
                        ->label('عنوان سند')
                        ->required()
                        ->maxLength(255),
                    FileUpload::make('file_path')
                        ->label('فایل')
                        ->directory('documents')
                        ->previewable(false)
                        ->openable(false)
                        ->downloadable(false)
                        ->required(),
                    Select::make('access_level')
                        ->label('سطح دسترسی')
                        ->options(DocumentAccessLevel::class)
                        ->default(DocumentAccessLevel::PublicInternal)
                        ->required()
                        ->live(),
                    Select::make('department_id')
                        ->label('دپارتمان مجاز')
                        ->options(fn () => Department::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(fn (Get $get) => $get('access_level') === DocumentAccessLevel::DepartmentOnly)
                        ->visible(fn (Get $get) => $get('access_level') === DocumentAccessLevel::DepartmentOnly),
                ])
                ->action(function (array $data): void {
                    Document::create([
                        'title' => $data['title'],
                        'file_path' => $data['file_path'],
                        'mime_type' => Storage::disk()->mimeType($data['file_path']),
                        'size_kb' => (int) ceil(Storage::disk()->size($data['file_path']) / 1024),
                        'access_level' => $data['access_level'],
                        'department_id' => $data['department_id'] ?? null,
                        'folder_id' => $this->folderId,
                        'uploaded_by' => auth()->id(),
                    ]);

                    Notification::make()->title('سند بارگذاری شد.')->success()->send();
                }),
        ];
    }
}
