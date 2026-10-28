<?php

namespace App\Http\Controllers;

use App\Enums\GuideReferenceStatus;
use App\Models\GuideReferenceImage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Staff tools of the smart guide. The model runs in the staff member's own
 * browser, which makes each photo's fingerprint and sends only that vector here.
 */
class GuideEnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('guide-reference-images.update'), 403);

        return view('guide.enroll');
    }

    public function pending(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('guide-reference-images.update'), 403);

        $images = GuideReferenceImage::query()
            ->with('object')
            ->when(! $request->boolean('all'), fn ($query) => $query->whereIn('status', [GuideReferenceStatus::Pending->value, GuideReferenceStatus::Failed->value]))
            ->orderBy('id')
            ->get();

        return response()->json([
            'images' => $images->map(fn (GuideReferenceImage $image) => [
                'id' => $image->id,
                'url' => $image->url(),
                'object' => $image->object?->title_fa,
            ])->values(),
        ]);
    }

    public function store(Request $request, GuideReferenceImage $referenceImage): JsonResponse
    {
        abort_unless($request->user()->can('guide-reference-images.update'), 403);

        $data = $request->validate([
            'embedding' => ['required', 'array', 'min:1', 'max:'.GuideReferenceImage::MAX_DIMENSIONS],
            'embedding.*' => ['required', 'numeric'],
        ]);

        try {
            $referenceImage->markProcessed($data['embedding']);
        } catch (InvalidArgumentException $exception) {
            $referenceImage->markFailed($exception->getMessage());

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['status' => $referenceImage->status->value]);
    }

    public function failed(Request $request, GuideReferenceImage $referenceImage): JsonResponse
    {
        abort_unless($request->user()->can('guide-reference-images.update'), 403);

        $data = $request->validate(['error' => ['required', 'string', 'max:500']]);

        $referenceImage->markFailed($data['error']);

        return response()->json(['status' => $referenceImage->status->value]);
    }

    public function accuracy(Request $request): View
    {
        abort_unless($request->user()->can('guide-objects.view_any'), 403);

        return view('guide.accuracy');
    }
}
