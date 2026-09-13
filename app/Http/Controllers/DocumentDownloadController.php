<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a document's file from the private "local" disk. Files are
 * intentionally NOT on the public disk/symlink, since that would let
 * anyone with the URL bypass Document::isVisibleTo() entirely - every
 * download must go through this authenticated, access-checked route.
 */
class DocumentDownloadController extends Controller
{
    public function current(Request $request, Document $document): StreamedResponse
    {
        abort_unless($document->isVisibleTo($request->user()), 403, 'شما به این سند دسترسی ندارید.');

        return Storage::disk('local')->download($document->file_path, $document->title);
    }

    public function version(Request $request, DocumentVersion $version): StreamedResponse
    {
        abort_unless($version->document->isVisibleTo($request->user()), 403, 'شما به این سند دسترسی ندارید.');

        return Storage::disk('local')->download(
            $version->file_path,
            "{$version->document->title} (نسخه {$version->version_number})",
        );
    }
}
